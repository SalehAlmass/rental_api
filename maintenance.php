<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth = require_auth();
$path = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo = db();

function maintenance_ok($data = null, int $code = 200): void {
  respond(["success" => true, "data" => $data], $code);
}

function maintenance_fail(string $msg, int $code = 400): void {
  respond(["success" => false, "error" => $msg], $code);
}

function maintenance_table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $st->execute([$table]);
  return ((int)$st->fetchColumn()) > 0;
}

// DELETE /maintenance/clear-business-data
// Clears operational/business data while keeping users/settings intact so the admin can still log in.
if ($path === 'maintenance/clear-business-data' && $method === 'DELETE') {
  if (($auth['role'] ?? '') !== 'admin') {
    maintenance_fail('Forbidden', 403);
  }

  $in = json_in();
  $confirm = trim((string)($in['confirm'] ?? $_GET['confirm'] ?? ''));
  if ($confirm !== 'CLEAR') {
    maintenance_fail('Confirmation required', 400);
  }

  $tables = [
    'audit_logs',
    'admin_alerts',
    'collection_followups',
    'attendance_logs',
    'payments',
    'rents',
    'equipment_maintenance',
    'equipment',
    'clients',
    'shifts',
    'payroll_runs',
    'payroll_items',
  ];

  $cleared = [];
  $pdo->beginTransaction();
  try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
      if (maintenance_table_exists($pdo, $table)) {
        $pdo->exec("TRUNCATE TABLE `$table`");
        $cleared[] = $table;
      }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();
    maintenance_ok(['cleared_tables' => $cleared]);
  } catch (Throwable $e) {
    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ignored) {}
    if ($pdo->inTransaction()) $pdo->rollBack();
    maintenance_fail('Failed to clear data: ' . $e->getMessage(), 500);
  }
}

maintenance_fail('Not Found', 404);
