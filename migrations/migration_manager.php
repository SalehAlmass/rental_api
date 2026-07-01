<?php
declare(strict_types=1);

/**
 * Database Migration Manager
 * 
 * Compares reference schema vs current database, generates safe migration plans,
 * auto-creates backups, and tracks all changes in system_migrations.
 * 
 * Safe operations only:
 *   - CREATE TABLE IF NOT EXISTS
 *   - ALTER TABLE ADD COLUMN IF NOT EXISTS  
 *   - CREATE INDEX IF NOT EXISTS
 * 
 * Forbidden:
 *   - DROP TABLE / DROP COLUMN / DROP INDEX
 *   - DELETE / TRUNCATE / UPDATE without WHERE
 */

class MigrationManager {
    private PDO $pdo;
    private string $backupDir;
    private string $reportPath;
    private array $report = [
        'started_at' => '',
        'finished_at' => '',
        'status' => 'pending',
        'migrations_applied' => [],
        'tables_added' => [],
        'columns_added' => [],
        'indexes_added' => [],
        'errors' => [],
        'backup_path' => null,
    ];

    public function __construct(PDO $pdo, ?string $backupDir = null) {
        $this->pdo = $pdo;
        $this->backupDir = $backupDir ?? (dirname(__DIR__) . '/backups');
        $this->reportPath = __DIR__ . '/migration_report.md';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0777, true);
        }
    }

    // ========================================================================
    //  PUBLIC API
    // ========================================================================

    public function run(): array {
        $this->report['started_at'] = date('Y-m-d H:i:s');

        try {
            // 1. Ensure system_migrations table exists
            self::ensureMigrationTableColumns($this->pdo);

            // 2. Create backup
            $backupOk = $this->createBackup();
            if (!$backupOk) {
                throw new \RuntimeException('Backup failed — migration aborted to protect data.');
            }

            // 3. Load reference schema
            $reference = $this->loadReferenceSchema();

            // 4. Compare and build migration plan
            $plan = $this->buildMigrationPlan($reference);

            // 5. Execute plan
            $this->executePlan($plan);

            // 6. Finalize
            $this->report['finished_at'] = date('Y-m-d H:i:s');
            $this->report['status'] = 'completed';

        } catch (\Throwable $e) {
            $this->report['status'] = 'failed';
            $this->report['errors'][] = $e->getMessage();
        }

        // Write report
        $this->writeReport();

        return $this->report;
    }

    public function status(): array {
        self::ensureMigrationTableColumns($this->pdo);
        $st = $this->pdo->query("SELECT * FROM system_migrations ORDER BY applied_at DESC");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Compare current schema with reference
        $reference = $this->loadReferenceSchema();
        $diff = $this->compareSchemas($reference);

        return [
            'migrations_count' => count($rows),
            'migrations' => $rows,
            'pending_changes' => $diff,
        ];
    }

    public function generateReport(): string {
        $this->writeReport();
        return $this->reportPath;
    }

    // ========================================================================
    //  INTERNAL
    // ========================================================================

    private function createBackup(): bool {
        $backupFile = $this->backupDir . '/pre_migration_' . date('Ymd_His') . '.sql';
        
        $cmd = sprintf(
            '"%s" -u root --routines --triggers --single-transaction --skip-comments rental_system 2>nul',
            'C:\xampp\mysql\bin\mysqldump.exe'
        );

        $output = [];
        $exitCode = 0;
        $outputLine = exec($cmd . ' > "' . $backupFile . '"', $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($backupFile) || filesize($backupFile) < 100) {
            $this->report['errors'][] = "mysqldump failed (exit=$exitCode) or backup file too small";
            return false;
        }

        $this->report['backup_path'] = $backupFile;
        return true;
    }

    private function loadReferenceSchema(): array {
        $schemaFile = dirname(__DIR__) . '/migrations/reference_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException('Reference schema not found: ' . $schemaFile);
        }

        $sql = file_get_contents($schemaFile);
        return $this->parseCreateStatements($sql);
    }

    private function parseCreateStatements(string $sql): array {
        $schema = [];
        // Find each CREATE TABLE block, extract the body between outer () that precedes ENGINE=
        // Use a stateful approach to handle nested parentheses
        $lines = explode("\n", $sql);
        $currentTable = null;
        $currentBody = '';
        $parenDepth = 0;
        $inBody = false;

        foreach ($lines as $line) {
            // Detect CREATE TABLE start
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(/i', $line, $m)) {
                $currentTable = $m[1];
                $currentBody = '';
                $parenDepth = 0;
                $inBody = true;
                // Count opening parens in this line
                $parenDepth += substr_count($line, '(');
                $parenDepth -= substr_count($line, ')');
                // Extract the portion after the opening '('
                if ($parenDepth > 0) {
                    $pos = strpos($line, '(');
                    $currentBody = substr($line, $pos + 1) . "\n";
                }
                continue;
            }

            if ($inBody) {
                $parenDepth += substr_count($line, '(');
                $parenDepth -= substr_count($line, ')');
                
                if ($parenDepth <= 0 && preg_match('/\)\s*ENGINE\s*=/i', $line)) {
                    // End of table definition
                    $schema['tables'][$currentTable] = $this->parseTableDef($currentBody);
                    $currentTable = null;
                    $currentBody = '';
                    $inBody = false;
                } elseif ($currentBody !== null) {
                    $currentBody .= $line . "\n";
                }
            }
        }
        return $schema;
    }

    private function parseTableDef(string $def): array {
        $tableInfo = [
            'columns' => [],
            'indexes' => [],
            'foreign_keys' => [],
        ];

        // Extract column definitions (must start with backtick name + SQL data type)
        preg_match_all('/^\s*`(\w+)`\s+(\w+(?:\([^)]+\))?(?:\s+(?:unsigned|zerofill|NOT\s+NULL|NULL|DEFAULT\s+[^\s,]+|AUTO_INCREMENT|COMMENT\s+\'[^\']*\'))*)/im', $def, $cols, PREG_SET_ORDER);
        foreach ($cols as $c) {
            $tableInfo['columns'][$c[1]] = [
                'type' => $c[2],
                'nullable' => stripos($c[2], 'NOT NULL') === false,
                'default' => null,
            ];
        }

        // Extract indexes from table def (KEY/INDEX/UNIQUE/PRIMARY)
        preg_match_all('/(?:PRIMARY\s+KEY|KEY|INDEX|UNIQUE\s+KEY|UNIQUE\s+INDEX)\s+(?:`(\w+)`)?\s*\(([^)]+)\)/i', $def, $idxs, PREG_SET_ORDER);
        foreach ($idxs as $ix) {
            $name = $ix[1] ?: 'PRIMARY';
            $tableInfo['indexes'][$name] = $ix[2];
        }

        // Extract foreign keys
        preg_match_all('/CONSTRAINT\s+`(\w+)`\s+FOREIGN\s+KEY\s*\(`(\w+)`\)\s+REFERENCES\s+`(\w+)`\s*\(`(\w+)`\)/i', $def, $fks, PREG_SET_ORDER);
        foreach ($fks as $fk) {
            $tableInfo['foreign_keys'][] = [
                'constraint' => $fk[1],
                'column' => $fk[2],
                'ref_table' => $fk[3],
                'ref_column' => $fk[4],
            ];
        }

        return $tableInfo;
    }

    private function buildMigrationPlan(array $reference): array {
        $plan = [];

        // Get actual tables
        $st = $this->pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $actualTables = $st->fetchAll(PDO::FETCH_COLUMN);

        $refTables = $reference['tables'] ?? [];

        // 1. Tables to add
        foreach ($refTables as $tname => $tdef) {
            if (!in_array($tname, $actualTables, true)) {
                $createSql = $this->getCreateTableSql($tname);
                if ($createSql) {
                    // If already a full CREATE TABLE statement, add IF NOT EXISTS
                    if (stripos($createSql, 'CREATE TABLE') === 0) {
                        $createSql = preg_replace('/CREATE\s+TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createSql, 1);
                    } else {
                        $createSql = "CREATE TABLE IF NOT EXISTS `$tname` $createSql";
                    }
                    $plan[] = [
                        'type' => 'CREATE TABLE',
                        'target' => $tname,
                        'sql' => $createSql,
                    ];
                }
            }
        }

        // 2. Columns and indexes to add
        foreach ($refTables as $tname => $tdef) {
            if (!in_array($tname, $actualTables, true)) continue;

            $actualCols = $this->getActualColumns($tname);
            $actualIdxs = $this->getActualIndexes($tname);
            $actualFks = $this->getActualForeignKeys($tname);

            // Columns to add
            foreach ($tdef['columns'] as $cname => $cinfo) {
                if (!isset($actualCols[$cname])) {
                    $colDef = $this->getColumnDef($tname, $cname);
                    if ($colDef) {
                        $plan[] = [
                            'type' => 'ADD COLUMN',
                            'target' => "$tname.$cname",
                            'sql' => "ALTER TABLE `$tname` ADD COLUMN $colDef",
                        ];
                    }
                }
            }

            // Indexes to add
            foreach ($tdef['indexes'] as $iname => $icols) {
                if ($iname === 'PRIMARY') continue;
                if (!isset($actualIdxs[$iname])) {
                    $plan[] = [
                        'type' => 'ADD INDEX',
                        'target' => "$tname.$iname",
                        'sql' => "CREATE INDEX `$iname` ON `$tname` ($icols)",
                    ];
                }
            }

            // Foreign keys to add
            foreach ($tdef['foreign_keys'] as $fk) {
                $exists = false;
                foreach ($actualFks as $afk) {
                    $afkName = $afk['CONSTRAINT_NAME'] ?? $afk['constraint'] ?? '';
                    if ($afkName === $fk['constraint']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $plan[] = [
                        'type' => 'ADD FOREIGN KEY',
                        'target' => "$tname.{$fk['constraint']}",
                        'sql' => "ALTER TABLE `$tname` ADD CONSTRAINT `{$fk['constraint']}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['ref_table']}` (`{$fk['ref_column']}`)",
                    ];
                }
            }
        }

        // 3. Standalone indexes
        if (isset($reference['indexes_standalone'])) {
            foreach ($reference['indexes_standalone'] as $idx) {
                $actualIdxs = $this->getActualIndexes($idx['table']);
                if (!isset($actualIdxs[$idx['name']])) {
                    $plan[] = [
                        'type' => 'ADD INDEX',
                        'target' => "{$idx['table']}.{$idx['name']}",
                        'sql' => "CREATE INDEX `{$idx['name']}` ON `{$idx['table']}` ({$idx['columns']})",
                    ];
                }
            }
        }

        return $plan;
    }

    private function compareSchemas(array $reference): array {
        $st = $this->pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $tables = $st->fetchAll(PDO::FETCH_COLUMN);

        $diff = [
            'tables_missing' => [],
            'columns_missing' => [],
            'indexes_missing' => [],
            'up_to_date' => true,
        ];

        $refTables = $reference['tables'] ?? [];
        foreach ($refTables as $tname => $tdef) {
            if (!in_array($tname, $tables, true)) {
                $diff['tables_missing'][] = $tname;
                $diff['up_to_date'] = false;
                continue;
            }
            $actualCols = $this->getActualColumns($tname);
            $actualIdxs = $this->getActualIndexes($tname);

            foreach ($tdef['columns'] as $cname => $cinfo) {
                if (!isset($actualCols[$cname])) {
                    $diff['columns_missing'][] = "$tname.$cname";
                    $diff['up_to_date'] = false;
                }
            }
            foreach ($tdef['indexes'] as $iname => $icols) {
                if ($iname === 'PRIMARY') continue;
                if (!isset($actualIdxs[$iname])) {
                    $diff['indexes_missing'][] = "$tname.$iname";
                    $diff['up_to_date'] = false;
                }
            }
        }

        return $diff;
    }

    private function executePlan(array $plan): void {
        $version = date('Ymd_His');
        $checksum = md5(serialize($plan));
        $appliedBy = PHP_SAPI === 'cli' ? 'cli' : ($_SERVER['REMOTE_ADDR'] ?? 'api');
        $startTime = microtime(true);
        $details = [];

        // Mark migration as running
        $this->pdo->prepare("
            INSERT INTO system_migrations (version, description, checksum, applied_by, status, backup_file)
            VALUES (?, ?, ?, ?, 'running', ?)
        ")->execute([
            $version,
            'Auto-migration based on reference schema',
            $checksum,
            $appliedBy,
            $this->report['backup_path'],
        ]);

        try {
            foreach ($plan as $step) {
                try {
                    $stepStart = microtime(true);
                    $this->pdo->exec($step['sql']);
                    $stepTime = (int)((microtime(true) - $stepStart) * 1000);

                    $details[] = [
                        'type' => $step['type'],
                        'target' => $step['target'],
                        'status' => 'applied',
                        'time_ms' => $stepTime,
                    ];

                    // Track for report
                    switch ($step['type']) {
                        case 'CREATE TABLE':
                            $this->report['tables_added'][] = $step['target'];
                            break;
                        case 'ADD COLUMN':
                            $this->report['columns_added'][] = $step['target'];
                            break;
                        case 'ADD INDEX':
                        case 'ADD FOREIGN KEY':
                            $this->report['indexes_added'][] = $step['target'];
                            break;
                    }
                    $this->report['migrations_applied'][] = $step['target'];

                } catch (\PDOException $e) {
                    $details[] = [
                        'type' => $step['type'],
                        'target' => $step['target'],
                        'status' => 'skipped',
                        'error' => $e->getMessage(),
                    ];
                    // Don't abort — skip and continue
                }
            }

            $execTime = (int)((microtime(true) - $startTime) * 1000);

            // Update migration as completed
            $this->pdo->prepare("
                UPDATE system_migrations 
                SET status = 'completed', execution_time_ms = ?, details = ?, finished_at = NOW()
                WHERE version = ?
            ")->execute([$execTime, json_encode($details, JSON_UNESCAPED_UNICODE), $version]);

        } catch (\Throwable $e) {
            $execTime = (int)((microtime(true) - $startTime) * 1000);
            $this->pdo->prepare("
                UPDATE system_migrations 
                SET status = 'failed', execution_time_ms = ?, details = ?, finished_at = NOW()
                WHERE version = ?
            ")->execute([$execTime, json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE), $version]);
            throw $e;
        }
    }

    // ========================================================================
    //  INFORMATION SCHEMA HELPERS
    // ========================================================================

    private function getActualColumns(string $table): array {
        $st = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ");
        $st->execute([$table]);
        $cols = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[$row['COLUMN_NAME']] = $row;
        }
        return $cols;
    }

    private function getActualIndexes(string $table): array {
        $st = $this->pdo->prepare("
            SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLS
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
        ");
        $st->execute([$table]);
        $idxs = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idxs[$row['INDEX_NAME']] = $row['COLS'];
        }
        return $idxs;
    }

    private function getActualForeignKeys(string $table): array {
        $st = $this->pdo->prepare("
            SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $st->execute([$table]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCreateTableSql(string $table): ?string {
        // First try SHOW CREATE TABLE (table exists)
        try {
            $st = $this->pdo->prepare("SHOW CREATE TABLE `$table`");
            $st->execute();
            $row = $st->fetch(PDO::FETCH_NUM);
            if ($row && isset($row[1])) {
                return $row[1];
            }
        } catch (\PDOException $e) {
            // Table doesn't exist, try reference schema file
        }

        // Extract from reference_schema.sql
        $schemaFile = dirname(__DIR__) . '/migrations/reference_schema.sql';
        if (!file_exists($schemaFile)) return null;
        $sql = file_get_contents($schemaFile);
        $lines = explode("\n", $sql);
        $inTable = false;
        $createLines = [];
        foreach ($lines as $line) {
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`/i', $line)) {
                $inTable = true;
                $createLines[] = $line;
                continue;
            }
            if ($inTable) {
                $createLines[] = $line;
                if (preg_match('/\)\s*ENGINE\s*=/i', $line)) {
                    break;
                }
            }
        }
        if (!empty($createLines)) {
            return implode("\n", $createLines);
        }
        return null;
    }

    private function getColumnDef(string $table, string $column): ?string {
        // Look up the column definition from reference_schema.sql
        $schemaFile = dirname(__DIR__) . '/migrations/reference_schema.sql';
        if (!file_exists($schemaFile)) return null;
        $sql = file_get_contents($schemaFile);
        $lines = explode("\n", $sql);
        $inTable = false;
        foreach ($lines as $line) {
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`/i', $line)) {
                $inTable = true;
                continue;
            }
            if ($inTable) {
                $lineTrimmed = trim($line);
                // Match: `column_name` type(def) ... (NOT NULL|NULL) (DEFAULT ...) (AUTO_INCREMENT)
                if (preg_match('/^`' . preg_quote($column, '/') . '`\s+(.+?)(?:,\s*$|$)/i', $lineTrimmed, $m)) {
                    return '`' . $column . '` ' . trim($m[1]);
                }
                if (preg_match('/\)\s*ENGINE\s*=/i', $line)) {
                    break;
                }
            }
        }
        return null;
    }

    // ========================================================================
    //  REPORT
    // ========================================================================

    private function writeReport(): void {
        $r = $this->report;
        $lines = [];
        $lines[] = '# Database Migration Report';
        $lines[] = '';
        $lines[] = '**Date:** ' . ($r['started_at'] ?: 'N/A');
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '- **Status:** ' . $r['status'];
        $lines[] = '- **Started:** ' . ($r['started_at'] ?: '-');
        $lines[] = '- **Finished:** ' . ($r['finished_at'] ?: '-');
        $lines[] = '- **Backup:** ' . ($r['backup_path'] ?? 'None');
        $lines[] = '';
        $lines[] = '## Changes Applied';
        $lines[] = '';
        $lines[] = '### Tables Added (' . count($r['tables_added']) . ')';
        $lines[] = '';
        if (empty($r['tables_added'])) {
            $lines[] = '_None_';
        } else {
            foreach ($r['tables_added'] as $t) $lines[] = "- `$t`";
        }
        $lines[] = '';
        $lines[] = '### Columns Added (' . count($r['columns_added']) . ')';
        $lines[] = '';
        if (empty($r['columns_added'])) {
            $lines[] = '_None_';
        } else {
            foreach ($r['columns_added'] as $c) $lines[] = "- `$c`";
        }
        $lines[] = '';
        $lines[] = '### Indexes Added (' . count($r['indexes_added']) . ')';
        $lines[] = '';
        if (empty($r['indexes_added'])) {
            $lines[] = '_None_';
        } else {
            foreach ($r['indexes_added'] as $ix) $lines[] = "- `$ix`";
        }
        $lines[] = '';
        $lines[] = '## Errors';
        $lines[] = '';
        if (empty($r['errors'])) {
            $lines[] = '_None_';
        } else {
            foreach ($r['errors'] as $e) $lines[] = "- $e";
        }
        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'Report generated by MigrationManager at ' . date('Y-m-d H:i:s');

        file_put_contents($this->reportPath, implode("\n", $lines) . "\n");
    }

    // Helper to add finished_at column to system_migrations if needed
    public static function ensureMigrationTableColumns(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_migrations (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(64) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                checksum VARCHAR(64) NOT NULL,
                applied_by VARCHAR(100) DEFAULT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at DATETIME DEFAULT NULL,
                execution_time_ms INT NOT NULL DEFAULT 0,
                status ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
                backup_file VARCHAR(255) DEFAULT NULL,
                details JSON DEFAULT NULL,
                UNIQUE KEY idx_migrations_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}
