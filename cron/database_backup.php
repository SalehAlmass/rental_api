<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/config.php";
require_once dirname(__DIR__) . "/helpers.php";

$pdo = db();
$backupDir = dirname(__DIR__) . "/backups";

if (!is_dir($backupDir)) {
  @mkdir($backupDir, 0777, true);
}

date_default_timezone_set('Asia/Riyadh');

$filename = "backup_full_cron_" . date("Y-m-d_H-i-s") . ".sql";
$fullpath = $backupDir . "/" . $filename;

echo "Starting automated cron database backup...\n";

try {
  if (!is_writable($backupDir)) {
    throw new Exception("Backup directory not writable");
  }

  $sql = dump_database($pdo, 'full');
  file_put_contents($fullpath, $sql);
  
  // Custom paths
  copy_backup_to_custom_paths($pdo, $fullpath, $filename);
  
  $fileSize = (int)filesize($fullpath);
  
  // Save log in DB
  $st = $pdo->prepare("INSERT INTO backup_logs (user_id, file_name, file_size, status) VALUES (NULL, ?, ?, 'success')");
  $st->execute([$filename, $fileSize]);
  $logId = (int)$pdo->lastInsertId();
  
  audit_log($pdo, 'backup_created', 'backup', $logId, null, null, [
    'file' => $filename,
    'size' => $fileSize,
    'trigger' => 'cron'
  ]);
  
  echo "Backup created successfully: {$filename} ({$fileSize} bytes)\n";
  
  // Apply Retention Policy:
  // - Daily backups (not on 1st of month) -> kept 30 days.
  // - Monthly backups (on 1st of month) -> kept 12 months.
  $allBackups = glob($backupDir . "/backup_*.sql") ?: [];
  $now = time();
  $thirtyDaysAgo = $now - (30 * 86400);
  $twelveMonthsAgo = $now - (365 * 86400);
  
  $deletedCount = 0;
  foreach ($allBackups as $f) {
    $mtime = filemtime($f);
    if ($mtime === false) continue;
    
    if ($mtime < $thirtyDaysAgo) {
      $dayOfMonth = date('d', $mtime);
      if ($dayOfMonth === '01') {
        if ($mtime < $twelveMonthsAgo) {
          if (@unlink($f)) $deletedCount++;
        }
      } else {
        if (@unlink($f)) $deletedCount++;
      }
    }
  }
  
  echo "Pruned {$deletedCount} old backups according to retention policy.\n";
  
} catch (Throwable $e) {
  $st = $pdo->prepare("INSERT INTO backup_logs (user_id, file_name, file_size, status) VALUES (NULL, ?, 0, 'failed')");
  $st->execute([$filename]);
  
  audit_log($pdo, 'backup_failed', 'backup', null, null, null, [
    'file' => $filename,
    'reason' => $e->getMessage(),
    'trigger' => 'cron'
  ]);
  
  echo "Cron backup failed: " . $e->getMessage() . "\n";
}
