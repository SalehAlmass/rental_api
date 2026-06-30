<?php
require_once __DIR__ . "/config.php";
$pdo = db();

echo "=== Schema Migration: system_errors ===\n\n";

// Add status column (ENUM: open, resolved)
try {
    $pdo->exec("ALTER TABLE system_errors ADD COLUMN status ENUM('open','resolved') NOT NULL DEFAULT 'open' AFTER request_data");
    echo "✅ Added 'status' column\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "ℹ️ 'status' column already exists\n";
    } else {
        echo "❌ Failed to add 'status': " . $e->getMessage() . "\n";
    }
}

// Add resolved_at column
try {
    $pdo->exec("ALTER TABLE system_errors ADD COLUMN resolved_at DATETIME NULL AFTER status");
    echo "✅ Added 'resolved_at' column\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "ℹ️ 'resolved_at' column already exists\n";
    } else {
        echo "❌ Failed to add resolved_at: " . $e->getMessage() . "\n";
    }
}

// Mark ALL existing errors as resolved (Phase 9 fixed all root causes)
$st = $pdo->prepare("UPDATE system_errors SET status = 'resolved', resolved_at = NOW() WHERE status IS NULL OR status = 'open'");
$st->execute();
$count = $st->rowCount();
echo "✅ Marked {$count} existing errors as resolved\n\n";

// Verify
$st = $pdo->query("SELECT status, COUNT(*) as cnt FROM system_errors GROUP BY status");
echo "=== Current error status distribution ===\n";
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  {$row['status']}: {$row['cnt']}\n";
}

echo "\n=== Migration complete ===\n";
