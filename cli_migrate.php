<?php
declare(strict_types=1);

/**
 * CLI Migration Tool
 * 
 * Usage:
 *   php cli_migrate.php              # Run pending migrations
 *   php cli_migrate.php --status     # Show migration status
 *   php cli_migrate.php --check      # Dry-run: show pending changes only
 *   php cli_migrate.php --report     # Generate report only
 *   php cli_migrate.php --help       # Show help
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/migrations/migration_manager.php';

$opts = getopt('', ['status', 'check', 'report', 'help']);
$pdo = db();

// Ensure system_migrations has all needed columns
MigrationManager::ensureMigrationTableColumns($pdo);

$manager = new MigrationManager($pdo);

if (isset($opts['help'])) {
    echo "Rental System — Database Migration Tool\n";
    echo "========================================\n\n";
    echo "Usage:\n";
    echo "  php cli_migrate.php              Run pending migrations\n";
    echo "  php cli_migrate.php --status     Show migration history & pending changes\n";
    echo "  php cli_migrate.php --check      Dry-run: show pending changes without executing\n";
    echo "  php cli_migrate.php --report     Generate migration_report.md\n";
    echo "  php cli_migrate.php --help       Show this help\n";
    exit(0);
}

if (isset($opts['status'])) {
    $status = $manager->status();
    echo "=== Migration Status ===\n\n";
    echo "Migrations applied: {$status['migrations_count']}\n\n";
    
    if (!empty($status['migrations'])) {
        echo str_pad("Version", 30) . str_pad("Status", 15) . str_pad("Time (ms)", 12) . "Applied At\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($status['migrations'] as $m) {
            echo str_pad($m['version'] ?? '-', 30);
            echo str_pad($m['status'] ?? '-', 15);
            echo str_pad((string)($m['execution_time_ms'] ?? 0), 12);
            echo ($m['applied_at'] ?? '-') . "\n";
        }
    }

    echo "\n--- Pending Changes ---\n";
    if ($status['pending_changes']['up_to_date']) {
        echo "Database is up to date.\n";
    } else {
        if (!empty($status['pending_changes']['tables_missing'])) {
            echo "\nTables to add:\n";
            foreach ($status['pending_changes']['tables_missing'] as $t) {
                echo "  + $t\n";
            }
        }
        if (!empty($status['pending_changes']['columns_missing'])) {
            echo "\nColumns to add:\n";
            foreach ($status['pending_changes']['columns_missing'] as $c) {
                echo "  + $c\n";
            }
        }
        if (!empty($status['pending_changes']['indexes_missing'])) {
            echo "\nIndexes to add:\n";
            foreach ($status['pending_changes']['indexes_missing'] as $ix) {
                echo "  + $ix\n";
            }
        }
    }
    exit(0);
}

if (isset($opts['check'])) {
    $status = $manager->status();
    if ($status['pending_changes']['up_to_date']) {
        echo "✓ Database is up to date with reference schema.\n";
        exit(0);
    }
    echo "Pending changes detected:\n";
    if (!empty($status['pending_changes']['tables_missing'])) {
        echo "\nTables to add:\n";
        foreach ($status['pending_changes']['tables_missing'] as $t) {
            echo "  + $t\n";
        }
    }
    if (!empty($status['pending_changes']['columns_missing'])) {
        echo "\nColumns to add:\n";
        foreach ($status['pending_changes']['columns_missing'] as $c) {
            echo "  + $c\n";
        }
    }
    if (!empty($status['pending_changes']['indexes_missing'])) {
        echo "\nIndexes to add:\n";
        foreach ($status['pending_changes']['indexes_missing'] as $ix) {
            echo "  + $ix\n";
        }
    }
    echo "\nRun without --check to apply.\n";
    exit(1);
}

if (isset($opts['report'])) {
    $path = $manager->generateReport();
    echo "Report generated: $path\n";
    exit(0);
}

// Default: run migrations
echo "Starting migration...\n";
$result = $manager->run();

echo "\n=== Migration Result ===\n";
echo "Status: {$result['status']}\n";
echo "Tables added: " . count($result['tables_added']) . "\n";
echo "Columns added: " . count($result['columns_added']) . "\n";
echo "Indexes added: " . count($result['indexes_added']) . "\n";
echo "Backup: " . ($result['backup_path'] ?? 'N/A') . "\n";

if (!empty($result['errors'])) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $e) {
        echo "  ! $e\n";
    }
    exit(1);
}

$reportPath = $manager->generateReport();
echo "Report: $reportPath\n";
echo "Done.\n";
exit(0);
