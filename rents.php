<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$auth = require_auth();

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

ensure_financials_schema($pdo);

/* =========================================================
| Helpers
|========================================================= */

function ok($data = null, int $code = 200): void {
  respond(["success" => true, "data" => $data], $code);
}

function fail(string $msg, int $code = 400, $details = null): void {
  $out = ["success" => false, "error" => $msg];
  if ($details !== null) {
    $out["details"] = $details;
  }
  respond($out, $code);
}

function to_float($v): float {
  if (is_numeric($v)) return (float)$v;
  $x = trim((string)$v);
  return $x === '' ? 0.0 : (float)$x;
}

function calc_daily_pricing(float $dailyRate, float $hours): array {
  if ($hours <= 0) {
    return [
      'total' => 0.0,
      'pricing_rule_code' => 'no_duration',
      'pricing_rule_label' => 'لا توجد مدة محتسبة',
      'billable_days' => 0,
    ];
  }

  if ($hours < 3) {
    return [
      'total' => round($dailyRate * (2 / 3), 2),
      'pricing_rule_code' => 'less_than_3h_two_thirds_day',
      'pricing_rule_label' => 'أقل من 3 ساعات = ثلثي السعر اليومي',
      'billable_days' => 2 / 3,
    ];
  }

  $billableDays = (int)ceil($hours / 24.0);
  if ($billableDays < 1) $billableDays = 1;

  return [
    'total' => round($dailyRate * $billableDays, 2),
    'pricing_rule_code' => $billableDays === 1 ? 'full_day' : 'multi_day',
    'pricing_rule_label' => $billableDays === 1
      ? '3 ساعات فأكثر = يوم كامل'
      : ('احتساب ' . $billableDays . ' يوم × السعر اليومي'),
    'billable_days' => $billableDays,
  ];
}

function estimate_open_rent_total(array $row): float {
  $status = strtolower((string)($row['status'] ?? ''));
  $savedTotal = to_float($row['total_amount'] ?? 0);
  if ($savedTotal > 0) return $savedTotal;
  if ($status !== 'open') return 0.0;

  $dailyRate = to_float($row['rate'] ?? 0);
  if ($dailyRate <= 0) return 0.0;

  $startTs = strtotime((string)($row['start_datetime'] ?? ''));
  if (!$startTs) return 0.0;

  $seconds = time() - $startTs;
  if ($seconds <= 0) return 0.0;

  $hours = $seconds / 3600.0;
  $calc = calc_daily_pricing($dailyRate, $hours);
  return to_float($calc['total']);
}

function normalize_rent_row(array $row): array {
  $row['id'] = (int)($row['id'] ?? 0);
  $row['client_id'] = (int)($row['client_id'] ?? 0);
  $row['equipment_id'] = (int)($row['equipment_id'] ?? 0);

  $row['hours'] = isset($row['hours']) ? to_float($row['hours']) : 0.0;
  $row['rate'] = isset($row['rate']) ? to_float($row['rate']) : 0.0;
  $row['total_amount'] = isset($row['total_amount']) ? to_float($row['total_amount']) : 0.0;
  $row['paid_amount'] = isset($row['paid_amount']) ? to_float($row['paid_amount']) : 0.0;
  $row['remaining_amount'] = isset($row['remaining_amount']) ? to_float($row['remaining_amount']) : 0.0;
  $row['closing_paid_amount'] = isset($row['closing_paid_amount']) ? to_float($row['closing_paid_amount']) : 0.0;
  $row['discount_amount'] = isset($row['discount_amount']) ? to_float($row['discount_amount']) : 0.0;
  $row['discount_note'] = $row['discount_note'] ?? null;

  $row['is_paid'] = !empty($row['is_paid']) ? 1 : 0;
  $row['pricing_rule_applied'] = !empty($row['pricing_rule_applied']) ? 1 : 0;

  if ($row['total_amount'] <= 0 && strtolower((string)($row['status'] ?? '')) === 'open') {
    $row['total_amount'] = estimate_open_rent_total($row);
  }

  if ($row['remaining_amount'] <= 0 && $row['total_amount'] > 0) {
    $remaining = $row['total_amount'] - $row['paid_amount'];
    $row['remaining_amount'] = $remaining > 0 ? round($remaining, 2) : 0.0;
  }

  return $row;
}

