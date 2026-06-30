<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/helpers_financial.php";

// ✅ Require login
$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();
ensure_financials_schema($pdo);
ensure_depreciation_schema($pdo);
process_monthly_depreciation($pdo);

date_default_timezone_set('Asia/Riyadh');

/** Validate date format YYYY-MM-DD */
function is_ymd($s) {
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

/**
 * Add date filter on a DATETIME/TIMESTAMP column.
 * - from/to are YYYY-MM-DD.
 */
function build_date_filter($col, $from, $to, &$conds, &$params) {
  if ($from !== null && $from !== "") {
    if (!is_ymd($from)) respond(["error" => "تاريخ البداية غير صالح. استخدم YYYY-MM-DD"], 400);
    $conds[]  = "$col >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to !== null && $to !== "") {
    if (!is_ymd($to)) respond(["error" => "تاريخ النهاية غير صالح. استخدم YYYY-MM-DD"], 400);
    $conds[]  = "$col <= ?";
    $params[] = $to . " 23:59:59";
  }
}

/**
 * Normalize list response to {success, filter, data}
 */
function ok_list($filter, $data) {
  respond([
    "success" => true,
    "filter"  => $filter,
    "data"    => $data,
  ], 200);
}

// ---------------------------------------------------------
// Helper: build WHERE for payments report
// ---------------------------------------------------------
function _payments_where($from, $to, $type, $include_void, &$params) {
  $conds = [];
  if (!(int)$include_void) {
    $conds[] = "p.is_void = 0";
  }
  if ($type === 'in' || $type === 'out') {
    $conds[] = "p.type = ?";
    $params[] = $type;
  }
  build_date_filter("p.created_at", $from, $to, $conds, $params);
  return count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";
}

// ---------------------------------------------------------
// A) Dashboard
// GET reports/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD
// ---------------------------------------------------------
if ($path === "reports/dashboard" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;

  $clients   = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
  $equipment = (int)$pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();

  // open rents within range (created_at)
  $conds = ["r.status='open'"];
  $params = [];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $st = $pdo->prepare("SELECT COUNT(*) FROM rents r $where");
  $st->execute($params);
  $openRents = (int)$st->fetchColumn();

  // revenue: sum incoming payments within range
  $conds2 = ["p.type='in'", "p.is_void=0"]; // ignore void
  $params2 = [];
  build_date_filter("p.created_at", $from, $to, $conds2, $params2);
  $where2 = "WHERE " . implode(" AND ", $conds2);

  $st2 = $pdo->prepare("SELECT IFNULL(SUM(p.amount),0) FROM payments p $where2");
  $st2->execute($params2);
  $revenue = (float)$st2->fetchColumn();

  respond([
    "success" => true,
    "filter" => ["from" => $from, "to" => $to],
    "data" => [
      "clients"    => $clients,
      "equipment"  => $equipment,
      "open_rents" => $openRents,
      "revenue"    => $revenue,
    ],
  ], 200);
}

// ---------------------------------------------------------
// B) Equipment profit
// GET reports/equipment-profit?from&to
// profit = SUM(rents.total_amount) (closed)  within range
// cost   = SUM(equipment_maintenance.cost)   within range
// net    = profit - cost
// ---------------------------------------------------------
if ($path === "reports/equipment-profit" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;

  // profit by equipment from rents
  $conds = ["r.status='closed'", "r.total_amount IS NOT NULL"]; // closed only
  $params = [];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $sql = "
    SELECT
      e.id AS equipment_id,
      e.name,
      e.model AS type,
      e.serial_no,
      IFNULL(SUM(r.total_amount),0) AS profit
    FROM equipment e
    LEFT JOIN rents r ON r.equipment_id=e.id
    $where
    GROUP BY e.id, e.name, e.model, e.serial_no
    ORDER BY profit DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // costs by equipment from maintenance
  $condsM = [];
  $paramsM = [];
  build_date_filter("m.created_at", $from, $to, $condsM, $paramsM);
  $whereM = count($condsM) ? ("WHERE " . implode(" AND ", $condsM)) : "";

  $costSql = "
    SELECT equipment_id, IFNULL(SUM(cost),0) AS cost
    FROM equipment_maintenance m
    $whereM
    GROUP BY equipment_id
  ";
  $costSt = $pdo->prepare($costSql);
  $costSt->execute($paramsM);
  $costRows = $costSt->fetchAll(PDO::FETCH_ASSOC);

  $costMap = [];
  foreach ($costRows as $c) {
    $costMap[(int)$c['equipment_id']] = (float)$c['cost'];
  }

  $condsD = ["equipment_id IS NOT NULL"];
  $paramsD = [];
  build_date_filter("created_at", $from, $to, $condsD, $paramsD);
  $whereD = count($condsD) ? ("WHERE " . implode(" AND ", $condsD)) : "";
  $depSql = "SELECT equipment_id, IFNULL(SUM(accounting_amount),0) AS accounting_dep, IFNULL(SUM(operational_amount),0) AS operational_dep
             FROM equipment_depreciation_entries $whereD GROUP BY equipment_id";
  $depSt = $pdo->prepare($depSql);
  $depSt->execute($paramsD);
  $depRows = $depSt->fetchAll(PDO::FETCH_ASSOC);
  $depMap = [];
  foreach ($depRows as $d) {
    $depMap[(int)$d['equipment_id']] = [
      'accounting' => (float)$d['accounting_dep'],
      'operational' => (float)$d['operational_dep'],
    ];
  }

  $out = [];
  foreach ($rows as $r) {
    $eid = (int)$r['equipment_id'];
    $rentalRevenue  = (float)$r['profit'];
    $maintenanceCost = (float)($costMap[$eid] ?? 0);
    $accDep = (float)(($depMap[$eid]['accounting'] ?? 0));
    $opDep  = (float)(($depMap[$eid]['operational'] ?? 0));
    $netProfit = $rentalRevenue - $maintenanceCost - $accDep;
    $opNet     = $rentalRevenue - $maintenanceCost - $opDep;

    // Fetch purchase price + rental days for this equipment in the period
    $eqSt = $pdo->prepare("SELECT purchase_price, COALESCE(book_value,0) AS book_value FROM equipment WHERE id=? LIMIT 1");
    $eqSt->execute([$eid]);
    $eqRow = $eqSt->fetch(PDO::FETCH_ASSOC);
    $purchasePrice = (float)($eqRow['purchase_price'] ?? 0);

    // Count rental days in period
    $rdParams = [$eid];
    $rdConds  = ["equipment_id=?"];
    build_date_filter("created_at", $from, $to, $rdConds, $rdParams);
    $rdSt = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, start_datetime, IFNULL(closed_at, NOW()))/24),0) AS rental_days FROM rents WHERE " . implode(' AND ', $rdConds));
    $rdSt->execute($rdParams);
    $rentalDays = round((float)$rdSt->fetchColumn(), 1);

    $roi = ($purchasePrice > 0) ? round(($netProfit / $purchasePrice) * 100, 2) : null;

    $out[] = [
      "equipment_id"             => $eid,
      "name"                     => $r['name'],
      "type"                     => $r['type'],
      "serial_no"                => $r['serial_no'],
      "purchase_price"           => $purchasePrice,
      "rental_days"              => $rentalDays,
      "rental_revenue"           => round($rentalRevenue, 2),
      "maintenance_cost"         => round($maintenanceCost, 2),
      "accounting_depreciation"  => round($accDep, 2),
      "operational_depreciation" => round($opDep, 2),
      "net_profit"               => round($netProfit, 2),
      "operational_net"          => round($opNet, 2),
      "roi_pct"                  => $roi,
    ];
  }

  ok_list(["from"=>$from, "to"=>$to], $out);
}

