<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

date_default_timezone_set('Asia/Riyadh');


function ensure_shift_closings_schema(PDO $pdo) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS shift_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    actual_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    transfer_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shift_user_date (user_id, shift_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $cols = $pdo->query("SHOW COLUMNS FROM shift_closings")->fetchAll(PDO::FETCH_COLUMN, 0);
  if (!in_array('cash_total', $cols, true)) {
    $pdo->exec("ALTER TABLE shift_closings ADD COLUMN cash_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER difference");
  }
  if (!in_array('transfer_total', $cols, true)) {
    $pdo->exec("ALTER TABLE shift_closings ADD COLUMN transfer_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_total");
  }
}

ensure_shift_closings_schema($pdo);

/**
 * Validate date format YYYY-MM-DD
 */
function is_ymd($s) {
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

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

/*
|--------------------------------------------------------------------------
| GET shifts?from=YYYY-MM-DD&to=YYYY-MM-DD
| - Admin: all shifts
| - Employee: only own shifts
|--------------------------------------------------------------------------
*/
if ($path === "shifts" && $method === "GET") {
  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;

  $conds = [];
  $params = [];

  // Filter by date (shift_date)
  if ($from !== null && $from !== "") {
    if (!is_ymd($from)) respond(["error" => "Invalid from date. Use YYYY-MM-DD"], 400);
    $conds[] = "s.shift_date >= ?";
    $params[] = $from;
  }
  if ($to !== null && $to !== "") {
    if (!is_ymd($to)) respond(["error" => "Invalid to date. Use YYYY-MM-DD"], 400);
    $conds[] = "s.shift_date <= ?";
    $params[] = $to;
  }

  // Permission scope
  if (($auth['role'] ?? '') !== 'admin') {
    $conds[] = "s.user_id = ?";
    $params[] = (int)($auth['sub'] ?? 0);
  }

  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $sql = "
    SELECT
      s.id,
      s.user_id,
      u.username,
      s.shift_date,
      s.expected_amount,
      s.actual_amount,
      s.difference,
      s.cash_total,
      s.transfer_total,
      s.notes,
      s.created_at
    FROM shift_closings s
    LEFT JOIN users u ON u.id = s.user_id
    $where
    ORDER BY s.shift_date DESC, s.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // cast
  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['user_id'] = (int)$r['user_id'];
    $r['expected_amount'] = (float)$r['expected_amount'];
    $r['actual_amount'] = (float)$r['actual_amount'];
    $r['difference'] = (float)$r['difference'];
    $r['cash_total'] = (float)($r['cash_total'] ?? 0);
    $r['transfer_total'] = (float)($r['transfer_total'] ?? 0);
  }

  respond(["success" => true, "data" => $rows], 200);
}

/*
|--------------------------------------------------------------------------
| POST shifts/close
| body:
| {
|   shift_date: YYYY-MM-DD (optional -> today)
|   cash_total: number (optional)
|   transfer_total: number (optional)
|   cash_in_drawer: number (required)
|   note: string (optional)
| }
|
| Stores into shift_closings:
| expected_amount = cash_total + transfer_total
| actual_amount   = cash_in_drawer
| difference      = actual - expected
| notes           = note + breakdown
|
| Unique per (user_id, shift_date) -> UPSERT
|--------------------------------------------------------------------------
*/
if ($path === "shifts/close" && $method === "POST") {
  $uid = (int)($auth['sub'] ?? 0);
  if ($uid <= 0) respond(["error" => "غير مصرح"], 401);

  $in = json_in();
  $shiftDate = (string)($in['shift_date'] ?? date('Y-m-d'));
  if (!is_ymd($shiftDate)) respond(["error" => "تاريخ الوردية غير صالح. استخدم YYYY-MM-DD"], 400);

  $cashTotal = (float)($in['cash_total'] ?? 0);
  $transferTotal = (float)($in['transfer_total'] ?? 0);
  $cashInDrawer = $in['cash_in_drawer'] ?? $in['actual_amount'] ?? null;
  if ($cashInDrawer === null || $cashInDrawer === '') {
    respond(["error" => "النقد في الدرج مطلوب"], 422);
  }
  $cashInDrawer = (float)$cashInDrawer;

  $expected = (float)($cashTotal + $transferTotal);
  $actual = (float)$cashInDrawer;
  $diff = (float)($actual - $expected);

  $note = trim((string)($in['note'] ?? $in['notes'] ?? ''));
  // Keep a lightweight breakdown in notes so UI can display it even with current DB schema
  $breakdown = "[cash_total={$cashTotal}, transfer_total={$transferTotal}]";
  $finalNotes = $note === '' ? $breakdown : ($note . " " . $breakdown);

  $sql = "
    INSERT INTO shift_closings (user_id, shift_date, expected_amount, actual_amount, difference, cash_total, transfer_total, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      expected_amount=VALUES(expected_amount),
      actual_amount=VALUES(actual_amount),
      difference=VALUES(difference),
      cash_total=VALUES(cash_total),
      transfer_total=VALUES(transfer_total),
      notes=VALUES(notes)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$uid, $shiftDate, $expected, $actual, $diff, $cashTotal, $transferTotal, $finalNotes]);

  // fetch record
  $st2 = $pdo->prepare("SELECT id FROM shift_closings WHERE user_id=? AND shift_date=? LIMIT 1");
  $st2->execute([$uid, $shiftDate]);
  $id = (int)$st2->fetchColumn();

  audit_log($pdo, 'shift_closed', 'shift_closing', $id, [
    'shift_date' => $shiftDate,
    'expected_amount' => $expected,
    'actual_amount' => $actual,
    'difference' => $diff,
    'cash_total' => $cashTotal,
    'transfer_total' => $transferTotal,
  ]);
  if (abs($diff) > 0.009) {
    audit_log($pdo, 'shift_difference_detected', 'shift_closing', $id, [
      'shift_date' => $shiftDate,
      'difference' => $diff,
      'expected_amount' => $expected,
      'actual_amount' => $actual,
    ]);
  }

  respond([
    "success" => true,
    "data" => [
      "id" => $id,
      "user_id" => $uid,
      "shift_date" => $shiftDate,
      "expected_amount" => $expected,
      "actual_amount" => $actual,
      "difference" => $diff,
      "cash_total" => $cashTotal,
      "transfer_total" => $transferTotal,
      "notes" => $finalNotes,
    ]
  ], 200);
}

respond(["error" => "غير موجود"], 404);
