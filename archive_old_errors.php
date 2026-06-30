<?php
/**
 * Archive Old Resolved System Errors
 * 
 * Moves resolved errors older than 30 days to system_errors_archive
 * to keep the main error log lean while preserving audit history.
 * 
 * Usage:
 *   php archive_old_errors.php                       # dry-run (preview only)
 *   php archive_old_errors.php --execute             # actual archive
 *   php archive_old_errors.php --restore             # restore all archived errors
 */

declare(strict_types=1);

require_once __DIR__ . "/config.php";

$isExecute = in_array('--execute', $argv ?? [], true);
$isRestore = in_array('--restore', $argv ?? [], true);
$pdo = db();

echo "=== System Errors Archive Tool ===\n\n";

// Create archive table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS system_errors_archive LIKE system_errors");
echo "✅ Archive table ready\n\n";

if ($isRestore) {
    // Restore all archived errors back to main table
    $countSt = $pdo->query("SELECT COUNT(*) FROM system_errors_archive");
    $archivedCount = (int)$countSt->fetchColumn();
    
    if ($archivedCount === 0) {
        echo "ℹ️  No archived errors to restore.\n";
        exit(0);
    }
    
    echo "Will restore {$archivedCount} archived errors.\n";
    
    if ($isExecute) {
        // Restore with INSERT IGNORE to avoid ID collisions (IDs may conflict)
        $pdo->exec("INSERT IGNORE INTO system_errors SELECT * FROM system_errors_archive");
        $pdo->exec("TRUNCATE TABLE system_errors_archive");
        echo "✅ Restored {$archivedCount} errors from archive.\n";
    } else {
        echo "ℹ️  Dry-run. Use --execute to perform restore.\n";
    }
    exit(0);
}

// Find resolved errors older than 30 days
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
$st = $pdo->prepare("SELECT COUNT(*) FROM system_errors WHERE status = 'resolved' AND (resolved_at IS NOT NULL AND resolved_at < ?)");
$st->execute([$cutoff]);
$count = (int)$st->fetchColumn();

echo "Resolved errors older than 30 days (before {$cutoff}): {$count}\n\n";

if ($count === 0) {
    echo "ℹ️  No old resolved errors to archive.\n";
    exit(0);
}

if ($isExecute) {
    // Move to archive
    $st = $pdo->prepare("INSERT INTO system_errors_archive SELECT * FROM system_errors WHERE status = 'resolved' AND resolved_at IS NOT NULL AND resolved_at < ?");
    $st->execute([$cutoff]);
    $inserted = $st->rowCount();
    
    $st = $pdo->prepare("DELETE FROM system_errors WHERE status = 'resolved' AND resolved_at IS NOT NULL AND resolved_at < ?");
    $st->execute([$cutoff]);
    $deleted = $st->rowCount();
    
    echo "✅ Archived: {$inserted} errors moved, {$deleted} deleted from main table.\n";
    
    // Current counts
    $st = $pdo->query("SELECT status, COUNT(*) as cnt FROM system_errors GROUP BY status");
    echo "\n=== Current error status distribution ===\n";
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  {$row['status']}: {$row['cnt']}\n";
    }
    
    $st = $pdo->query("SELECT COUNT(*) FROM system_errors_archive");
    echo "  archived: {$st->fetchColumn()}\n";
} else {
    echo "ℹ️  Dry-run mode. Use --execute to perform the archive.\n";
    echo "   Preview of errors that will be archived:\n";
    
    $st = $pdo->prepare("SELECT id, api, error_message, resolved_at, created_at FROM system_errors WHERE status = 'resolved' AND resolved_at IS NOT NULL AND resolved_at < ? ORDER BY id ASC LIMIT 10");
    $st->execute([$cutoff]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "   #{$row['id']} [{$row['created_at']}] {$row['api']} — " . substr($row['error_message'], 0, 80) . "\n";
    }
    echo "   ... (showing max 10 preview rows)\n";
}

echo "\n=== Done ===\n";
