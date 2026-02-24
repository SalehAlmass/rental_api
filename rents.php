<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

require_auth();

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// ✅ auto-migrate: rent financial state + idempotency + audit log
ensure_financials_schema($pdo);

/**
 * Compute rent financials from DB (and store them on rents table).
 * Single source of truth: rents.paid_amount / rents.remaining_amount / rents.is_paid / rents.paid_at
 */
function recalc_rent_financials(PDO $pdo, int $rentId): array {
  // lock rent row
  $st = $pdo->prepare("SELECT id, status, total_amount, paid_at
                       FROM rents WHERE id=? FOR UPDATE");
  $st->execute([$rentId]);
  $rent = $st->fetch();
  if (!$rent) return [];

  $st2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0)
                        FROM payments
                        WHERE rent_id=?
                          AND (is_void=0 OR is_void IS NULL)
                          AND type='in'");
  $st2->execute([$rentId]);
  $paid = (float)$st2->fetchColumn();

  $total = (float)($rent['total_amount'] ?? 0);
  $remaining = max($total - $paid, 0);

  $status = strtolower((string)($rent['status'] ?? ''));
  // IMPORTANT: never mark OPEN rent as paid
  $isPaid = ($status !== 'open') && ($remaining <= 0.0001);

  $paidAt = $rent['paid_at'];
  if ($isPaid && empty($paidAt)) {
    $paidAt = date('Y-m-d H:i:s');
  }
  if (!$isPaid) {
    $paidAt = null;
  }

  $upd = $pdo->prepare("UPDATE rents
                        SET paid_amount=?, remaining_amount=?, is_paid=?, paid_at=?
                        WHERE id=?");
  $upd->execute([$paid, $remaining, $isPaid ? 1 : 0, $paidAt, $rentId]);

  return [
    'paid_amount' => $paid,
    'remaining_amount' => $remaining,
    'is_paid' => $isPaid,
    'paid_at' => $paidAt,
    'total_amount' => $total,
  ];
}

/**
 * Routes:
 * GET    rents
 * GET    rents/{id}
 * GET    rents/{id}/financials     ✅ NEW
 * POST   rents                     (open)
 * PUT    rents/{id}                (edit notes فقط)
 * POST   rents/{id}/close
 * POST   rents/{id}/cancel         (soft cancel بدل delete)
 */

function to_float($v): float {
  if (is_numeric($v)) return (float)$v;
  $x = trim((string)$v);
  return $x === '' ? 0.0 : (float)$x;
}

function ok($data = null, int $code = 200) {
  respond(["success" => true, "data" => $data], $code);
}

function fail(string $msg, int $code = 400, $details = null) {
  $out = ["success" => false, "error" => $msg];
  if ($details !== null) $out["details"] = $details;
  respond($out, $code);
}

/* -----------------------------------------------------------
| GET /rents
|----------------------------------------------------------- */
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
  if ($status !== '' && in_array($status, ['open','closed','cancelled'], true)) {
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
          JOIN equipment e ON r.equipment_id = e.id
          $where
          ORDER BY r.id DESC
          $limitSql";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  ok($st->fetchAll());
}

/* -----------------------------------------------------------
| GET /rents/{id}
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)$#', $path, $m) && $method === "GET") {
  $id = (int)$m[1];

  $st = $pdo->prepare("
    SELECT r.*,
           c.name AS client_name,
           e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $row = $st->fetch();

  if (!$row) fail("Rent not found", 404);
  ok($row);
}

/* -----------------------------------------------------------
| ✅ GET /rents/{id}/financials
| returns: rent + total_amount + paid_amount + remaining + is_fully_paid
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)/financials$#', $path, $m) && $method === "GET") {
  $rentId = (int)$m[1];

  // 1) rent
  $st = $pdo->prepare("
    SELECT r.*,
           c.name AS client_name,
           e.name AS equipment_name
    FROM rents r
    JOIN clients c ON r.client_id = c.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$rentId]);
  $rent = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rent) fail("Rent not found", 404);

  // 2) total
  $total = to_float($rent["total_amount"] ?? 0);

  // 3) paid (in only, not void)
  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS paid
    FROM payments
    WHERE rent_id = ?
      AND LOWER(type) = 'in'
      AND (is_void IS NULL OR is_void = 0)
  ");
  $st2->execute([$rentId]);
  $paid = to_float(($st2->fetch(PDO::FETCH_ASSOC)["paid"] ?? 0));

  // 4) remaining
  $remaining = max(0.0, $total - $paid);

  // 5) ✅ لا تجعلها FullyPaid إذا العقد OPEN
  $status = strtolower((string)($rent["status"] ?? ""));
  $isOpen = ($status === 'open');
  $isFullyPaid = $isOpen ? false : ($remaining <= 0.0001);

  ok([
    "rent" => $rent,
    "total_amount" => $total,
    "paid_amount" => $paid,
    "remaining" => $remaining,
    "is_fully_paid" => $isFullyPaid,
  ]);
}

/* -----------------------------------------------------------
| POST /rents (فتح عقد)
|----------------------------------------------------------- */
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
    $stEq = $pdo->prepare("SELECT id, hourly_rate, status FROM equipment WHERE id=? FOR UPDATE");
    $stEq->execute([$equipment_id]);
    $eq = $stEq->fetch();

    if (!$eq) {
      $pdo->rollBack();
      fail("Equipment not found", 404);
    }

    if (strtolower((string)$eq["status"]) === "rented") {
      $pdo->rollBack();
      fail("Equipment is already rented", 409);
    }

    $rate = array_key_exists("rate", $in) ? to_float($in["rate"]) : to_float($eq["hourly_rate"]);
    $hours = $in["hours"] ?? null;

    if ($hours === null && $end !== "") {
      $start_ts = strtotime($start);
      $end_ts   = strtotime($end);
      if ($start_ts && $end_ts && $end_ts > $start_ts) {
        $hours = round(($end_ts - $start_ts) / 3600, 2);
      }
    }
    $hours = ($hours !== null) ? to_float($hours) : null;
    $total = ($hours !== null) ? round($hours * $rate, 2) : null;

    $st = $pdo->prepare("
      INSERT INTO rents (client_id, equipment_id, start_datetime, end_datetime, hours, rate, total_amount, notes, status)
      VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      $client_id,
      $equipment_id,
      $start,
      $end !== "" ? $end : null,
      $hours,
      $rate,
      $total,
      $notes,
      "open",
    ]);

    $id = (int)$pdo->lastInsertId();

    $upd = $pdo->prepare("UPDATE equipment SET status='rented' WHERE id=?");
    $upd->execute([$equipment_id]);

    $pdo->commit();
    ok(["id" => $id], 201);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("Server error", 500, $e->getMessage());
  }
}

