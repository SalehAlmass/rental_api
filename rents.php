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
  $st = $pdo->prepare("SELECT id, status, total_amount, paid_at, discount_amount
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
  $discount = (float)($rent['discount_amount'] ?? 0);
  $remaining = max($total - $discount - $paid, 0);

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

  if (auto_close_rent_if_fully_paid($pdo, $rentId)) {
    return recalc_rent_financials($pdo, $rentId);
  }

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
          LEFT JOIN equipment e ON r.equipment_id = e.id
          $where
          ORDER BY r.id DESC
          $limitSql";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  attach_rent_items($pdo, $rows);
  ok($rows);
}

/* -----------------------------------------------------------
| GET /rents/{id}
|----------------------------------------------------------- */
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

  if (!$row) fail("Rent not found", 404);
  $rows = [$row];
  attach_rent_items($pdo, $rows);
  ok($rows[0]);
}

/* -----------------------------------------------------------
| ✅ GET /rents/{id}/financials
| returns: rent + total_amount + paid_amount + remaining + is_fully_paid
|----------------------------------------------------------- */
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

  if (!$rent) fail("Rent not found", 404);
  $rows = [$rent];
  attach_rent_items($pdo, $rows);
  $rent = $rows[0];

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
    fail("Missing fields (client_id, items, start_datetime)", 400);
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
        fail("Equipment not found (ID: $eqId)", 404);
      }

      if (strtolower((string)$eq["status"]) === "rented") {
        $pdo->rollBack();
        fail("Equipment is already rented (ID: $eqId)", 409);
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

  $discount_amount = to_float($in["discount_amount"] ?? 0);
  $discount_note   = trim((string)($in["discount_note"] ?? ""));

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

    $end_ts   = strtotime($end);

    if (!$end_ts) {
      $pdo->rollBack();
      fail("Invalid end_datetime", 400);
    }

    $stItems = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id=?");
    $stItems->execute([$id]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $updItem = $pdo->prepare("UPDATE rent_items SET end_datetime=?, status='closed' WHERE id=?");
    $updEq = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");

    if (empty($items)) {
      $start_ts = strtotime((string)$rent["start_datetime"]);
      if ($start_ts) {
        $hrs = ($end_ts - $start_ts) / 3600;
        $days = ceil($hrs / 24);
        if ($days < 1) $days = 1;
        $total = round($days * to_float($rent["rate"] ?? 0), 2);
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
        
        $start_ts = strtotime((string)$it['start_datetime']);
        $iEnd_ts = strtotime($itemEnd);
        if ($start_ts && $iEnd_ts) {
          $hrs = ($iEnd_ts - $start_ts) / 3600;
          $days = ceil($hrs / 24);
          if ($days < 1) $days = 1;
          $total += round($days * to_float($it['rate']), 2);
        }
      }
    }

    $upd = $pdo->prepare("
      UPDATE rents
      SET end_datetime=?, total_amount=?, discount_amount=?, discount_note=?, status='closed'
      WHERE id=?
    ");
    $upd->execute([$end, $total, $discount_amount, $discount_note, $id]);

	    // ✅ update rent financials after final total is known
	    recalc_rent_financials($pdo, $id);

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
    fail("Server error", 500, $e->getMessage());
  }
}

/* -----------------------------------------------------------
| POST /rents/{id}/replace_item
|----------------------------------------------------------- */
if (preg_match('#^rents/(\d+)/replace_item$#', $path, $m) && $method === "POST") {
  $id = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;

  $oldEqId = (int)($in["old_equipment_id"] ?? 0);
  $newEqId = (int)($in["new_equipment_id"] ?? 0);
  $notes   = $in["notes"] ?? null;
  $now     = date('Y-m-d H:i:s');

  if ($oldEqId <= 0 || $newEqId <= 0) {
    fail("Missing old_equipment_id or new_equipment_id", 400);
  }

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT id, status FROM rents WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $rent = $st->fetch();

    if (!$rent || strtolower((string)$rent["status"]) !== "open") {
      $pdo->rollBack();
      fail("Rent not found or not open", 404);
    }

    $stOldItem = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id=? AND equipment_id=? AND status='open' FOR UPDATE");
    $stOldItem->execute([$id, $oldEqId]);
    $oldItem = $stOldItem->fetch();

    if (!$oldItem) {
      $pdo->rollBack();
      fail("Old equipment not found in this rent or not open", 404);
    }

    $stNewEq = $pdo->prepare("SELECT id, hourly_rate, status FROM equipment WHERE id=? FOR UPDATE");
    $stNewEq->execute([$newEqId]);
    $newEq = $stNewEq->fetch();

    if (!$newEq || strtolower((string)$newEq["status"]) === "rented") {
      $pdo->rollBack();
      fail("New equipment not available", 409);
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
    fail("Server error", 500, $e->getMessage());
  }
}

/*
|--------------------------------------------------------------------------
| GET rents/{id}/collection-followups
| Returns all follow-ups for a specific rent, newest first.
|--------------------------------------------------------------------------
*/
if (preg_match('#^rents/(\d+)/collection-followups$#', $path, $m) && $method === "GET") {
  $rentId = (int)$m[1];

  $st = $pdo->prepare("
    SELECT cf.*, u.username AS created_by_name
    FROM collection_followups cf
    LEFT JOIN users u ON cf.created_by_user_id = u.id
    WHERE cf.rent_id = ?
    ORDER BY cf.created_at DESC
  ");
  $st->execute([$rentId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['rent_id'] = (int)$r['rent_id'];
    $r['client_id'] = (int)$r['client_id'];
    $r['created_by_user_id'] = $r['created_by_user_id'] !== null ? (int)$r['created_by_user_id'] : null;
    $r['rent_no'] = (int)$r['rent_id'];
  }

  ok(["data" => $rows]);
}

/*
|--------------------------------------------------------------------------
| POST rents/{id}/collection-followups
| body: { contact_type, outcome, note, next_followup_at, allow_duplicate_today }
|--------------------------------------------------------------------------
*/
if (preg_match('#^rents/(\d+)/collection-followups$#', $path, $m) && $method === "POST") {
  try {
    $rentId = (int)$m[1];
    $uid = (int)($auth['sub'] ?? 0);
    $in = json_in();

    // Verify rent exists
    $stRent = $pdo->prepare("SELECT id, client_id FROM rents WHERE id=?");
    $stRent->execute([$rentId]);
    $rent = $stRent->fetch(PDO::FETCH_ASSOC);
    if (!$rent) respond(["error" => "العقد غير موجود"], 404);

    $clientId = (int)$rent['client_id'];
    $contactType = trim((string)($in['contact_type'] ?? 'call'));
    $outcome = trim((string)($in['outcome'] ?? ''));
    $note = trim((string)($in['note'] ?? ''));
    $nextFollowupAt = !empty($in['next_followup_at']) ? (string)$in['next_followup_at'] : null;
    $allowDup = (bool)($in['allow_duplicate_today'] ?? false);

    // Check duplicate today (unless explicitly allowed)
    if (!$allowDup) {
      $today = date('Y-m-d');
      $stDup = $pdo->prepare("SELECT id FROM collection_followups WHERE rent_id=? AND DATE(created_at)=? LIMIT 1");
      $stDup->execute([$rentId, $today]);
      if ($stDup->fetch()) {
        respond(["error" => "يوجد متابعة مسجلة اليوم لهذا العقد. هل تريد إضافة أخرى؟"], 409);
      }
    }

    $st = $pdo->prepare("INSERT INTO collection_followups (rent_id, client_id, created_by_user_id, contact_type, outcome, note, next_followup_at)
                         VALUES (?,?,?,?,?,?,?)");
    $st->execute([$rentId, $clientId, $uid > 0 ? $uid : null, $contactType, $outcome ?: null, $note ?: null, $nextFollowupAt]);

    $newId = (int)$pdo->lastInsertId();

    audit_log($pdo, 'collection_followup_created', 'rent', $rentId, [
      'followup_id' => $newId,
      'contact_type' => $contactType,
      'outcome' => $outcome,
    ]);

    respond(["data" => ["id" => $newId, "ok" => true]], 201);
  } catch (Throwable $e) {
    respond(["error" => "فشل في حفظ متابعة التحصيل: " . $e->getMessage()], 500);
  }
}

respond(["error" => "غير موجود"], 404);
