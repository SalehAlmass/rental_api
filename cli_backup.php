<?php
// cli_backup.php
// To be executed via: php cli_backup.php

// Ensure it's running via CLI
if (php_sapi_name() !== 'cli') {
  die("This script can only be run from the command line.\n");
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

// Set timezone
date_default_timezone_set('Asia/Riyadh');

$backupDir = __DIR__ . "/backups";
if (!is_dir($backupDir)) @mkdir($backupDir, 0777, true);

$pdo = db();
ensure_financials_schema($pdo);

$type = 'full';
$filename = "backup_{$type}_" . date("Y-m-d_H-i-s") . ".sql";
$fullpath = $backupDir . "/" . $filename;

try {
  echo "Generating database backup ({$type})...\n";
  $sql = dump_database($pdo, $type);
  file_put_contents($fullpath, $sql);
  echo "Backup saved to: {$fullpath}\n";

  echo "Copying backup to custom paths...\n";
  copy_backup_to_custom_paths($pdo, $fullpath, $filename);

  // Auto-prune: keep only latest 30
  $allBackups = glob($backupDir . "/backup_*.sql") ?: [];
  usort($allBackups, function($a, $b) { return filemtime($b) - filemtime($a); });
  $maxKeep = 30;
  if (count($allBackups) > $maxKeep) {
    $toDelete = array_slice($allBackups, $maxKeep);
    foreach ($toDelete as $old) {
      @unlink($old);
    }
    echo "Pruned " . count($toDelete) . " old backup(s).\n";
  }

  echo "Backup process finished successfully!\n";
} catch (Throwable $e) {
  echo "Error during backup: " . $e->getMessage() . "\n";
  exit(1);
}