// ---------------------------------------------------------
// C) Top equipment
// GET reports/top-equipment?from&to&limit=10
// ---------------------------------------------------------
if ($path === "reports/top-equipment" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

  $conds = [];
  $params = [];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $sql = "
    SELECT
      e.id AS equipment_id,
      e.name,
      e.model AS type,
      e.serial_no,
      COUNT(r.id) AS rentals_count,
      IFNULL(SUM(r.total_amount),0) AS total_income
    FROM equipment e
    LEFT JOIN rents r ON r.equipment_id=e.id
    $where
    GROUP BY e.id, e.name, e.model, e.serial_no
    ORDER BY rentals_count DESC, total_income DESC
    LIMIT $limit
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok_list(["from"=>$from, "to"=>$to, "limit"=>$limit], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ---------------------------------------------------------
// D) Top clients
// GET reports/top-clients?from&to&limit=10
// ---------------------------------------------------------
if ($path === "reports/top-clients" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

  $conds = [];
  $params = [];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $sql = "
    SELECT
      c.id AS client_id,
      c.name,
      c.phone,
      COUNT(r.id) AS contracts_count,
      IFNULL(SUM(r.total_amount),0) AS total_amount
    FROM clients c
    LEFT JOIN rents r ON r.client_id=c.id
    $where
    GROUP BY c.id, c.name, c.phone
    ORDER BY total_amount DESC, contracts_count DESC
    LIMIT $limit
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok_list(["from"=>$from, "to"=>$to, "limit"=>$limit], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ---------------------------------------------------------
// E) Late clients (approximation for this schema)
// GET reports/late-clients?from&to&limit=10
// NOTE: schema doesn't have expected_return_at, so we count "late" as:
//       open rents older than 24h from start_datetime.
// ---------------------------------------------------------
if ($path === "reports/late-clients" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

  $conds = ["r.status='open'", "TIMESTAMPDIFF(HOUR, r.start_datetime, NOW()) > 24"]; // late open
  $params = [];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $sql = "
    SELECT
      c.id AS client_id,
      c.name,
      c.phone,
      COUNT(r.id) AS late_contracts_count
    FROM clients c
    JOIN rents r ON r.client_id=c.id
    $where
    GROUP BY c.id, c.name, c.phone
    ORDER BY late_contracts_count DESC
    LIMIT $limit
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok_list(["from"=>$from, "to"=>$to, "limit"=>$limit], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ---------------------------------------------------------
// F) Revenue (day|month|year)
// GET reports/revenue?group=day|month|year&from&to
// Uses incoming payments (type='in', not void)
// ---------------------------------------------------------
if ($path === "reports/revenue" && $method === "GET") {

  $group = $_GET['group'] ?? 'day';
  if (!in_array($group, ['day','month','year'], true)) {
    respond(["error" => "مجموعة غير صالحة. استخدم day|month|year"], 400);
  }

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;

  $fmt = $group === 'day' ? '%Y-%m-%d' : ($group === 'month' ? '%Y-%m' : '%Y');

  $conds = ["p.type='in'", "p.is_void=0"]; // incoming only
  $params = [];
  build_date_filter("p.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $sql = "
    SELECT
      DATE_FORMAT(p.created_at, '$fmt') AS period,
      IFNULL(SUM(p.amount),0) AS revenue
    FROM payments p
    $where
    GROUP BY period
    ORDER BY period ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok_list(["from"=>$from, "to"=>$to, "group"=>$group], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ---------------------------------------------------------
// G) Revenue by user
// GET reports/revenue-by-user?from&to
// Uses incoming payments (type='in', not void)
// ---------------------------------------------------------
if ($path === "reports/revenue-by-user" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;

  $conds = ["p.type='in'", "p.is_void=0", "p.user_id IS NOT NULL"]; // must have user
  $params = [];
  build_date_filter("p.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $sql = "
    SELECT
      u.id AS user_id,
      u.username,
      u.username AS full_name,
      u.role,
      COUNT(p.id) AS receipts_count,
      IFNULL(SUM(p.amount),0) AS revenue
    FROM users u
    JOIN payments p ON p.user_id=u.id
    $where
    GROUP BY u.id, u.username, u.role
    ORDER BY revenue DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok_list(["from"=>$from, "to"=>$to], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ---------------------------------------------------------
// H) Payments report (used by Flutter 'السندات' tab)
// GET reports/payments?from=YYYY-MM-DD&to=YYYY-MM-DD&type=in|out|all&include_void=0|1
// ---------------------------------------------------------
if ($path === "reports/payments" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $type = $_GET['type'] ?? 'all';
  $include_void = isset($_GET['include_void']) ? (int)$_GET['include_void'] : 0;

  $params = [];
  $where = _payments_where($from, $to, $type, $include_void, $params);

  $sql = "
    SELECT
      p.id, p.type, p.amount, p.method, p.reference_no, p.notes, p.created_at,
      p.client_id, c.name AS client_name,
      p.rent_id, r.id AS rent_no,
      p.is_void, p.voided_at, p.void_reason
    FROM payments p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN rents   r ON p.rent_id = r.id
    $where
    ORDER BY p.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $sumSql = "
    SELECT
      IFNULL(SUM(CASE WHEN p.type='in'  THEN p.amount ELSE 0 END),0) AS total_in,
      IFNULL(SUM(CASE WHEN p.type='out' THEN p.amount ELSE 0 END),0) AS total_out
    FROM payments p
    $where
  ";
  $sumSt = $pdo->prepare($sumSql);
  $sumSt->execute($params);
  $sums = $sumSt->fetch(PDO::FETCH_ASSOC);

  respond([
    "success" => true,
    "filter" => [
      "from" => $from,
      "to" => $to,
      "type" => $type,
      "include_void" => $include_void,
    ],
    "totals" => [
      "in"  => (float)($sums['total_in'] ?? 0),
      "out" => (float)($sums['total_out'] ?? 0),
      "net" => (float)($sums['total_in'] ?? 0) - (float)($sums['total_out'] ?? 0),
    ],
    "data" => $rows,
  ], 200);
}

// ---------------------------------------------------------
// I) Payments report CSV (optional)
// GET reports/payments.csv?from=YYYY-MM-DD&to=YYYY-MM-DD&type=in|out|all&include_void=0|1
// ---------------------------------------------------------
if ($path === "reports/payments.csv" && $method === "GET") {

  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $type = $_GET['type'] ?? 'all';
  $include_void = isset($_GET['include_void']) ? (int)$_GET['include_void'] : 0;

  $params = [];
  $where = _payments_where($from, $to, $type, $include_void, $params);

  $sql = "
    SELECT
      p.id, p.created_at, p.type, p.amount, p.method,
      c.name AS client_name, r.id AS rent_no,
      p.reference_no, p.notes
    FROM payments p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN rents   r ON p.rent_id = r.id
    $where
    ORDER BY p.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="payments_report.csv"');

  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
  fputcsv($out, ['ID','Date','Type','Amount','Method','Client','Rent','Reference','Notes']);

  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
      $row['id'],
      $row['created_at'],
      $row['type'],
      $row['amount'],
      $row['method'],
      $row['client_name'],
      $row['rent_no'],
      $row['reference_no'],
      $row['notes'],
    ]);
  }
  fclose($out);
  exit;
}

// ------------------------------------------------------------
// Attendance report (Admin)
// GET reports/attendance?month=YYYY-MM&filter=all|present|absent|late
// Friday is holiday
// ------------------------------------------------------------
if ($path === 'reports/attendance' && $method === 'GET') {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['success'=>false, 'error'=>'ممنوع'], 403);
  }

  // Ensure tables exist (safe)
  $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('in','out') NOT NULL,
    ts DATETIME NOT NULL,
    method VARCHAR(20) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_ts (user_id, ts)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $month = trim((string)($_GET['month'] ?? ''));
  if ($month === '') $month = date('Y-m');
  $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));

  $from = date('Y-m-01 00:00:00', strtotime($month . '-01'));
  $to = date('Y-m-d 23:59:59', strtotime('last day of ' . $month));

  $WEEKLY_HOLIDAY_DOW = 5; // Friday
  $EXPECTED_IN = '08:00:00';
  $GRACE_MIN = 15;

  $isWorkday = function(int $ts) use ($WEEKLY_HOLIDAY_DOW): bool {
    return (int)date('w', $ts) !== $WEEKLY_HOLIDAY_DOW;
  };
  $expectedInTs = function(int $dayTs) use ($EXPECTED_IN): int {
    return strtotime(date('Y-m-d', $dayTs) . ' ' . $EXPECTED_IN);
  };

  $users = $pdo->query("SELECT id, username, role FROM users WHERE is_active=1 ORDER BY id ASC")->fetchAll();
  $items = [];
  foreach ($users as $u) {
    $uid = (int)$u['id'];

    // first in per day
    $st = $pdo->prepare("SELECT type, ts FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
    $st->execute([$uid, $from, $to]);
    $rows = $st->fetchAll();
    $firstIn = [];
    foreach ($rows as $r) {
      if (strtolower((string)$r['type']) !== 'in') continue;
      $ts = strtotime((string)$r['ts']);
      if (!$ts) continue;
      $day = date('Y-m-d', $ts);
      if (!isset($firstIn[$day]) || $ts < $firstIn[$day]) $firstIn[$day] = $ts;
    }

    $presentDays = 0;
    $absentDays = 0;
    $lateMinutes = 0;
    $workDays = 0;
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
      if (!$isWorkday($t)) continue;
      $workDays++;
      $day = date('Y-m-d', $t);
      if (!isset($firstIn[$day])) { $absentDays++; continue; }
      $presentDays++;
      $expected = $expectedInTs($t) + ($GRACE_MIN * 60);
      $actual = $firstIn[$day];
      if ($actual > $expected) $lateMinutes += (int)floor(($actual - $expected)/60);
    }

    $row = [
      'user_id' => $uid,
      'username' => $u['username'],
      'role' => $u['role'],
      'work_days' => $workDays,
      'present_days' => $presentDays,
      'absent_days' => $absentDays,
      'late_minutes' => $lateMinutes,
    ];

    $include = true;
    if ($filter === 'present') $include = ($presentDays > 0);
    elseif ($filter === 'absent') $include = ($absentDays > 0);
    elseif ($filter === 'late') $include = ($lateMinutes > 0);
    if ($include) $items[] = $row;
  }

  respond(['success'=>true, 'data'=>[
    'month' => $month,
    'from' => $from,
    'to' => $to,
    'weekly_holiday' => 'Friday',
    'items' => $items,
  ]]);
}

// ---------------------------------------------------------
// FINANCIAL REPORTS — Permission Guard Helper
// ---------------------------------------------------------
function require_financial_permission(PDO $pdo, array $auth): void {
  if (!has_permission($pdo, $auth, 'financial_reports')) {
    respond(['error' => 'ممنوع: ليس لديك صلاحية التقارير المالية (financial_reports)'], 403);
  }
}

// ---------------------------------------------------------
// J) Financial Summary
// GET reports/financial-summary?from_date=&to_date=&compare=true
// ---------------------------------------------------------
if ($path === 'reports/financial-summary' && $method === 'GET') {
  require_financial_permission($pdo, $auth);

  $from    = $_GET['from_date'] ?? ($_GET['from'] ?? null);
  $to      = $_GET['to_date']   ?? ($_GET['to']   ?? null);
  $compare = filter_var($_GET['compare'] ?? false, FILTER_VALIDATE_BOOLEAN);

  $rentalRev  = calculate_rental_revenue($pdo, $from, $to);
  $otherRev   = calculate_other_revenue($pdo, $from, $to);
  $totalRev   = $rentalRev + $otherRev;
  $expenses   = calculate_total_expenses($pdo, $from, $to);
  $grossProfit = $totalRev - $expenses['maintenance'] - $expenses['depreciation'];
  $opProfit    = $grossProfit - $expenses['payroll'];
  $netProfit   = $totalRev - $expenses['total'];

  $result = [
    'filter'   => ['from' => $from, 'to' => $to],
    'revenue'  => [
      'rental_revenue' => round($rentalRev, 2),
      'other_revenue'  => round($otherRev, 2),
      'total_revenue'  => round($totalRev, 2),
    ],
    'expenses' => $expenses,
    'results'  => [
      'gross_profit'     => round($grossProfit, 2),
      'operating_profit' => round($opProfit, 2),
      'net_profit'       => round($netProfit, 2),
    ],
    'kpis' => [
      'outstanding_amount' => calculate_outstanding_amount($pdo),
      'total_asset_value'  => calculate_total_asset_value($pdo),
      'profit_margin_pct'  => $totalRev > 0 ? round(($netProfit / $totalRev) * 100, 2) : 0,
    ],
  ];

  if ($compare) {
    // Calculate previous period of same length
    $fromTs   = $from ? strtotime($from) : strtotime(date('Y-m-01'));
    $toTs     = $to   ? strtotime($to)   : strtotime(date('Y-m-d'));
    $lenDays  = max(1, (int)(($toTs - $fromTs) / 86400));
    $prevFrom = date('Y-m-d', $fromTs - ($lenDays * 86400));
    $prevTo   = date('Y-m-d', $fromTs - 86400);

    $prevRev  = calculate_total_revenue($pdo, $prevFrom, $prevTo);
    $prevExp  = calculate_total_expenses($pdo, $prevFrom, $prevTo);
    $prevNet  = $prevRev - $prevExp['total'];
    $revGrowth = $prevRev > 0 ? round((($totalRev - $prevRev) / $prevRev) * 100, 2) : null;

    $result['comparison'] = [
      'period'           => ['from' => $prevFrom, 'to' => $prevTo],
      'total_revenue'    => round($prevRev, 2),
      'net_profit'       => round($prevNet, 2),
      'revenue_growth_pct' => $revGrowth,
    ];
  }

  respond(['success' => true, 'data' => $result]);
}

// ---------------------------------------------------------
// K) Profit & Loss Statement
// GET reports/profit-loss?from_date=&to_date=
// ---------------------------------------------------------
if ($path === 'reports/profit-loss' && $method === 'GET') {
  require_financial_permission($pdo, $auth);

  $from = $_GET['from_date'] ?? ($_GET['from'] ?? null);
  $to   = $_GET['to_date']   ?? ($_GET['to']   ?? null);

  $pl = calculate_profit_loss($pdo, $from, $to);

  respond([
    'success' => true,
    'filter'  => ['from' => $from, 'to' => $to],
    'data'    => $pl,
  ]);
}

// ---------------------------------------------------------
// L) Cash Flow
// GET reports/cash-flow?from_date=&to_date=
// ---------------------------------------------------------
if ($path === 'reports/cash-flow' && $method === 'GET') {
  require_financial_permission($pdo, $auth);

  $from = $_GET['from_date'] ?? ($_GET['from'] ?? null);
  $to   = $_GET['to_date']   ?? ($_GET['to']   ?? null);

  $cf = calculate_cash_flow($pdo, $from, $to);

  respond([
    'success' => true,
    'filter'  => ['from' => $from, 'to' => $to],
    'data'    => $cf,
  ]);
}

// ---------------------------------------------------------
// M) Employee Performance
// GET reports/employee-performance?from_date=&to_date=
// ---------------------------------------------------------
if ($path === 'reports/employee-performance' && $method === 'GET') {
  require_financial_permission($pdo, $auth);

  $from  = $_GET['from_date'] ?? ($_GET['from'] ?? null);
  $to    = $_GET['to_date']   ?? ($_GET['to']   ?? null);
  $role  = strtolower((string)($auth['role'] ?? ''));
  $myId  = (int)($auth['sub'] ?? $auth['uid'] ?? 0);

  $dw = fin_date_where('p.created_at', $from, $to);
  $payWhere = count($dw['conds'])
    ? ("AND p.is_void=0 AND p.type='in' AND " . implode(' AND ', $dw['conds']))
    : "AND p.is_void=0 AND p.type='in'";

  $userFilter = ($role !== 'admin' && !has_permission($pdo, $auth, 'hr')) ? 'AND u.id = ' . $myId : '';

  $sql = "SELECT
    u.id AS user_id,
    u.username,
    u.role,
    COUNT(DISTINCT p.id) AS receipts_count,
    COALESCE(SUM(p.amount),0) AS total_collected,
    0 AS contracts_created,
    0 AS total_contract_value
  FROM users u
  LEFT JOIN payments p ON p.user_id=u.id $payWhere
  WHERE 1=1 $userFilter
  GROUP BY u.id, u.username, u.role
  ORDER BY total_collected DESC";

  $params = $dw['params'];
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $collected = (float)$r['total_collected'];
    $receipts  = (int)$r['receipts_count'];
    $r['total_collected']     = round($collected, 2);
    $r['total_contract_value'] = round((float)$r['total_contract_value'], 2);
    $r['avg_transaction_value'] = $receipts > 0 ? round($collected / $receipts, 2) : 0.0;
    $r['receipts_count']      = $receipts;
    $r['contracts_created']   = (int)$r['contracts_created'];
  }

  respond(['success' => true, 'filter' => ['from' => $from, 'to' => $to], 'data' => $rows]);
}

// ---------------------------------------------------------
// N) Revenue by User Summary
// GET reports/revenue-by-user-summary?from_date=&to_date=
// ---------------------------------------------------------
if ($path === 'reports/revenue-by-user-summary' && $method === 'GET') {
  require_financial_permission($pdo, $auth);

  $from = $_GET['from_date'] ?? ($_GET['from'] ?? null);
  $to   = $_GET['to_date']   ?? ($_GET['to']   ?? null);

  $dw = fin_date_where('p.created_at', $from, $to);
  $conds = array_merge(["p.type='in'", "p.is_void=0", "p.user_id IS NOT NULL"], $dw['conds']);
  $where = 'WHERE ' . implode(' AND ', $conds);

  $sql = "SELECT
    u.id AS user_id,
    u.username,
    u.role,
    COALESCE(SUM(CASE WHEN p.method='cash' THEN p.amount ELSE 0 END),0)     AS cash_collected,
    COALESCE(SUM(CASE WHEN p.method='transfer' THEN p.amount ELSE 0 END),0) AS transfer_collected,
    COALESCE(SUM(p.amount),0) AS total_receipts,
    COUNT(p.id) AS transactions_count
  FROM users u
  JOIN payments p ON p.user_id=u.id
  $where
  GROUP BY u.id, u.username, u.role
  ORDER BY total_receipts DESC";

  $st = $pdo->prepare($sql);
  $st->execute($dw['params']);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $r['cash_collected']      = round((float)$r['cash_collected'], 2);
    $r['transfer_collected']  = round((float)$r['transfer_collected'], 2);
    $r['total_receipts']      = round((float)$r['total_receipts'], 2);
    $r['transactions_count']  = (int)$r['transactions_count'];
  }

  respond(['success' => true, 'filter' => ['from' => $from, 'to' => $to], 'data' => $rows]);
}

respond(["error" => "غير موجود"], 404);