/* -----------------------------------------------------------
| PUT /rents/{id} (تعديل notes فقط + لازم يكون open)
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $st = $pdo->prepare("SELECT id, status FROM rents WHERE id=?");
  $st->execute([$id]);
  $rent = $st->fetch();

  if (!$rent) fail("Rent not found", 404);
  if (strtolower((string)$rent["status"]) !== "open") fail("Only open rents can be edited", 409);
  if (!array_key_exists("notes", $in)) fail("notes is required", 400);

  $notes = $in["notes"];
  $upd = $pdo->prepare("UPDATE rents SET notes=? WHERE id=?");
  $upd->execute([$notes, $id]);

  ok(["ok" => true]);
}

/* -----------------------------------------------------------
| POST /rents/{id}/close
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)/close$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $end = trim((string)($in["end_datetime"] ?? ""));
  if ($end === "") fail("end_datetime is required", 400);

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT * FROM rents WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch();

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

    $hours = $in["hours"] ?? null;
    if ($hours === null) $hours = round(($end_ts - $start_ts) / 3600, 2);
    $hours = to_float($hours);

    $rate  = to_float($rent["rate"] ?? 0);
    $total = round($hours * $rate, 2);

    $upd = $pdo->prepare("
      UPDATE rents
      SET end_datetime=?, hours=?, total_amount=?, status='closed'
      WHERE id=?
    ");
    $upd->execute([$end, $hours, $total, $id]);

	    // ✅ update rent financials after final total is known
	    recalc_rent_financials($pdo, $id);

    $eqid = (int)$rent["equipment_id"];
    $upd2 = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");
    $upd2->execute([$eqid]);

	    audit_log($pdo, 'rent_closed', 'rent', $id, ['total_amount' => $total]);
	    $pdo->commit();
	    ok(["ok" => true, "total_amount" => $total]);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("Server error", 500, $e->getMessage());
  }
}

/* -----------------------------------------------------------
| POST /rents/{id}/cancel
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)/cancel$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $reason = $in["reason"] ?? null;

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT * FROM rents WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch();

    if (!$rent) {
      $pdo->rollBack();
      fail("Rent not found", 404);
    }

    if (strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("Only open rents can be cancelled", 409);
    }

    $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE rent_id=?");
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

    $upd = $pdo->prepare("UPDATE rents SET status='cancelled', notes=? WHERE id=?");
    $upd->execute([$notes, $id]);

    $eqid = (int)$rent["equipment_id"];
    $upd2 = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");
    $upd2->execute([$eqid]);

    $pdo->commit();
    ok(["ok" => true]);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail("Server error", 500, $e->getMessage());
  }
}

fail("Not Found", 404);
