<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

// نحتاج بيانات المستخدم لإسناد السندات لموظف (user_id)
$auth = require_auth();
$uid  = (int)($auth['sub'] ?? 0);
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// ✅ auto-migrate: rent financial state + idempotency + audit log
ensure_financials_schema($pdo);
ensure_depreciation_schema($pdo);
process_monthly_depreciation($pdo);

/**
 * Recalculate and persist the rent financial state.
 * Single source of truth lives on the server in rents.paid_amount/remaining_amount/is_paid/paid_at
 */
function recalc_rent_financials(PDO $pdo, int $rentId): void {
  // lock rent row to avoid race conditions
  $st = $pdo->prepare("SELECT id, status, total_amount, paid_at
                       FROM rents WHERE id=? FOR UPDATE");
  $st->execute([$rentId]);
  $rent = $st->fetch();
  if (!$rent) return;

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
}

/**
 * Routes:
 * GET    payments
 * POST   payments
 * PUT    payments/{id}
 * POST   payments/{id}/void   (بديل delete)
 */

function fetch_rent(PDO $pdo, int $rent_id) {
  $st = $pdo->prepare("SELECT id, client_id FROM rents WHERE id=?");
  $st->execute([$rent_id]);
  return $st->fetch();
}

function fetch_client(PDO $pdo, int $client_id) {
  $st = $pdo->prepare("SELECT id FROM clients WHERE id=?");
  $st->execute([$client_id]);
  return $st->fetch();
}

// GET /payments
if ($path === "payments" && $method === "GET") {
  $show_void = isset($_GET["show_void"]) ? (int)$_GET["show_void"] : 0;
  $from = $_GET['from'] ?? null; // yyyy-mm-dd
  $to   = $_GET['to'] ?? null;   // yyyy-mm-dd
  $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
  $rentId   = isset($_GET['rent_id']) ? (int)$_GET['rent_id'] : 0;
  $userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

  // بناء شروط البحث
  $conds = [];
  $params = [];

  if (!$show_void) {
    $conds[] = "p.is_void = 0";
  }
  if ($clientId > 0) {
    $conds[] = "p.client_id = ?";
    $params[] = $clientId;
  }
  if ($rentId > 0) {
    $conds[] = "p.rent_id = ?";
    $params[] = $rentId;
  }
  if ($userId > 0) {
    $conds[] = "p.user_id = ?";
    $params[] = $userId;
  }
  if ($from) {
    $conds[] = "p.created_at >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to) {
    $conds[] = "p.created_at <= ?";
    $params[] = $to . " 23:59:59";
  }

  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $sql = "SELECT p.*,
                 c.name AS client_name,
                 r.id   AS rent_no,
                 eq.name AS equipment_name
          FROM payments p
          LEFT JOIN clients c ON p.client_id = c.id
          LEFT JOIN rents   r ON p.rent_id = r.id
          LEFT JOIN equipment eq ON p.equipment_id = eq.id
          $where
          ORDER BY p.id DESC";

  if (count($params)) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    respond($st->fetchAll());
  }

  respond($pdo->query($sql)->fetchAll());
}

