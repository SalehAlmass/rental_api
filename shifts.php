<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

date_default_timezone_set('Asia/Riyadh');

/**
 * Validate date format YYYY-MM-DD
 */
function is_ymd($s) {
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

function build_date_filter($col, $from, $to, &$conds, &$params) {
  if ($from !== null && $from !== "") {
    if (!is_ymd($from)) respond(["error" => "Invalid from date. Use YYYY-MM-DD"], 400);
    $conds[]  = "$col >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to !== null && $to !== "") {
    if (!is_ymd($to)) respond(["error" => "Invalid to date. Use YYYY-MM-DD"], 400);
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
  if ($uid <= 0) respond(["error" => "Unauthorized"], 401);

  $in = json_in();
  $shiftDate = (string)($in['shift_date'] ?? date('Y-m-d'));
  if (!is_ymd($shiftDate)) respond(["error" => "Invalid shift_date. Use YYYY-MM-DD"], 400);

  $cashTotal = (float)($in['cash_total'] ?? 0);
  $transferTotal = (float)($in['transfer_total'] ?? 0);
  $cashInDrawer = $in['cash_in_drawer'] ?? $in['actual_amount'] ?? null;
  if ($cashInDrawer === null || $cashInDrawer === '') {
    respond(["error" => "cash_in_drawer is required"], 422);
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
    INSERT INTO shift_closings (user_id, shift_date, expected_amount, actual_amount, difference, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      expected_amount=VALUES(expected_amount),
      actual_amount=VALUES(actual_amount),
      difference=VALUES(difference),
      notes=VALUES(notes)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$uid, $shiftDate, $expected, $actual, $diff, $finalNotes]);

  // fetch record
  $st2 = $pdo->prepare("SELECT id FROM shift_closings WHERE user_id=? AND shift_date=? LIMIT 1");
  $st2->execute([$uid, $shiftDate]);
  $id = (int)$st2->fetchColumn();

  respond([
    "success" => true,
    "data" => [
      "id" => $id,
      "user_id" => $uid,
      "shift_date" => $shiftDate,
      "expected_amount" => $expected,
      "actual_amount" => $actual,
      "difference" => $diff,
      "notes" => $finalNotes,
    ]
  ], 200);
}

respond(["error" => "Not Found"], 404);