function fetch_collection_followups_for_rent(PDO $pdo, int $rentId): array {
  $st = $pdo->prepare("
    SELECT f.*, u.name AS created_by_name
    FROM collection_followups f
    LEFT JOIN users u ON f.created_by_user_id = u.id
    WHERE f.rent_id = ?
    ORDER BY f.created_at DESC, f.id DESC
    LIMIT 50
  ");
  $st->execute([$rentId]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetch_client_latest_followup_today(PDO $pdo, int $clientId): ?array {
  $st = $pdo->prepare("
    SELECT f.*, u.name AS created_by_name
    FROM collection_followups f
    LEFT JOIN users u ON f.created_by_user_id = u.id
    WHERE f.client_id = ? AND DATE(f.created_at) = CURDATE()
    ORDER BY f.created_at DESC, f.id DESC
    LIMIT 1
  ");
  $st->execute([$clientId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function fetch_collection_agenda(PDO $pdo, string $sort = 'oldest'): array {
  if ($sort === 'largest') {
    $orderBy = 'remaining_amount DESC, overdue_since ASC, r.id DESC';
  } elseif ($sort === 'scheduled') {
    $orderBy = 'CASE WHEN latest.next_followup_at IS NULL THEN 1 ELSE 0 END ASC, latest.next_followup_at ASC, remaining_amount DESC, r.id DESC';
  } else {
    $orderBy = 'CASE WHEN latest.next_followup_at IS NULL THEN 1 ELSE 0 END ASC, overdue_since ASC, remaining_amount DESC, r.id DESC';
  }

  $sql = "
    SELECT
      r.id,
      r.client_id,
      r.equipment_id,
      r.start_datetime,
      r.end_datetime,
      r.status,
      r.total_amount,
      r.paid_amount,
      r.remaining_amount,
      r.rate,
      c.name AS client_name,
      e.name AS equipment_name,
      CASE
        WHEN LOWER(COALESCE(r.status, '')) = 'open' THEN r.start_datetime
        ELSE COALESCE(r.closed_at, r.end_datetime, r.start_datetime)
      END AS overdue_since,
      latest.contact_type AS latest_contact_type,
      latest.outcome AS latest_outcome,
      latest.note AS latest_note,
      latest.created_at AS latest_created_at,
      latest.next_followup_at AS latest_next_followup_at,
      latest.created_by_name AS latest_created_by_name,
      today.contact_type AS today_contact_type,
      today.outcome AS today_outcome,
      today.note AS today_note,
      today.created_at AS today_created_at,
      today.created_by_name AS today_created_by_name
    FROM rents r
    INNER JOIN clients c ON c.id = r.client_id
    INNER JOIN equipment e ON e.id = r.equipment_id
    LEFT JOIN (
      SELECT f.client_id, f.contact_type, f.outcome, f.note, f.created_at, f.next_followup_at, u.name AS created_by_name
      FROM collection_followups f
      INNER JOIN (
        SELECT client_id, MAX(id) AS max_id
        FROM collection_followups
        GROUP BY client_id
      ) x ON x.max_id = f.id
      LEFT JOIN users u ON u.id = f.created_by_user_id
    ) latest ON latest.client_id = r.client_id
    LEFT JOIN (
      SELECT f.client_id, f.contact_type, f.outcome, f.note, f.created_at, u.name AS created_by_name
      FROM collection_followups f
      INNER JOIN (
        SELECT client_id, MAX(id) AS max_id
        FROM collection_followups
        WHERE DATE(created_at) = CURDATE()
        GROUP BY client_id
      ) y ON y.max_id = f.id
      LEFT JOIN users u ON u.id = f.created_by_user_id
    ) today ON today.client_id = r.client_id
    WHERE (
      (LOWER(COALESCE(r.status, '')) = 'open' AND TIMESTAMPDIFF(HOUR, r.start_datetime, NOW()) >= 24)
      OR
      (LOWER(COALESCE(r.status, '')) = 'closed' AND COALESCE(r.remaining_amount, GREATEST(COALESCE(r.total_amount,0) - COALESCE(r.paid_amount,0),0)) > 0.009)
    )
    ORDER BY $orderBy
    LIMIT 100
  ";

  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as &$row) {
    $row = normalize_rent_row($row);
  }
  return $rows;
}

function recalc_rent_financials(PDO $pdo, int $rentId): array {
  $st = $pdo->prepare("
    SELECT id, status, total_amount, paid_at, discount_amount
    FROM rents
    WHERE id = ?
    FOR UPDATE
  ");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) {
    return [];
  }

  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE rent_id = ?
      AND (is_void = 0 OR is_void IS NULL)
      AND type = 'in'
  ");
  $st2->execute([$rentId]);
  $paid = to_float($st2->fetchColumn());

  $total = to_float($rent['total_amount'] ?? 0);
  $discount = to_float($rent['discount_amount'] ?? 0);
  $remaining = max($total - $discount - $paid, 0.0);

  $status = strtolower((string)($rent['status'] ?? ''));
  $isPaid = ($status !== 'open') && ($remaining <= 0.0001);

  $paidAt = $rent['paid_at'];
  if ($isPaid && empty($paidAt)) {
    $paidAt = date('Y-m-d H:i:s');
  }
  if (!$isPaid) {
    $paidAt = null;
  }

  $upd = $pdo->prepare("
    UPDATE rents
    SET paid_amount = ?, remaining_amount = ?, is_paid = ?, paid_at = ?
    WHERE id = ?
  ");
  $upd->execute([$paid, $remaining, $isPaid ? 1 : 0, $paidAt, $rentId]);

  return [
    'paid_amount' => $paid,
    'remaining_amount' => $remaining,
    'is_paid' => $isPaid,
    'paid_at' => $paidAt,
    'total_amount' => $total,
  ];
}

function attach_rent_items(PDO $pdo, array &$rents) {
  if (empty($rents)) return;
  $ids = array_column($rents, 'id');
  $in  = str_repeat('?,', count($ids) - 1) . '?';
  $st = $pdo->prepare("SELECT ri.*, e.name AS equipment_name, e.serial_no
                       FROM rent_items ri 
                       JOIN equipment e ON ri.equipment_id = e.id 
                       WHERE ri.rent_id IN ($in)");
  $st->execute($ids);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);
  $grouped = [];
  foreach ($items as $it) {
    $grouped[$it['rent_id']][] = $it;
  }
  foreach ($rents as &$r) {
    $r['items'] = $grouped[$r['id']] ?? [];
  }
}

/* =========================================================
| GET /rents
|========================================================= */
if ($path === "rents" && $method === "GET") {
  $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
  $status   = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
  $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

  $conds = [];
  $params = [];

  if ($clientId > 0) {
    $conds[] = 'r.client_id = ?';
    $params[] = $clientId;
  }

  if ($status !== '' && in_array($status, ['open', 'closed', 'cancelled'], true)) {
    $conds[] = 'r.status = ?';
    $params[] = $status;
  }

  $where = count($conds) ? ('WHERE ' . implode(' AND ', $conds)) : '';
  $limitSql = ($limit > 0 && $limit <= 200) ? ('LIMIT ' . $limit) : '';

  $sql = "SELECT r.*,
                 c.name AS client_name,
                 e.name AS equipment_name
          FROM rents r
          JOIN clients c ON r.client_id = c.id
          LEFT JOIN equipment e ON r.equipment_id = e.id
          $where
          ORDER BY r.id DESC
          $limitSql";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as &$row) {
    $row = normalize_rent_row($row);
  }
  attach_rent_items($pdo, $rows);

  ok($rows);
}

/* =========================================================
| GET /rents/{id}
|========================================================= */
if (preg_match('#^rents/(\d+)$#', $path, $m) && $method === "GET") {
  $id = (int)$m[1];

  $st = $pdo->prepare("
    SELECT r.*,
           c.name AS client_name,
           c.phone AS client_phone,
           e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    LEFT JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) fail("عقد الإيجار غير موجود", 404);

  $row = normalize_rent_row($row);
  $rows = [$row];
  attach_rent_items($pdo, $rows);
  ok($rows[0]);
}

/* =========================================================
| GET /rents/{id}/financials
|========================================================= */
if (preg_match('#^rents/(\d+)/financials$#', $path, $m) && $method === "GET") {
  $rentId = (int)$m[1];

  $st = $pdo->prepare("
    SELECT r.*,
           c.name AS client_name,
           c.phone AS client_phone,
           e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    LEFT JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) fail("عقد الإيجار غير موجود", 404);

  $rent = normalize_rent_row($rent);
  $rows = [$rent];
  attach_rent_items($pdo, $rows);
  $rent = $rows[0];

  $total = to_float($rent["total_amount"] ?? 0);

  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS paid
    FROM payments
    WHERE rent_id = ?
      AND LOWER(type) = 'in'
      AND (is_void IS NULL OR is_void = 0)
  ");
  $st2->execute([$rentId]);
  $paid = to_float(($st2->fetch(PDO::FETCH_ASSOC)["paid"] ?? 0));
  $remaining = max(0.0, $total - $paid);

  $status = strtolower((string)($rent["status"] ?? ""));
  $isOpen = ($status === 'open');
  $isFullyPaid = $isOpen ? false : ($remaining <= 0.0001);

  $rent["paid_amount"] = $paid;
  $rent["remaining_amount"] = $remaining;

  ok([
    "rent" => $rent,
    "total_amount" => $total,
    "paid_amount" => $paid,
    "remaining" => $remaining,
    "remaining_amount" => $remaining,
    "is_fully_paid" => $isFullyPaid,
  ]);
}

/* =========================================================
| GET /rents/collection-agenda
|========================================================= */
if ($path === "rents/collection-agenda" && $method === "GET") {
  $sort = strtolower(trim((string)($_GET['sort'] ?? 'oldest')));
  if (!in_array($sort, ['oldest', 'largest', 'scheduled'], true)) {
    $sort = 'oldest';
  }
  ok(fetch_collection_agenda($pdo, $sort));
}

/* =========================================================
| GET /rents/{id}/collection-followups
|========================================================= */
if (preg_match('#^rents/(\d+)/collection-followups$#', $path, $m) && $method === "GET") {
  $rentId = (int)$m[1];

  $st = $pdo->prepare("SELECT id FROM rents WHERE id = ? LIMIT 1");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) {
    fail("عقد الإيجار غير موجود", 404);
  }

  ok(fetch_collection_followups_for_rent($pdo, $rentId));
}

/* =========================================================
| POST /rents/{id}/collection-followups
|========================================================= */
if (preg_match('#^rents/(\d+)/collection-followups$#', $path, $m) && $method === "POST") {
  $rentId = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $st = $pdo->prepare("SELECT id, client_id, status FROM rents WHERE id = ? LIMIT 1");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) {
    fail("عقد الإيجار غير موجود", 404);
  }

  $clientId = (int)($rent['client_id'] ?? 0);
  $contactType = strtolower(trim((string)($in['contact_type'] ?? '')));
  $outcome = strtolower(trim((string)($in['outcome'] ?? '')));
  $note = trim((string)($in['note'] ?? ''));
  $nextFollowupAt = trim((string)($in['next_followup_at'] ?? ''));
  $createdBy = (int)($auth['sub'] ?? 0);

  if (!in_array($contactType, ['call', 'whatsapp', 'visit', 'verbal', 'no_answer'], true)) {
    fail("نوع التواصل غير صالح", 400);
  }

  if ($outcome !== '' && !in_array($outcome, ['promise_to_pay', 'follow_up_later', 'paid', 'customer_requested_delay', 'no_answer', 'other'], true)) {
    fail("النتيجة غير صالحة", 400);
  }

  if ($note === '' && $outcome === '') {
    fail("يجب إدخال ملاحظة أو نتيجة المتابعة", 400);
  }

  $allowDuplicateToday = !empty($in['allow_duplicate_today']);
  $todayFollowup = fetch_client_latest_followup_today($pdo, $clientId);
  if ($todayFollowup && !$allowDuplicateToday) {
    fail('تم تسجيل تواصل لهذا العميل اليوم بالفعل', 409, [
      'existing_followup' => $todayFollowup,
      'can_override' => true,
    ]);
  }

  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare("
      INSERT INTO collection_followups
      (rent_id, client_id, contact_type, outcome, note, next_followup_at, created_by_user_id)
      VALUES (?,?,?,?,?,?,?)
    ");
    $ins->execute([
      $rentId,
      $clientId,
      $contactType,
      $outcome !== '' ? $outcome : null,
      $note !== '' ? $note : null,
      $nextFollowupAt !== '' ? date('Y-m-d H:i:s', strtotime($nextFollowupAt)) : null,
      $createdBy > 0 ? $createdBy : null,
    ]);

    $newId = (int)$pdo->lastInsertId();

    audit_log($pdo, 'collection_followup_created', 'rent', $rentId, [
      'followup_id' => $newId,
      'client_id' => $clientId,
      'contact_type' => $contactType,
      'outcome' => $outcome !== '' ? $outcome : null,
      'note' => $note !== '' ? $note : null,
      'next_followup_at' => $nextFollowupAt !== '' ? date('Y-m-d H:i:s', strtotime($nextFollowupAt)) : null,
    ]);

    $pdo->commit();
    ok(['id' => $newId], 201);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail('فشل في حفظ متابعة التحصيل', 500, $e->getMessage());
  }
}

/* =========================================================
| POST /rents
|========================================================= */
if ($path === "rents" && $method === "POST") {
  $in = json_in();
  if (!$in) $in = $_POST;

  $client_id = (int)($in["client_id"] ?? 0);
  $start     = trim((string)($in["start_datetime"] ?? ""));
  $end       = trim((string)($in["end_datetime"] ?? ""));
  $rentNotes = $in["notes"] ?? null;

  $items = $in["items"] ?? [];
  if (empty($items) && !empty($in["equipment_id"])) {
    $items = [
      [
        "equipment_id" => (int)$in["equipment_id"],
        "rate" => $in["rate"] ?? null,
        "notes" => $in["equipment_notes"] ?? null
      ]
    ];
  }

  if ($client_id <= 0 || empty($items) || $start === "") {
    fail("حقول مطلوبة مفقودة (client_id, items, start_datetime)", 400);
  }

  $pdo->beginTransaction();
  try {
    $firstEqId = (int)($items[0]["equipment_id"] ?? 0);
    $st = $pdo->prepare("
      INSERT INTO rents (client_id, equipment_id, start_datetime, end_datetime, notes, status)
      VALUES (?,?,?,?,?,?)
    ");
    $st->execute([
      $client_id,
      $firstEqId, // Keep for backward compatibility
      $start,
      $end !== "" ? $end : null,
      $rentNotes,
      "open",
    ]);

    $rentId = (int)$pdo->lastInsertId();

    $stEq = $pdo->prepare("SELECT id, hourly_rate, status FROM equipment WHERE id=? FOR UPDATE");
    $updEq = $pdo->prepare("UPDATE equipment SET status='rented' WHERE id=?");
    $stItem = $pdo->prepare("
      INSERT INTO rent_items (rent_id, equipment_id, rate, notes, status, start_datetime, end_datetime)
      VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($items as $it) {
      $eqId = (int)($it["equipment_id"] ?? 0);
      if ($eqId <= 0) continue;

      $stEq->execute([$eqId]);
      $eq = $stEq->fetch();

      if (!$eq) {
        $pdo->rollBack();
        fail("المعدة غير موجودة (ID: $eqId)", 404);
      }

      if (strtolower((string)$eq["status"]) === "rented") {
        $pdo->rollBack();
        fail("المعدة مؤجرة بالفعل (ID: $eqId)", 409);
      }

      $rate = array_key_exists("rate", $it) && $it["rate"] !== null
              ? to_float($it["rate"]) 
              : to_float($eq["hourly_rate"]);

      $stItem->execute([
        $rentId,
        $eqId,
        $rate,
        $it["notes"] ?? null,
        "open",
        $start,
        $end !== "" ? $end : null
      ]);

      $updEq->execute([$eqId]);
    }

    $pdo->commit();
    ok(["id" => $rentId], 201);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("خطأ في الخادم", 500, $e->getMessage());
  }
}

/* =========================================================
| PUT /rents/{id}
|========================================================= */
if (preg_match('#^rents/(\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $st = $pdo->prepare("SELECT id, status FROM rents WHERE id = ?");
  $st->execute([$id]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) {
    fail("عقد الإيجار غير موجود", 404);
  }

  if (strtolower((string)$rent["status"]) !== "open") {
    fail("يمكن تعديل العقود المفتوحة فقط", 409);
  }

  if (!array_key_exists("notes", $in)) {
    fail("الملاحظات مطلوبة", 400);
  }

  $notes = $in["notes"];
  $upd = $pdo->prepare("UPDATE rents SET notes = ? WHERE id = ?");
  $upd->execute([$notes, $id]);

  ok(["ok" => true]);
}

/* =========================================================
| POST /rents/{id}/close
|========================================================= */
if (preg_match('#^rents/(\d+)/close$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $end = trim((string)($in["end_datetime"] ?? ""));
  if ($end === "") {
    fail("تاريخ النهاية مطلوب", 400);
  }

  $applySpecialPricing = !empty($in['apply_special_pricing']);
  $paidAmount = to_float($in['paid_amount'] ?? 0);
  $discountAmount = max(0.0, to_float($in['discount_amount'] ?? 0));
  $discountNote = trim((string)($in['discount_note'] ?? ''));
  $paymentMethod = trim((string)($in['payment_method'] ?? 'cash'));
  $paymentNotes = trim((string)($in['payment_notes'] ?? ''));
  $createReceipt = !empty($in['create_receipt']) && $paidAmount > 0;
  $closingAt = date('Y-m-d H:i:s');
  $closedBy = (int)($auth['sub'] ?? 0);

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT * FROM rents WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch(PDO::FETCH_ASSOC);

    if (!$rent) {
      $pdo->rollBack();
      fail("عقد الإيجار غير موجود", 404);
    }

    if (strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("العقد ليس مفتوحاً", 409);
    }

    $end_ts   = strtotime($end);
    $start_ts = strtotime((string)$rent["start_datetime"]);

    if (!$end_ts) {
      $pdo->rollBack();
      fail("تاريخ النهاية غير صالح", 400);
    }

    $stItems = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id=?");
    $stItems->execute([$id]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);

    $grossTotal = 0.0;
    $updItem = $pdo->prepare("UPDATE rent_items SET end_datetime=?, status='closed' WHERE id=?");
    $updEq = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");

    if (empty($items)) {
      if ($start_ts && $end_ts > $start_ts) {
        $hrs = ($end_ts - $start_ts) / 3600;
        $calcItem = calc_daily_pricing(to_float($rent["rate"] ?? 0), $hrs);
        $grossTotal = to_float($calcItem['total']);
      }
      $eqid = (int)$rent["equipment_id"];
      if ($eqid > 0) $updEq->execute([$eqid]);
    } else {
      foreach ($items as $it) {
        $itemEnd = $end;
        if ($it['status'] === 'open') {
          $updItem->execute([$end, $it['id']]);
          $updEq->execute([$it['equipment_id']]);
        } else {
          $itemEnd = $it['end_datetime'] ?? $end;
        }
        
        $iStart_ts = strtotime((string)$it['start_datetime']);
        $iEnd_ts = strtotime($itemEnd);
        if ($iStart_ts && $iEnd_ts && $iEnd_ts > $iStart_ts) {
          $hrs = ($iEnd_ts - $iStart_ts) / 3600;
          $calcItem = calc_daily_pricing(to_float($it['rate']), $hrs);
          $grossTotal += to_float($calcItem['total']);
        }
      }
    }

    if ($discountAmount > $grossTotal) {
      $discountAmount = $grossTotal;
    }
    $total = max($grossTotal - $discountAmount, 0.0);

    $paidBeforeSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE rent_id=? AND type='in' AND (is_void=0 OR is_void IS NULL)");
    $paidBeforeSt->execute([$id]);
    $paidBefore = to_float($paidBeforeSt->fetchColumn());
    $requiredNow = max($total - $paidBefore, 0.0);
    
    if ($paymentMethod !== 'deferred' && $paidAmount - $requiredNow > 0.009) {
      $pdo->rollBack();
      fail('المبلغ المدفوع أكبر من المطلوب لهذا العقد', 400, [
        'required_amount' => $requiredNow,
        'paid_before' => $paidBefore,
        'total_after_discount' => $total,
      ]);
    }

    $hours = 0.0;
    if ($start_ts && $end_ts > $start_ts) {
      $hours = round(($end_ts - $start_ts) / 3600, 2);
    }

    // Default calculations for the dashboard logs
    $pricingCode = 'daily_default';
    $pricingLabel = 'الاحتساب التلقائي لجميع البنود';

    $paymentId = null;
    $paymentStatus = $createReceipt ? 'created' : 'not_created';

    $upd = $pdo->prepare("
      UPDATE rents
      SET end_datetime = ?, hours = ?, total_amount = ?, status = 'closed',
          closed_at = ?, closed_by_user_id = ?,
          closing_paid_amount = ?, closing_payment_method = ?, closing_payment_status = ?, closing_payment_id = ?,
          pricing_rule_code = ?, pricing_rule_label = ?, pricing_rule_applied = ?,
          discount_amount = ?, discount_note = ?
      WHERE id = ?
    ");
    $upd->execute([
      $end,
      $hours,
      $total,
      $closingAt,
      $closedBy > 0 ? $closedBy : null,
      $paidAmount,
      $paymentMethod !== '' ? $paymentMethod : null,
      $paymentStatus,
      null,
      $pricingCode,
      $pricingLabel,
      $applySpecialPricing ? 1 : 0,
      $discountAmount,
      $discountNote !== '' ? $discountNote : null,
      $id,
    ]);

    if ($createReceipt) {
      $idem = trim((string)($in['idempotency_key'] ?? ('rent_close_' . $id)));
      $pay = $pdo->prepare("
        INSERT INTO payments (type, amount, client_id, rent_id, method, notes, user_id, idempotency_key, created_at)
        VALUES ('in', ?, ?, ?, ?, ?, ?, ?, NOW())
      ");
      $pay->execute([
        $paidAmount,
        (int)$rent['client_id'],
        $id,
        $paymentMethod !== '' ? $paymentMethod : 'cash',
        $paymentNotes !== '' ? $paymentNotes : 'سند قبض تلقائي عند إغلاق العقد',
        $closedBy > 0 ? $closedBy : null,
        $idem,
      ]);

      $paymentId = (int)$pdo->lastInsertId();
      $paymentStatus = 'created';

      $pdo->prepare("UPDATE rents SET closing_payment_status = ?, closing_payment_id = ? WHERE id = ?")
          ->execute([$paymentStatus, $paymentId, $id]);
    }

    $fin = recalc_rent_financials($pdo, $id);

    if (!$createReceipt) {
      audit_log($pdo, 'receipt_skipped_on_close', 'rent', $id, [
        'closing_paid_amount' => $paidAmount,
        'closing_payment_method' => $paymentMethod,
        'closed_at' => $closingAt,
      ]);
    }

    audit_log($pdo, 'rent_closed', 'rent', $id, [
      'total_amount' => $total,
      'gross_total_amount' => $grossTotal,
      'discount_amount' => $discountAmount,
      'discount_note' => $discountNote !== '' ? $discountNote : null,
      'hours' => $hours,
      'apply_special_pricing' => $applySpecialPricing,
      'pricing_rule_code' => $pricingCode,
      'closing_paid_amount' => $paidAmount,
      'closing_payment_method' => $paymentMethod,
      'closing_payment_status' => $paymentStatus,
      'closing_payment_id' => $paymentId,
      'closed_at' => $closingAt,
      'closed_by_user_id' => $closedBy,
    ]);

    $pdo->commit();

    ok([
      "ok" => true,
      "total_amount" => $total,
      "gross_total_amount" => $grossTotal,
      "discount_amount" => $discountAmount,
      "discount_note" => $discountNote !== '' ? $discountNote : null,
      "hours" => $hours,
      "payment_id" => $paymentId,
      "closing_payment_status" => $paymentStatus,
      "pricing_rule_code" => $pricingCode,
      "pricing_rule_label" => $pricingLabel,
      "paid_amount" => $fin['paid_amount'] ?? 0,
      "remaining_amount" => $fin['remaining_amount'] ?? max($total - $paidAmount, 0),
      "is_paid" => $fin['is_paid'] ?? false,
    ]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("خطأ في الخادم", 500, $e->getMessage());
  }
}

/* =========================================================
| POST /rents/{id}/cancel
|========================================================= */
if (preg_match('#^rents/(\d+)/cancel$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $reason = $in["reason"] ?? null;

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT * FROM rents WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch(PDO::FETCH_ASSOC);

    if (!$rent) {
      $pdo->rollBack();
      fail("عقد الإيجار غير موجود", 404);
    }

    if (strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("يمكن إلغاء العقود المفتوحة فقط", 409);
    }

    $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE rent_id = ?");
    $chk->execute([$id]);
    $cnt = (int)$chk->fetchColumn();

    if ($cnt > 0) {
      $pdo->rollBack();
      fail("لا يمكن إلغاء العقد (يوجد دفعات مرتبطة)", 409);
    }

    $notes = (string)($rent["notes"] ?? "");
    if ($reason) {
      $notes = trim($notes . "\nCANCEL_REASON: " . (string)$reason);
    }

    $upd = $pdo->prepare("UPDATE rents SET status = 'cancelled', notes = ? WHERE id = ?");
    $upd->execute([$notes, $id]);

    $stItems = $pdo->prepare("SELECT equipment_id FROM rent_items WHERE rent_id=? AND status='open'");
    $stItems->execute([$id]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);

    $updItem = $pdo->prepare("UPDATE rent_items SET status='cancelled' WHERE rent_id=? AND status='open'");
    $updItem->execute([$id]);

    $updEq = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");
    if (empty($items)) {
       $eqid = (int)$rent["equipment_id"];
       if ($eqid > 0) $updEq->execute([$eqid]);
    } else {
       foreach ($items as $it) {
         $updEq->execute([$it['equipment_id']]);
       }
    }

    $pdo->commit();
    ok(["ok" => true]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("خطأ في الخادم", 500, $e->getMessage());
  }
}

/* =========================================================
| POST /rents/{id}/replace_item
|========================================================= */
if (preg_match('#^rents/(\d+)/replace_item$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $oldEqId = (int)($in["old_equipment_id"] ?? 0);
  $newEqId = (int)($in["new_equipment_id"] ?? 0);
  $notes   = $in["notes"] ?? null;
  $now     = date('Y-m-d H:i:s');

  if ($oldEqId <= 0 || $newEqId <= 0) {
    fail("معرف المعدة القديمة أو الجديدة مفقود", 400);
  }

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT id, status FROM rents WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch();

    if (!$rent || strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("العقد غير موجود أو ليس مفتوحاً", 404);
    }

    $stOldItem = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id=? AND equipment_id=? AND status='open' FOR UPDATE");
    $stOldItem->execute([$id, $oldEqId]);
    $oldItem = $stOldItem->fetch();

    if (!$oldItem) {
      $pdo->rollBack();
      fail("المعدة القديمة غير موجودة في هذا العقد أو ليست مفتوحة", 404);
    }

    $stNewEq = $pdo->prepare("SELECT id, hourly_rate, status FROM equipment WHERE id=? FOR UPDATE");
    $stNewEq->execute([$newEqId]);
    $newEq = $stNewEq->fetch();

    if (!$newEq || strtolower((string)$newEq["status"]) === "rented") {
      $pdo->rollBack();
      fail("المعدة الجديدة غير متاحة", 409);
    }

    $updOld = $pdo->prepare("UPDATE rent_items SET status='replaced', end_datetime=?, replaced_by_id=? WHERE id=?");
    $updOld->execute([$now, $newEqId, $oldItem["id"]]);

    $updEqAvail = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");
    $updEqAvail->execute([$oldEqId]);

    $rate = array_key_exists("rate", $in) && $in["rate"] !== null ? to_float($in["rate"]) : to_float($newEq["hourly_rate"]);
    
    $insNew = $pdo->prepare("
      INSERT INTO rent_items (rent_id, equipment_id, rate, notes, status, start_datetime)
      VALUES (?,?,?,?,?,?)
    ");
    $insNew->execute([
      $id,
      $newEqId,
      $rate,
      $notes,
      "open",
      $now
    ]);

    $updEqRented = $pdo->prepare("UPDATE equipment SET status='rented' WHERE id=?");
    $updEqRented->execute([$newEqId]);

    $updRentEq = $pdo->prepare("UPDATE rents SET equipment_id=? WHERE id=? AND equipment_id=?");
    $updRentEq->execute([$newEqId, $id, $oldEqId]);

    $pdo->commit();
    ok(["ok" => true, "replaced_at" => $now]);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("خطأ في الخادم", 500, $e->getMessage());
  }
}

respond(["error" => "غير موجود"], 404);
