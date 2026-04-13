<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth = require_auth();
$pdo = db();
$path = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
ensure_financials_schema($pdo);

if ($path === "settings/contract-closing" && $method === "GET") {
  $userId = (int)($auth['sub'] ?? 0);
  $user = ['role' => (string)($auth['role'] ?? 'employee')];
  if ($userId > 0) {
    $st = $pdo->prepare("SELECT id, role, permissions_json FROM users WHERE id=? LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $user = $row;
  }
  respond(effective_contract_closing_modes($pdo, $user), 200);
}

if ($path === "settings/contract-closing" && $method === "PUT") {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['error' => 'Forbidden'], 403);
  }

  $in = json_in();
  if (!$in) $in = $_POST;

  $hourMode = strtolower(trim((string)($in['hour_pricing_mode'] ?? 'ask')));
  $receiptMode = strtolower(trim((string)($in['payment_receipt_mode'] ?? 'auto')));

  if (!in_array($hourMode, ['auto', 'ask'], true)) {
    respond(['error' => 'Invalid hour_pricing_mode'], 400);
  }
  if (!in_array($receiptMode, ['auto', 'ask'], true)) {
    respond(['error' => 'Invalid payment_receipt_mode'], 400);
  }

  setting_set($pdo, 'contract.hour_pricing_mode', $hourMode);
  setting_set($pdo, 'contract.payment_receipt_mode', $receiptMode);

  audit_log($pdo, 'contract_closing_settings_updated', 'settings', null, [
    'hour_pricing_mode' => $hourMode,
    'payment_receipt_mode' => $receiptMode,
  ]);

  respond([
    'success' => true,
    'data' => [
      'hour_pricing_mode' => $hourMode,
      'payment_receipt_mode' => $receiptMode,
    ],
  ], 200);
}


