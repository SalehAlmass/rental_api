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
  if ($hours < 3) {
    return [
      'total' => round($dailyRate * (2 / 3), 2),
      'pricing_rule_code' => 'less_than_3h_two_thirds_day',
      'pricing_rule_label' => 'أقل من 3 ساعات = ثلثي السعر اليومي',
    ];
  }

  return [
    'total' => round($dailyRate, 2),
    'pricing_rule_code' => 'full_day',
    'pricing_rule_label' => '3 ساعات فأكثر = يوم كامل',
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
    SELECT id, status, total_amount, paid_at
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
  $remaining = max($total - $paid, 0.0);

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

  $sql = "
    SELECT r.*, c.name AS client_name, e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    JOIN equipment e ON r.equipment_id = e.id
    $where
    ORDER BY r.id DESC
    $limitSql
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as &$row) {
    $row = normalize_rent_row($row);
  }

  ok($rows);
}

/* =========================================================
| GET /rents/{id}
|========================================================= */
if (preg_match('#^rents/(\d+)$#', $path, $m) && $method === "GET") {
  $id = (int)$m[1];

  $st = $pdo->prepare("
    SELECT r.*, c.name AS client_name, e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    fail("Rent not found", 404);
  }

  $row = normalize_rent_row($row);
  ok($row);
}

/* =========================================================
| GET /rents/{id}/financials
|========================================================= */
if (preg_match('#^rents/(\d+)/financials$#', $path, $m) && $method === "GET") {
  $rentId = (int)$m[1];

  $st = $pdo->prepare("
    SELECT r.*, c.name AS client_name, e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) {
    fail("Rent not found", 404);
  }

  $rent = normalize_rent_row($rent);

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
    fail("Rent not found", 404);
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
    fail("Rent not found", 404);
  }

  $clientId = (int)($rent['client_id'] ?? 0);
  $contactType = strtolower(trim((string)($in['contact_type'] ?? '')));
  $outcome = strtolower(trim((string)($in['outcome'] ?? '')));
  $note = trim((string)($in['note'] ?? ''));
  $nextFollowupAt = trim((string)($in['next_followup_at'] ?? ''));
  $createdBy = (int)($auth['sub'] ?? 0);

  if (!in_array($contactType, ['call', 'whatsapp', 'visit', 'verbal', 'no_answer'], true)) {
    fail("Invalid contact_type", 400);
  }

  if ($outcome !== '' && !in_array($outcome, ['promise_to_pay', 'follow_up_later', 'paid', 'customer_requested_delay', 'no_answer', 'other'], true)) {
    fail("Invalid outcome", 400);
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
    fail('Failed to save collection follow-up', 500, $e->getMessage());
  }
}

