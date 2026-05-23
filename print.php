<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

function is_ymd($s) {
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

function build_date_filter($col, $from, $to, &$conds, &$params) {
  if ($from !== null && $from !== "") {
    if (!is_ymd($from)) respond(["error"=>"تاريخ البداية غير صالح. استخدم YYYY-MM-DD"], 400);
    $conds[]  = "$col >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to !== null && $to !== "") {
    if (!is_ymd($to)) respond(["error"=>"تاريخ النهاية غير صالح. استخدم YYYY-MM-DD"], 400);
    $conds[]  = "$col <= ?";
    $params[] = $to . " 23:59:59";
  }
}

/**
 * GET print/contract?id=RENT_ID
 * يرجع تفاصيل عقد التأجير + العناصر + دفعاته (payments)
 */
if ($path === "print/contract" && $method === "GET") {
  $id = (int)($_GET["id"] ?? 0);
  if ($id <= 0) respond(["error"=>"المعرف مطلوب"], 422);

  // 1) rent header
  $st = $pdo->prepare("
    SELECT
      r.id,
      r.client_id,
      c.name AS client_name,
      c.phone AS client_phone,
      r.start_at,
      r.expected_return_at,
      r.actual_return_at,
      r.deposit_amount,
      r.paid_amount,
      r.total_amount,
      r.status,
      r.notes,
      r.created_by,
      u.full_name AS created_by_name
    FROM rents r
    JOIN clients c ON c.id=r.client_id
    LEFT JOIN users u ON u.id=r.created_by
    WHERE r.id=?
    LIMIT 1
  ");
  $st->execute([$id]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);
  if (!$rent) respond(["error"=>"التأجير غير موجود"], 404);

  // 2) items (عدّل اسم الجدول لو مختلف عندك)
  // شائع: rent_items أو rent_equipment أو rent_lines
  $itemsSt = $pdo->prepare("
    SELECT
      ri.id,
      ri.equipment_id,
      e.name AS equipment_name,
      e.serial_no,
      ri.daily_rate,
      ri.hourly_rate,
      ri.rent_amount,
      ri.late_amount
    FROM rent_items ri
    JOIN equipment e ON e.id = ri.equipment_id
    WHERE ri.rent_id=?
    ORDER BY ri.id ASC
  ");
  $itemsSt->execute([$id]);
  $items = $itemsSt->fetchAll(PDO::FETCH_ASSOC);

  // 3) payments linked to this rent (عدّل لو عندك rent_id مختلف)
  $paySt = $pdo->prepare("
    SELECT id, type, method, amount, notes, created_at
    FROM payments
    WHERE rent_id=? AND is_void=0
    ORDER BY id ASC
  ");
  $paySt->execute([$id]);
  $payments = $paySt->fetchAll(PDO::FETCH_ASSOC);

  respond([
    "ok"=>true,
    "rent"=>$rent,
    "items"=>$items,
    "payments"=>$payments
  ], 200);
}


/**
 * GET print/client-statement?client_id=ID&from=YYYY-MM-DD&to=YYYY-MM-DD
 * يرجع كشف حساب العميل: العقود + السندات + الرصيد
 */
if ($path === "print/client-statement" && $method === "GET") {
  $clientId = (int)($_GET["client_id"] ?? 0);
  if ($clientId <= 0) respond(["error"=>"معرف العميل مطلوب"], 422);

  $from = $_GET["from"] ?? null;
  $to   = $_GET["to"] ?? null;

  $cst = $pdo->prepare("SELECT id, name, phone, national_id, address FROM clients WHERE id=?");
  $cst->execute([$clientId]);
  $client = $cst->fetch(PDO::FETCH_ASSOC);
  if (!$client) respond(["error"=>"العميل غير موجود"], 404);

  // rents
  $conds = ["r.client_id=?"];
  $params = [$clientId];
  build_date_filter("r.created_at", $from, $to, $conds, $params);
  $where = "WHERE " . implode(" AND ", $conds);

  $rSt = $pdo->prepare("
    SELECT id, start_at, expected_return_at, actual_return_at, total_amount, paid_amount, status, created_at
    FROM rents r
    $where
    ORDER BY r.id DESC
  ");
  $rSt->execute($params);
  $rents = $rSt->fetchAll(PDO::FETCH_ASSOC);

  // payments
  $pConds = ["p.client_id=?","p.is_void=0","p.type='in'"];
  $pParams = [$clientId];
  build_date_filter("p.created_at", $from, $to, $pConds, $pParams);
  $pWhere = "WHERE " . implode(" AND ", $pConds);

  $pSt = $pdo->prepare("
    SELECT id, amount, method, notes, created_at, rent_id
    FROM payments p
    $pWhere
    ORDER BY p.id DESC
  ");
  $pSt->execute($pParams);
  $payments = $pSt->fetchAll(PDO::FETCH_ASSOC);

  // totals
  $sumR = 0.0;
  foreach ($rents as $r) $sumR += (float)($r["total_amount"] ?? 0);

  $sumP = 0.0;
  foreach ($payments as $p) $sumP += (float)($p["amount"] ?? 0);

  $balance = $sumR - $sumP;

  respond([
    "ok"=>true,
    "filter"=>["from"=>$from,"to"=>$to],
    "client"=>$client,
    "totals"=>[
      "total_rent"=>$sumR,
      "total_paid"=>$sumP,
      "balance"=>$balance
    ],
    "rents"=>$rents,
    "payments"=>$payments
  ], 200);
}

respond(["error"=>"غير موجود"], 404);