if ($path === "settings/admin-alerts" && $method === "GET") {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['error' => 'Forbidden'], 403);
  }

  $days = max(1, min(90, (int)($_GET['days'] ?? 14)));
  $limit = max(10, min(200, (int)($_GET['limit'] ?? 50)));

  $summary = [
    'contracts_without_receipt' => 0,
    'manual_hour_pricing' => 0,
    'shift_differences' => 0,
    'voided_payments' => 0,
    'needs_review_total' => 0,
  ];

  $st = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE status='closed' AND closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND COALESCE(closing_payment_status, 'not_created') <> 'created'");
  $st->execute([$days]);
  $summary['contracts_without_receipt'] = (int)$st->fetchColumn();

  $st = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE status='closed' AND closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND COALESCE(pricing_rule_applied,0)=1");
  $st->execute([$days]);
  $summary['manual_hour_pricing'] = (int)$st->fetchColumn();

  $st = $pdo->prepare("SELECT COUNT(*) FROM shift_closings WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND ABS(COALESCE(difference,0)) > 0.009");
  $st->execute([$days]);
  $summary['shift_differences'] = (int)$st->fetchColumn();

  $st = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND COALESCE(is_void,0)=1");
  $st->execute([$days]);
  $summary['voided_payments'] = (int)$st->fetchColumn();
  $summary['needs_review_total'] = array_sum($summary);

  $alerts = [];

  $sql = "SELECT r.id, r.closed_at, r.closing_paid_amount, r.closing_payment_method, r.closing_payment_status,
                 c.name AS client_name, e.name AS equipment_name, u.username
          FROM rents r
          LEFT JOIN clients c ON c.id = r.client_id
          LEFT JOIN equipment e ON e.id = r.equipment_id
          LEFT JOIN users u ON u.id = r.closed_by_user_id
          WHERE r.status='closed'
            AND r.closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND COALESCE(r.closing_payment_status, 'not_created') <> 'created'
          ORDER BY r.closed_at DESC
          LIMIT ?";
  $st = $pdo->prepare($sql);
  $st->bindValue(1, $days, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $alerts[] = [
      'severity' => 'high',
      'category' => 'receipt',
      'title' => 'عقد أُغلق بدون سند قبض',
      'subtitle' => 'العقد #' . (int)$row['id'] . ' - ' . ((string)($row['client_name'] ?? 'عميل غير معروف')),
      'details' => 'أغلقه ' . ((string)($row['username'] ?? 'مستخدم غير معروف')) . '، حالة السند: ' . ((string)($row['closing_payment_status'] ?? 'غير معروف')),
      'entity' => 'rent',
      'entity_id' => (int)$row['id'],
      'created_at' => (string)($row['closed_at'] ?? ''),
      'meta' => $row,
    ];
  }

  $sql = "SELECT r.id, r.closed_at, r.hours, r.total_amount, r.pricing_rule_label,
                 c.name AS client_name, e.name AS equipment_name, u.username
          FROM rents r
          LEFT JOIN clients c ON c.id = r.client_id
          LEFT JOIN equipment e ON e.id = r.equipment_id
          LEFT JOIN users u ON u.id = r.closed_by_user_id
          WHERE r.status='closed'
            AND r.closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND COALESCE(r.pricing_rule_applied,0)=1
          ORDER BY r.closed_at DESC
          LIMIT ?";
  $st = $pdo->prepare($sql);
  $st->bindValue(1, $days, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $alerts[] = [
      'severity' => 'medium',
      'category' => 'pricing',
      'title' => 'تم استخدام احتساب الساعات الخاص',
      'subtitle' => 'العقد #' . (int)$row['id'] . ' - ' . ((string)($row['client_name'] ?? 'عميل غير معروف')),
      'details' => ((string)($row['pricing_rule_label'] ?? 'احتساب خاص')) . ' بواسطة ' . ((string)($row['username'] ?? 'مستخدم غير معروف')),
      'entity' => 'rent',
      'entity_id' => (int)$row['id'],
      'created_at' => (string)($row['closed_at'] ?? ''),
      'meta' => $row,
    ];
  }

  $sql = "SELECT s.id, s.shift_date, s.created_at, s.expected_amount, s.actual_amount, s.difference, s.cash_total, s.transfer_total,
                 u.username
          FROM shift_closings s
          LEFT JOIN users u ON u.id = s.user_id
          WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND ABS(COALESCE(s.difference,0)) > 0.009
          ORDER BY s.created_at DESC
          LIMIT ?";
  $st = $pdo->prepare($sql);
  $st->bindValue(1, $days, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $sev = ((float)$row['difference'] < 0) ? 'high' : 'medium';
    $alerts[] = [
      'severity' => $sev,
      'category' => 'shift',
      'title' => ((float)$row['difference'] < 0) ? 'عجز في إغلاق الدوام' : 'زيادة في إغلاق الدوام',
      'subtitle' => 'إغلاق دوام ' . ((string)($row['shift_date'] ?? '')) . ' - ' . ((string)($row['username'] ?? 'مستخدم غير معروف')),
      'details' => 'الفرق: ' . number_format((float)$row['difference'], 2, '.', '') . ' | المتوقع: ' . number_format((float)$row['expected_amount'], 2, '.', ''),
      'entity' => 'shift_closing',
      'entity_id' => (int)$row['id'],
      'created_at' => (string)($row['created_at'] ?? ''),
      'meta' => $row,
    ];
  }

  $sql = "SELECT p.id, p.created_at, p.amount, p.type, p.notes, p.method, c.name AS client_name, u.username
          FROM payments p
          LEFT JOIN clients c ON c.id = p.client_id
          LEFT JOIN users u ON u.id = p.user_id
          WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND COALESCE(p.is_void,0)=1
          ORDER BY p.created_at DESC
          LIMIT ?";
  $st = $pdo->prepare($sql);
  $st->bindValue(1, $days, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $alerts[] = [
      'severity' => 'medium',
      'category' => 'payment',
      'title' => 'تم إلغاء سند',
      'subtitle' => 'السند #' . (int)$row['id'] . ' - ' . ((string)($row['client_name'] ?? 'بدون عميل')),
      'details' => 'ألغاه ' . ((string)($row['username'] ?? 'مستخدم غير معروف')) . ' | المبلغ: ' . number_format((float)$row['amount'], 2, '.', ''),
      'entity' => 'payment',
      'entity_id' => (int)$row['id'],
      'created_at' => (string)($row['created_at'] ?? ''),
      'meta' => $row,
    ];
  }

  usort($alerts, function($a, $b) {
    $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
    $ra = $rank[$a['severity']] ?? 0;
    $rb = $rank[$b['severity']] ?? 0;
    if ($ra !== $rb) return $rb <=> $ra;
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
  });
  $alerts = array_slice($alerts, 0, $limit);

  $st = $pdo->prepare("SELECT a.id, a.action, a.entity, a.entity_id, a.meta, a.created_at, u.username
                       FROM audit_logs a
                       LEFT JOIN users u ON u.id = a.user_id
                       WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                       ORDER BY a.created_at DESC
                       LIMIT ?");
  $st->bindValue(1, $days, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  $audit = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['id'] = (int)$row['id'];
    $row['entity_id'] = $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
    $decoded = null;
    if (!empty($row['meta'])) {
      $tmp = json_decode((string)$row['meta'], true);
      if (is_array($tmp)) $decoded = $tmp;
    }
    $row['meta'] = $decoded;
    $audit[] = $row;
  }

  respond([
    'success' => true,
    'data' => [
      'days' => $days,
      'summary' => $summary,
      'alerts' => $alerts,
      'audit' => $audit,
    ],
  ], 200);
}