/* =========================================================
| POST /rents
|========================================================= */
if ($path === "rents" && $method === "POST") {
  $in = json_in();
  if (!$in) $in = $_POST;

  $client_id    = (int)($in["client_id"] ?? 0);
  $equipment_id = (int)($in["equipment_id"] ?? 0);
  $start        = trim((string)($in["start_datetime"] ?? ""));
  $end          = trim((string)($in["end_datetime"] ?? ""));
  $notes        = $in["notes"] ?? null;

  if ($client_id <= 0 || $equipment_id <= 0 || $start === "") {
    fail("Missing fields (client_id, equipment_id, start_datetime)", 400);
  }

  $pdo->beginTransaction();
  try {
    $stEq = $pdo->prepare("SELECT id, hourly_rate, status FROM equipment WHERE id = ? FOR UPDATE");
    $stEq->execute([$equipment_id]);
    $eq = $stEq->fetch(PDO::FETCH_ASSOC);

    if (!$eq) {
      $pdo->rollBack();
      fail("Equipment not found", 404);
    }

    if (strtolower((string)$eq["status"]) === "rented") {
      $pdo->rollBack();
      fail("Equipment is already rented", 409);
    }

    // ملاحظة: rate هنا يُعامل كسعر يومي
    $rate = array_key_exists("rate", $in)
      ? to_float($in["rate"])
      : to_float($eq["hourly_rate"]);

    $st = $pdo->prepare("
      INSERT INTO rents (
        client_id, equipment_id, start_datetime, end_datetime,
        hours, rate, total_amount, notes, status,
        paid_amount, remaining_amount, is_paid
      )
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      $client_id,
      $equipment_id,
      $start,
      $end !== "" ? $end : null,
      null,
      $rate,
      0,
      $notes,
      "open",
      0,
      0,
      0,
    ]);

    $id = (int)$pdo->lastInsertId();

    $upd = $pdo->prepare("UPDATE equipment SET status = 'rented' WHERE id = ?");
    $upd->execute([$equipment_id]);

    $pdo->commit();
    ok(["id" => $id], 201);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("Server error", 500, $e->getMessage());
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
    fail("Rent not found", 404);
  }

  if (strtolower((string)$rent["status"]) !== "open") {
    fail("Only open rents can be edited", 409);
  }

  if (!array_key_exists("notes", $in)) {
    fail("notes is required", 400);
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
    fail("end_datetime is required", 400);
  }

  $applySpecialPricing = !empty($in['apply_special_pricing']);
  $paidAmount = to_float($in['paid_amount'] ?? 0);
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
      fail("Rent not found", 404);
    }

    if (strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("Rent is not open", 409);
    }

    $start_ts = strtotime((string)$rent["start_datetime"]);
    $end_ts   = strtotime($end);

    if (!$start_ts || !$end_ts || $end_ts <= $start_ts) {
      $pdo->rollBack();
      fail("Invalid end_datetime", 400);
    }

    $seconds = $end_ts - $start_ts;
    $hours = round($seconds / 3600, 2);

    $rate  = to_float($rent["rate"] ?? 0);

    // الاحتساب القياسي والخاص عندك في النهاية نفس القاعدة اليومية
    $calc = calc_daily_pricing($rate, $hours);
    $total = to_float($calc['total']);
    $pricingCode = (string)$calc['pricing_rule_code'];
    $pricingLabel = (string)$calc['pricing_rule_label'];

    // نحتفظ بالخيار ليتتبع النظام إن تم استخدامه يدويًا
    if (!$applySpecialPricing) {
      $pricingCode = 'daily_default';
      $pricingLabel = $hours < 3
        ? 'الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي'
        : 'الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل';
    }

    $paymentId = null;
    $paymentStatus = $createReceipt ? 'created' : 'not_created';

    $upd = $pdo->prepare("
      UPDATE rents
      SET end_datetime = ?, hours = ?, total_amount = ?, status = 'closed',
          closed_at = ?, closed_by_user_id = ?,
          closing_paid_amount = ?, closing_payment_method = ?, closing_payment_status = ?, closing_payment_id = ?,
          pricing_rule_code = ?, pricing_rule_label = ?, pricing_rule_applied = ?
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

    $eqid = (int)$rent["equipment_id"];
    $upd2 = $pdo->prepare("UPDATE equipment SET status = 'available' WHERE id = ?");
    $upd2->execute([$eqid]);

    $fin = recalc_rent_financials($pdo, $id);

    if ($applySpecialPricing) {
      audit_log($pdo, 'manual_hour_pricing_used', 'rent', $id, [
        'pricing_rule_code' => $pricingCode,
        'pricing_rule_label' => $pricingLabel,
        'hours' => $hours,
        'total_amount' => $total,
      ]);
    }

    if (!$createReceipt) {
      audit_log($pdo, 'receipt_skipped_on_close', 'rent', $id, [
        'closing_paid_amount' => $paidAmount,
        'closing_payment_method' => $paymentMethod,
        'closed_at' => $closingAt,
      ]);
    }

    audit_log($pdo, 'rent_closed', 'rent', $id, [
      'total_amount' => $total,
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
    fail("Server error", 500, $e->getMessage());
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
      fail("Rent not found", 404);
    }

    if (strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("Only open rents can be cancelled", 409);
    }

    $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE rent_id = ?");
    $chk->execute([$id]);
    $cnt = (int)$chk->fetchColumn();

    if ($cnt > 0) {
      $pdo->rollBack();
      fail("Cannot cancel rent (has related payments)", 409);
    }

    $notes = (string)($rent["notes"] ?? "");
    if ($reason) {
      $notes = trim($notes . "\nCANCEL_REASON: " . (string)$reason);
    }

    $upd = $pdo->prepare("UPDATE rents SET status = 'cancelled', notes = ? WHERE id = ?");
    $upd->execute([$notes, $id]);

    $eqid = (int)$rent["equipment_id"];
    $upd2 = $pdo->prepare("UPDATE equipment SET status = 'available' WHERE id = ?");
    $upd2->execute([$eqid]);

    $pdo->commit();
    ok(["ok" => true]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("Server error", 500, $e->getMessage());
  }
}

fail("Not Found", 404);