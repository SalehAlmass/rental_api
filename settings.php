<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

date_default_timezone_set('Asia/Riyadh');
ensure_financials_schema($pdo);

/*
|--------------------------------------------------------------------------
| GET settings/admin-alerts?days=7&limit=20
| Returns instant alerts for the admin dashboard
|--------------------------------------------------------------------------
*/
if ($path === "settings/admin-alerts" && $method === "GET") {
  $days  = max(1, min(90, (int)($_GET['days'] ?? 7)));
  $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
  $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

  $alerts = [];
  $summary = [
    'contracts_without_receipt' => 0,
  ];

  // 1. Audit log alerts (high/medium from audit_logs)
  try {
    $st = $pdo->prepare("
      SELECT al.*, u.username AS created_by_name
      FROM audit_logs al
      LEFT JOIN users u ON al.user_id = u.id
      WHERE al.created_at >= ?
      ORDER BY al.created_at DESC
      LIMIT ?
    ");
    $st->execute([$since, $limit * 2]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
      $action = strtolower($r['action'] ?? '');
      $severity = 'low';
      $message = '';

      if (str_contains($action, 'void') || str_contains($action, 'cancel')) {
        $severity = 'high';
        $message = 'تم إلغاء/حذف عملية بواسطة ' . ($r['created_by_name'] ?? 'مجهول');
      } elseif (str_contains($action, 'close')) {
        $severity = 'medium';
        $message = 'تم إغلاق عقد بواسطة ' . ($r['created_by_name'] ?? 'مجهول');
      } elseif (str_contains($action, 'discount')) {
        $severity = 'medium';
        $message = 'تم تطبيق خصم بواسطة ' . ($r['created_by_name'] ?? 'مجهول');
      } else {
        continue;
      }

      $meta = [];
      if (!empty($r['meta'])) {
        $meta = json_decode($r['meta'], true) ?: [];
      }

      $alerts[] = [
        'id' => (int)$r['id'],
        'severity' => $severity,
        'message' => $message,
        'entity' => $r['entity'],
        'entity_id' => $r['entity_id'] !== null ? (int)$r['entity_id'] : null,
        'action' => $r['action'],
        'created_at' => $r['created_at'],
        'user' => $r['created_by_name'],
        'meta' => $meta,
      ];
    }
  } catch (Throwable $e) {}

  // 2. Clients exceeding credit limit
  try {
    $st = $pdo->query("
      SELECT c.id, c.name, c.credit_limit, IFNULL(debt.total_debt, 0) AS total_debt
      FROM clients c
      JOIN (
        SELECT client_id, SUM(remaining_amount) AS total_debt
        FROM rents
        WHERE remaining_amount > 0
        GROUP BY client_id
      ) debt ON debt.client_id = c.id
      WHERE c.credit_limit > 0 AND debt.total_debt >= c.credit_limit
      ORDER BY debt.total_debt DESC
    ");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $alerts[] = [
        'id' => 0,
        'severity' => 'high',
        'message' => 'العميل ' . $row['name'] . ' تجاوز الحد الائتماني (' . number_format((float)$row['total_debt'], 0) . ' / ' . number_format((float)$row['credit_limit'], 0) . ')',
        'entity' => 'client',
        'entity_id' => (int)$row['id'],
        'action' => 'credit_limit_exceeded',
        'created_at' => date('Y-m-d H:i:s'),
        'user' => null,
        'meta' => ['total_debt' => (float)$row['total_debt'], 'credit_limit' => (float)$row['credit_limit']],
      ];
    }
  } catch (Throwable $e) {}

  // 3. Overdue open rents (open > 48 hours)
  try {
    $st = $pdo->query("
      SELECT r.id, r.client_id, c.name AS client_name, r.start_datetime,
             TIMESTAMPDIFF(HOUR, r.start_datetime, NOW()) AS hours_open
      FROM rents r
      JOIN clients c ON r.client_id = c.id
      WHERE r.status = 'open'
        AND TIMESTAMPDIFF(HOUR, r.start_datetime, NOW()) > 48
      ORDER BY hours_open DESC
      LIMIT 20
    ");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $days = intdiv((int)$row['hours_open'], 24);
      $alerts[] = [
        'id' => 0,
        'severity' => $days > 7 ? 'high' : 'medium',
        'message' => 'عقد #' . $row['id'] . ' مفتوح منذ ' . $days . ' يوم - ' . $row['client_name'],
        'entity' => 'rent',
        'entity_id' => (int)$row['id'],
        'action' => 'overdue_rent',
        'created_at' => $row['start_datetime'],
        'user' => null,
        'meta' => ['hours_open' => (int)$row['hours_open'], 'client_name' => $row['client_name']],
      ];
    }
  } catch (Throwable $e) {}

  // 4. Closed rents without receipt (no payment linked)
  try {
    $st = $pdo->query("
      SELECT COUNT(*) FROM rents r
      WHERE r.status = 'closed'
        AND r.total_amount > 0
        AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.rent_id = r.id AND p.type = 'in' AND (p.is_void = 0 OR p.is_void IS NULL))
    ");
    $summary['contracts_without_receipt'] = (int)$st->fetchColumn();
  } catch (Throwable $e) {}

  // Sort alerts by severity (high first)
  usort($alerts, function($a, $b) {
    $order = ['high' => 0, 'medium' => 1, 'low' => 2];
    return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
  });

  // Trim to limit
  $alerts = array_slice($alerts, 0, $limit);

  // 5. Audit logs for the "اخر سجل تدقيق" section
  $audit = [];
  try {
    $st = $pdo->prepare("
      SELECT al.*, u.username AS created_by_name
      FROM audit_logs al
      LEFT JOIN users u ON al.user_id = u.id
      ORDER BY al.created_at DESC
      LIMIT 30
    ");
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $meta = [];
      if (!empty($r['meta'])) {
        $meta = json_decode($r['meta'], true) ?: [];
      }
      $audit[] = [
        'id' => (int)$r['id'],
        'action' => $r['action'],
        'entity' => $r['entity'],
        'entity_id' => $r['entity_id'] !== null ? (int)$r['entity_id'] : null,
        'created_at' => $r['created_at'],
        'username' => $r['created_by_name'],
        'meta' => $meta,
      ];
    }
  } catch (Throwable $e) {}

  respond([
    "success" => true,
    "data" => [
      "alerts" => $alerts,
      "summary" => $summary,
      "audit" => $audit,
    ]
  ]);
}

/*
|--------------------------------------------------------------------------
| GET settings/contract-closing
| Returns contract closing policy settings
|--------------------------------------------------------------------------
*/
if ($path === "settings/contract-closing" && $method === "GET") {
  try {
    $st = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE 'closing_%' ORDER BY id");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $r) {
      $settings[$r['setting_key']] = $r['setting_value'];
    }
    respond(["success" => true, "data" => $settings]);
  } catch (Throwable $e) {
    respond(["success" => true, "data" => []]);
  }
}

if ($path === "settings/contract-closing" && $method === "PUT") {
  $in = json_in();
  try {
    // Ensure system_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      setting_key VARCHAR(128) NOT NULL UNIQUE,
      setting_value TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ($in as $key => $value) {
      if (!str_starts_with($key, 'closing_')) continue;
      $st = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
      $st->execute([$key, (string)$value]);
    }
    respond(["success" => true, "ok" => true]);
  } catch (Throwable $e) {
    respond(["error" => $e->getMessage()], 500);
  }
}

respond(["error" => "Not Found"], 404);
