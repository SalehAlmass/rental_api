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
| GET settings/team-monitoring
| Returns team performance metrics
|--------------------------------------------------------------------------
*/
if ($path === "settings/team-monitoring" && $method === "GET") {
  $days = max(1, min(90, (int)($_GET['days'] ?? 14)));
  $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

  $usersData = [];
  $summary = [
    'active_users' => 0,
    'total_collections' => 0,
    'total_followups' => 0,
    'total_issues' => 0,
  ];

  try {
    $stUsers = $pdo->query("SELECT id, username FROM users WHERE is_active = 1");
    $usersList = $stUsers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($usersList as $u) {
      $uid = (int)$u['id'];
      
      // collections
      $stColl = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id=? AND type='in' AND (is_void=0 OR is_void IS NULL) AND created_at >= ?");
      $stColl->execute([$uid, $since]);
      $collection_total = (float)$stColl->fetchColumn();
      
      // followups
      $stFup = $pdo->prepare("SELECT COUNT(*) FROM collection_followups WHERE created_by_user_id=? AND created_at >= ?");
      $stFup->execute([$uid, $since]);
      $followups = (int)$stFup->fetchColumn();

      // closed rents
      $stClosed = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE closed_by_user_id=? AND status='closed' AND closed_at >= ?");
      $stClosed->execute([$uid, $since]);
      $closed_rents = (int)$stClosed->fetchColumn();

      // manual pricing
      $stManual = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE closed_by_user_id=? AND status='closed' AND pricing_rule_applied=0 AND closed_at >= ?");
      $stManual->execute([$uid, $since]);
      $manual_pricing_count = (int)$stManual->fetchColumn();
      
      // voided payments
      $stVoid = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action LIKE '%void%' AND created_at >= ?");
      $stVoid->execute([$uid, $since]);
      $voided_payments = (int)$stVoid->fetchColumn();

      // shift closings
      $stShift = $pdo->prepare("SELECT COUNT(*) FROM shift_closings WHERE user_id=? AND created_at >= ?");
      $stShift->execute([$uid, $since]);
      $shift_closings = (int)$stShift->fetchColumn();

      // shift differences
      $stShiftDiff = $pdo->prepare("SELECT COUNT(*) FROM shift_closings WHERE user_id=? AND difference != 0 AND created_at >= ?");
      $stShiftDiff->execute([$uid, $since]);
      $shift_differences = (int)$stShiftDiff->fetchColumn();

      // receipt skipped
      $stSkip = $pdo->prepare("
        SELECT COUNT(*) FROM rents r 
        WHERE r.closed_by_user_id=? AND r.status='closed' AND r.closed_at >= ? AND r.total_amount > 0 
          AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.rent_id=r.id AND p.type='in' AND (p.is_void=0 OR p.is_void IS NULL))
      ");
      $stSkip->execute([$uid, $since]);
      $receipt_skipped_count = (int)$stSkip->fetchColumn();

      // Last activity
      $stAct = $pdo->prepare("SELECT MAX(created_at) FROM audit_logs WHERE user_id=?");
      $stAct->execute([$uid]);
      $last_activity_at = $stAct->fetchColumn();

      $issue_count = $voided_payments + $shift_differences + $receipt_skipped_count;
      
      $score = 80;
      if ($closed_rents > 0 || $collection_total > 0 || $followups > 0) {
          $score += min(20, ($collection_total / 1000) * 2);
          $score -= ($issue_count * 5);
      } else {
          $score = 50;
      }
      $score = max(0, min(100, $score));

      $usersData[] = [
        'id' => $uid,
        'username' => $u['username'],
        'collection_total' => $collection_total,
        'followups' => $followups,
        'closed_rents' => $closed_rents,
        'manual_pricing_count' => $manual_pricing_count,
        'voided_payments' => $voided_payments,
        'shift_closings' => $shift_closings,
        'shift_differences' => $shift_differences,
        'receipt_skipped_count' => $receipt_skipped_count,
        'last_activity_at' => $last_activity_at,
        'issue_count' => $issue_count,
        'score' => $score,
      ];

      $summary['total_collections'] += $collection_total;
      $summary['total_followups'] += $followups;
      $summary['total_issues'] += $issue_count;
    }

    $summary['active_users'] = count($usersData);

    $sort = $_GET['sort'] ?? 'score';
    usort($usersData, function($a, $b) use ($sort) {
      if ($sort === 'collections') return $b['collection_total'] <=> $a['collection_total'];
      if ($sort === 'followups') return $b['followups'] <=> $a['followups'];
      if ($sort === 'issues') return $b['issue_count'] <=> $a['issue_count'];
      return $b['score'] <=> $a['score'];
    });

  } catch (Throwable $e) {}

  respond([
    "success" => true,
    "data" => [
      "summary" => $summary,
      "users" => $usersData
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
  require_permission($pdo, $auth, 'settings');
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
  require_permission($pdo, $auth, 'settings');
  $in = json_in();
  try {
    // Ensure system_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      setting_key VARCHAR(128) NOT NULL UNIQUE,
      setting_value TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch existing settings before updates
    $stOld = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'closing_%'");
    $oldSettings = [];
    foreach ($stOld->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $oldSettings[$r['setting_key']] = $r['setting_value'];
    }

    $changedOld = [];
    $changedNew = [];

    foreach ($in as $key => $value) {
      if (!str_starts_with($key, 'closing_')) continue;
      $oldVal = $oldSettings[$key] ?? null;
      $newVal = (string)$value;
      if ($oldVal !== $newVal) {
        $changedOld[$key] = $oldVal;
        $changedNew[$key] = $newVal;
      }
      $st = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
      $st->execute([$key, $newVal]);
    }

    if (!empty($changedOld) || !empty($changedNew)) {
      audit_log($pdo, 'contract_closing_settings_updated', 'settings', null, $changedOld, $changedNew);
    }
    respond(["success" => true, "ok" => true]);
  } catch (Throwable $e) {
    respond(["error" => $e->getMessage()], 500);
  }
}

respond(["error" => "غير موجود"], 404);