// POST /payments
if ($path === "payments" && $method === "POST") {
  $in = json_in();
  if (!$in) $in = $_POST;

  $type   = trim((string)($in["type"] ?? ""));
  $amount = (float)($in["amount"] ?? 0);

  if (!in_array($type, ["in","out","depreciation"], true)) respond(["error"=>"type must be in|out|depreciation"], 400);
  if ($amount <= 0) respond(["error"=>"amount must be > 0"], 400);

  $client_id = (isset($in["client_id"]) && $in["client_id"] !== "") ? (int)$in["client_id"] : null;
  $rent_id   = (isset($in["rent_id"])   && $in["rent_id"]   !== "") ? (int)$in["rent_id"]   : null;

  // ✅ إذا rent_id موجود: لازم العقد موجود + client_id يطابقه (أو نأخذه تلقائيًا)
  if ($rent_id !== null && $rent_id > 0) {
    $rent = fetch_rent($pdo, $rent_id);
    if (!$rent) respond(["error" => "Rent not found"], 404);

    if ($client_id === null || $client_id <= 0) {
      $client_id = (int)$rent["client_id"];
    } else {
      if ((int)$rent["client_id"] !== (int)$client_id) {
        respond(["error" => "client_id does not match rent's client_id"], 409);
      }
    }
  }

  // ✅ إذا client_id موجود: تأكد العميل موجود
  if ($client_id !== null && $client_id > 0) {
    if (!fetch_client($pdo, $client_id)) respond(["error" => "Client not found"], 404);
  }

  $equipment_id = (isset($in["equipment_id"]) && $in["equipment_id"] !== "") ? (int)$in["equipment_id"] : null;
  $methodPay = $in["method"] ?? "cash";
  $ref   = $in["reference_no"] ?? null;
  $notes = $in["notes"] ?? null;

  // ✅ Idempotency key (optional) to prevent duplicate vouchers
  $idemKey = isset($in['idempotency_key']) ? trim((string)$in['idempotency_key']) : null;
  if ($idemKey !== null && $idemKey !== '') {
    // if already created, return existing record id
    $chk = $pdo->prepare("SELECT id FROM payments WHERE idempotency_key=? LIMIT 1");
    $chk->execute([$idemKey]);
    $existingId = $chk->fetchColumn();
    if ($existingId) {
      respond(["id" => (int)$existingId, "idempotent" => true], 200);
    }
  } else {
    $idemKey = null;
  }

  // ✅ If this is an IN payment linked to a rent: enforce remaining and update rent financials atomically
  if ($rent_id !== null && $rent_id > 0 && $type === 'in') {
    $pdo->beginTransaction();
    try {
      // lock rent row
      $stR = $pdo->prepare("SELECT id, status, total_amount, remaining_amount
                            FROM rents WHERE id=? FOR UPDATE");
      $stR->execute([$rent_id]);
      $rRow = $stR->fetch();
      if (!$rRow) {
        $pdo->rollBack();
        respond(["error" => "Rent not found"], 404);
      }

      $status = strtolower((string)($rRow['status'] ?? ''));
      // allow collecting while open if you want, but never mark as paid until closed (handled in recalc)
      $total = (float)($rRow['total_amount'] ?? 0);
      $remaining = (float)($rRow['remaining_amount'] ?? 0);

      // If the rent is CLOSED and remaining is zero => reject
      if ($status !== 'open') {
        // recalc first in case remaining_amount is not updated yet
        recalc_rent_financials($pdo, (int)$rent_id);
        $stR2 = $pdo->prepare("SELECT remaining_amount FROM rents WHERE id=?");
        $stR2->execute([$rent_id]);
        $remaining = (float)$stR2->fetchColumn();
        if ($remaining <= 0.0001) {
          $pdo->rollBack();
          respond(["error" => "Rent is already fully paid"], 409);
        }
        // do not allow overpay
        if ($amount > $remaining + 0.0001) {
          $pdo->rollBack();
          respond(["error" => "Amount exceeds remaining"], 409);
        }
      }

      $st = $pdo->prepare("INSERT INTO payments (type, amount, client_id, rent_id, equipment_id, method, reference_no, notes, user_id, is_void, idempotency_key)
                           VALUES (?,?,?,?,?,?,?,?,?,0,?)");
      $st->execute([$type, $amount, $client_id, $rent_id, $equipment_id, $methodPay, $ref, $notes, ($uid > 0 ? $uid : null), $idemKey]);
      $newId = (int)$pdo->lastInsertId();

      // update rent financials after insert
      recalc_rent_financials($pdo, (int)$rent_id);
      audit_log($pdo, 'payment_created', 'payment', $newId, ['rent_id' => $rent_id, 'amount' => $amount, 'type' => $type]);

      $pdo->commit();
      respond(["id" => $newId], 201);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      // if idempotency unique constraint hit
      if (strpos($e->getMessage(), 'uniq_payments_idem_key') !== false && $idemKey) {
        $chk = $pdo->prepare("SELECT id FROM payments WHERE idempotency_key=? LIMIT 1");
        $chk->execute([$idemKey]);
        $existingId = $chk->fetchColumn();
        if ($existingId) respond(["id" => (int)$existingId, "idempotent" => true], 200);
      }
      respond(["error" => "Server error", "details" => $e->getMessage()], 500);
    }
  }

  // default insert (general payments or OUT payments)
  $st = $pdo->prepare("INSERT INTO payments (type, amount, client_id, rent_id, equipment_id, method, reference_no, notes, user_id, is_void, idempotency_key)
                       VALUES (?,?,?,?,?,?,?,?,?,0,?)");
  $st->execute([$type, $amount, $client_id, $rent_id, $equipment_id, $methodPay, $ref, $notes, ($uid > 0 ? $uid : null), $idemKey]);

  $newId = (int)$pdo->lastInsertId();
  audit_log($pdo, 'payment_created', 'payment', $newId, ['rent_id' => $rent_id, 'amount' => $amount, 'type' => $type]);
  // if rent linked and OUT or other, still recalc for safety
  if ($rent_id !== null && $rent_id > 0) {
    $pdo->beginTransaction();
    try {
      recalc_rent_financials($pdo, (int)$rent_id);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
    }
  }

  respond(["id" => $newId], 201);
}

// PUT /payments/{id}
if (preg_match('#^payments/(\\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $st = $pdo->prepare("SELECT * FROM payments WHERE id=?");
  $st->execute([$id]);
  $pay = $st->fetch();
  if (!$pay) respond(["error" => "Payment not found"], 404);

  if ((int)$pay["is_void"] === 1) {
    respond(["error" => "Cannot update voided payment"], 409);
  }

  $type = array_key_exists("type", $in) ? trim((string)$in["type"]) : (string)$pay["type"];
  if (!in_array($type, ["in","out","depreciation"], true)) respond(["error"=>"type must be in|out|depreciation"], 400);

  $amount = isset($in["amount"]) ? (float)$in["amount"] : (float)$pay["amount"];
  if ($amount <= 0) respond(["error" => "amount must be > 0"], 400);

  $methodPay = isset($in["method"]) ? (string)$in["method"] : (string)$pay["method"];
  $ref   = array_key_exists("reference_no", $in) ? $in["reference_no"] : $pay["reference_no"];
  $notes = array_key_exists("notes", $in) ? $in["notes"] : $pay["notes"];

  $client_id = array_key_exists("client_id", $in)
      ? (($in["client_id"] === null || $in["client_id"] === "") ? null : (int)$in["client_id"])
      : ($pay["client_id"] !== null ? (int)$pay["client_id"] : null);

  $rent_id = array_key_exists("rent_id", $in)
      ? (($in["rent_id"] === null || $in["rent_id"] === "") ? null : (int)$in["rent_id"])
      : ($pay["rent_id"] !== null ? (int)$pay["rent_id"] : null);

  $equipment_id = array_key_exists("equipment_id", $in)
      ? (($in["equipment_id"] === null || $in["equipment_id"] === "") ? null : (int)$in["equipment_id"])
      : ($pay["equipment_id"] !== null ? (int)$pay["equipment_id"] : null);

  // ✅ إذا rent_id موجود: لازم العقد موجود + client_id يطابقه (أو نأخذه تلقائيًا)
  if ($rent_id !== null && $rent_id > 0) {
    $rent = fetch_rent($pdo, $rent_id);
    if (!$rent) respond(["error" => "Rent not found"], 404);

    if ($client_id === null || $client_id <= 0) {
      $client_id = (int)$rent["client_id"];
    } else {
      if ((int)$rent["client_id"] !== (int)$client_id) {
        respond(["error" => "client_id does not match rent's client_id"], 409);
      }
    }
  }

  // ✅ إذا client_id موجود: تأكد العميل موجود
  if ($client_id !== null && $client_id > 0) {
    if (!fetch_client($pdo, $client_id)) respond(["error" => "Client not found"], 404);
  }

  $upd = $pdo->prepare("UPDATE payments
                        SET type=?, amount=?, client_id=?, rent_id=?, equipment_id=?, method=?, reference_no=?, notes=?
                        WHERE id=?");
  $upd->execute([$type, $amount, $client_id, $rent_id, $equipment_id, $methodPay, $ref, $notes, $id]);

  respond(["ok" => true]);
}

// POST /payments/{id}/void
if (preg_match('#^payments/(\\d+)/void$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  $reason = $in["reason"] ?? null;

  $st = $pdo->prepare("SELECT * FROM payments WHERE id=?");
  $st->execute([$id]);
  $pay = $st->fetch();
  if (!$pay) respond(["error" => "Payment not found"], 404);

  if ((int)$pay["is_void"] === 1) {
    respond(["error" => "Payment already voided"], 409);
  }

  $upd = $pdo->prepare("UPDATE payments
                        SET is_void=1, voided_at=NOW(), void_reason=?
                        WHERE id=?");

	$pdo->beginTransaction();
	try {
	  $upd->execute([$reason, $id]);
	  $rentId = (int)($pay['rent_id'] ?? 0);
	  if ($rentId > 0) {
	    recalc_rent_financials($pdo, $rentId);
	  }
	  audit_log($pdo, 'payment_voided', 'payment', $id, ['reason' => $reason, 'rent_id' => $rentId]);
	  $pdo->commit();
	  respond(["ok" => true]);
	} catch (Throwable $e) {
	  if ($pdo->inTransaction()) $pdo->rollBack();
	  respond(["error" => "Server error", "details" => $e->getMessage()], 500);
	}
}

respond(["error"=>"Not Found"], 404);
