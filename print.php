<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

require_permission($pdo, $auth, 'print');

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

/**
 * GET print/payments-report?from=YYYY-MM-DD&to=YYYY-MM-DD&type=in|out|all
 * Returns printable HTML of payments report containing all matching items
 */
if ($path === "print/payments-report" && $method === "GET") {
  $from = $_GET['from'] ?? null;
  $to   = $_GET['to'] ?? null;
  $type = $_GET['type'] ?? 'all';
  $include_void = isset($_GET['include_void']) ? (int)$_GET['include_void'] : 0;

  $params = [];
  $conds = [];
  if ($from) {
    $conds[] = "p.created_at >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to) {
    $conds[] = "p.created_at <= ?";
    $params[] = $to . " 23:59:59";
  }
  if ($type === 'in' || $type === 'out') {
    $conds[] = "p.type = ?";
    $params[] = $type;
  }
  if (!$include_void) {
    $conds[] = "p.is_void = 0";
  }
  
  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $sql = "
    SELECT
      p.id, p.type, p.amount, p.method, p.reference_no, p.notes, p.created_at,
      c.name AS client_name, r.id AS rent_no
    FROM payments p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN rents   r ON p.rent_id = r.id
    $where
    ORDER BY p.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $totalIn = 0.0;
  $totalOut = 0.0;
  foreach ($rows as $row) {
    if ($row['type'] === 'in') {
      $totalIn += (float)$row['amount'];
    } else {
      $totalOut += (float)$row['amount'];
    }
  }
  $net = $totalIn - $totalOut;

  header('Content-Type: text/html; charset=utf-8');
  ?>
  <!DOCTYPE html>
  <html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8">
    <title>تقرير السندات المالية</title>
    <style>
      body { font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; color: #333; }
      .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
      .header h1 { margin: 5px 0; font-size: 24px; }
      .header p { margin: 5px 0; font-size: 14px; color: #666; }
      .summary-boxes { display: flex; justify-content: space-between; margin-bottom: 20px; gap: 10px; }
      .summary-box { flex: 1; padding: 15px; border: 1px solid #ccc; border-radius: 6px; text-align: center; background: #fafafa; }
      .summary-box h3 { margin: 0 0 8px 0; font-size: 14px; color: #555; }
      .summary-box p { margin: 0; font-size: 20px; font-weight: bold; }
      .summary-box.in p { color: green; }
      .summary-box.out p { color: red; }
      .summary-box.net p { color: blue; }
      table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      th, td { border: 1px solid #ddd; padding: 10px; text-align: right; font-size: 13px; }
      th { background-color: #f2f2f2; font-weight: bold; }
      tr:nth-child(even) { background-color: #fafafa; }
      .badge { display: inline-block; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
      .badge.in { background-color: green; }
      .badge.out { background-color: red; }
      @media print {
        body { margin: 0; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body>
    <div class="no-print" style="margin-bottom: 15px; text-align: left;">
      <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px; font-weight: bold; cursor: pointer;">طباعة التقرير</button>
    </div>
    <div class="header">
      <h1>مؤسسة الخير لتأجير المعدات</h1>
      <p>تقرير السندات والتدفقات النقدية</p>
      <p>الفترة من: <?php echo htmlspecialchars($from ?? 'بداية النشاط'); ?> إلى: <?php echo htmlspecialchars($to ?? date('Y-m-d')); ?></p>
    </div>
    <div class="summary-boxes">
      <div class="summary-box in">
        <h3>إجمالي المقبوضات (سندات القبض)</h3>
        <p><?php echo number_format($totalIn, 2); ?> ر.س</p>
      </div>
      <div class="summary-box out">
        <h3>إجمالي المصروفات (سندات الصرف)</h3>
        <p><?php echo number_format($totalOut, 2); ?> ر.س</p>
      </div>
      <div class="summary-box net">
        <h3>صافي الدخل</h3>
        <p><?php echo number_format($net, 2); ?> ر.س</p>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>رقم السند</th>
          <th>تاريخ السند</th>
          <th>النوع</th>
          <th>العميل</th>
          <th>رقم العقد</th>
          <th>طريقة الدفع</th>
          <th>رقم المرجع</th>
          <th>المبلغ</th>
          <th>ملاحظات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="9" style="text-align: center; color: #888;">لا توجد سندات مالية مسجلة للفترة المحددة.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>#<?php echo $r['id']; ?></td>
              <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              <td>
                <span class="badge <?php echo $r['type']; ?>">
                  <?php echo $r['type'] === 'in' ? 'قبض' : 'صرف'; ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars($r['client_name'] ?? 'عام / غير محدد'); ?></td>
              <td><?php echo $r['rent_no'] ? '#'.$r['rent_no'] : '-'; ?></td>
              <td><?php echo htmlspecialchars($r['method']); ?></td>
              <td><?php echo htmlspecialchars($r['reference_no'] ?? '-'); ?></td>
              <td style="font-weight: bold; color: <?php echo $r['type'] === 'in' ? 'green' : 'red'; ?>;">
                <?php echo number_format((float)$r['amount'], 2); ?>
              </td>
              <td><?php echo htmlspecialchars($r['notes'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </body>
  </html>
  <?php
  exit;
}

respond(["error"=>"غير موجود"], 404);