if ($path === "settings/team-monitoring" && $method === "GET") {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['error' => 'Forbidden'], 403);
  }

  $days = max(1, min(90, (int)($_GET['days'] ?? 14)));
  $sort = strtolower(trim((string)($_GET['sort'] ?? 'score')));
  if (!in_array($sort, ['score', 'collections', 'followups', 'issues'], true)) {
    $sort = 'score';
  }

  $usersSt = $pdo->query("SELECT id, username, role, is_active FROM users WHERE COALESCE(is_active,1)=1 ORDER BY role DESC, username ASC");
  $rows = $usersSt->fetchAll(PDO::FETCH_ASSOC);
  $items = [];
  $summary = [
    'active_users' => 0,
    'total_collections' => 0,
    'total_followups' => 0,
    'total_issues' => 0,
  ];

  foreach ($rows as $u) {
    $uid = (int)($u['id'] ?? 0);
    if ($uid <= 0) continue;

    $closedRents = 0;
    $st = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE closed_by_user_id=? AND status='closed' AND closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $closedRents = (int)$st->fetchColumn();

    $manualPricingCount = 0;
    $st = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE closed_by_user_id=? AND status='closed' AND COALESCE(pricing_rule_applied,0)=1 AND closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $manualPricingCount = (int)$st->fetchColumn();

    $receiptSkippedCount = 0;
    $st = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE closed_by_user_id=? AND status='closed' AND COALESCE(closing_payment_status,'not_created') <> 'created' AND closed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $receiptSkippedCount = (int)$st->fetchColumn();

    $paymentCount = 0;
    $collectionTotal = 0.0;
    $voidedPayments = 0;
    $st = $pdo->prepare("SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(CASE WHEN COALESCE(is_void,0)=0 AND type='in' THEN amount ELSE 0 END),0) AS collection_total,
        COALESCE(SUM(CASE WHEN COALESCE(is_void,0)=1 THEN 1 ELSE 0 END),0) AS voided_count
      FROM payments
      WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $pay = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $paymentCount = (int)($pay['total_payments'] ?? 0);
    $collectionTotal = (float)($pay['collection_total'] ?? 0);
    $voidedPayments = (int)($pay['voided_count'] ?? 0);

    $followups = 0;
    $st = $pdo->prepare("SELECT COUNT(*) FROM collection_followups WHERE created_by_user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $followups = (int)$st->fetchColumn();

    $shiftClosings = 0;
    $shiftDifferences = 0;
    $st = $pdo->prepare("SELECT
        COUNT(*) AS total_closings,
        COALESCE(SUM(CASE WHEN ABS(COALESCE(difference,0)) > 0.009 THEN 1 ELSE 0 END),0) AS diff_count
      FROM shift_closings
      WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$uid, $days]);
    $shift = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $shiftClosings = (int)($shift['total_closings'] ?? 0);
    $shiftDifferences = (int)($shift['diff_count'] ?? 0);

    $issueCount = $manualPricingCount + $receiptSkippedCount + $voidedPayments + $shiftDifferences;

    $lastActivityAt = null;
    $st = $pdo->prepare("SELECT MAX(ts) FROM (
      SELECT MAX(closed_at) AS ts FROM rents WHERE closed_by_user_id=?
      UNION ALL
      SELECT MAX(created_at) AS ts FROM payments WHERE user_id=?
      UNION ALL
      SELECT MAX(created_at) AS ts FROM collection_followups WHERE created_by_user_id=?
      UNION ALL
      SELECT MAX(created_at) AS ts FROM shift_closings WHERE user_id=?
      UNION ALL
      SELECT MAX(created_at) AS ts FROM audit_logs WHERE user_id=?
    ) z");
    $st->execute([$uid, $uid, $uid, $uid, $uid]);
    $lastActivityAt = $st->fetchColumn() ?: null;

    $score = 50;
    $score += min(20, $closedRents * 2);
    $score += min(20, $followups * 2);
    $score += min(20, (int)floor($collectionTotal / 500));
    $score -= min(40, $issueCount * 8);
    if ($score < 0) $score = 0;
    if ($score > 100) $score = 100;

    $items[] = [
      'user_id' => $uid,
      'username' => (string)($u['username'] ?? 'مستخدم'),
      'role' => (string)($u['role'] ?? 'employee'),
      'closed_rents' => $closedRents,
      'payment_count' => $paymentCount,
      'collection_total' => round($collectionTotal, 2),
      'followups' => $followups,
      'shift_closings' => $shiftClosings,
      'shift_differences' => $shiftDifferences,
      'voided_payments' => $voidedPayments,
      'manual_pricing_count' => $manualPricingCount,
      'receipt_skipped_count' => $receiptSkippedCount,
      'issue_count' => $issueCount,
      'last_activity_at' => $lastActivityAt,
      'score' => $score,
    ];

    $summary['active_users']++;
    $summary['total_collections'] += $collectionTotal;
    $summary['total_followups'] += $followups;
    $summary['total_issues'] += $issueCount;
  }

  usort($items, function($a, $b) use ($sort) {
    if ($sort === 'collections') {
      return ($b['collection_total'] <=> $a['collection_total']) ?: strcmp((string)$a['username'], (string)$b['username']);
    }
    if ($sort === 'followups') {
      return ($b['followups'] <=> $a['followups']) ?: strcmp((string)$a['username'], (string)$b['username']);
    }
    if ($sort === 'issues') {
      return ($b['issue_count'] <=> $a['issue_count']) ?: strcmp((string)$a['username'], (string)$b['username']);
    }
    return ($b['score'] <=> $a['score']) ?: strcmp((string)$a['username'], (string)$b['username']);
  });

  $summary['total_collections'] = round((float)$summary['total_collections'], 2);

  respond([
    'success' => true,
    'data' => [
      'days' => $days,
      'sort' => $sort,
      'summary' => $summary,
      'users' => $items,
    ],
  ], 200);
}

if ($path === "settings/audit-log" && $method === "GET") {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['error' => 'Forbidden'], 403);
  }
  $days = max(1, min(180, (int)($_GET['days'] ?? 30)));
  $limit = max(10, min(500, (int)($_GET['limit'] ?? 100)));
  $action = trim((string)($_GET['action'] ?? ''));
  $conds = ["a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
  $params = [$days];
  if ($action !== '') {
    $conds[] = 'a.action = ?';
    $params[] = $action;
  }
  $where = 'WHERE ' . implode(' AND ', $conds);
  $sql = "SELECT a.id, a.action, a.entity, a.entity_id, a.meta, a.created_at, u.username
          FROM audit_logs a
          LEFT JOIN users u ON u.id = a.user_id
          $where
          ORDER BY a.created_at DESC
          LIMIT $limit";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['id'] = (int)$row['id'];
    $row['entity_id'] = $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
    $decoded = null;
    if (!empty($row['meta'])) {
      $tmp = json_decode((string)$row['meta'], true);
      if (is_array($tmp)) $decoded = $tmp;
    }
    $row['meta'] = $decoded;
    $rows[] = $row;
  }
  respond(['success' => true, 'data' => $rows], 200);
}

respond(['error' => 'Not Found'], 404);
