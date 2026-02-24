<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

require_auth();
$pdo = db();
$path = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];

/* =======================
   GET /equipment
======================= */
if ($path === "equipment" && $method === "GET") {
  $sql = "SELECT *
          FROM equipment
          WHERE is_active = 1
          ORDER BY id DESC";
  respond($pdo->query($sql)->fetchAll());
}

/* =======================
   POST /equipment
======================= */
if ($path === "equipment" && $method === "POST") {
  $in = json_in();

  $name   = trim($in["name"] ?? "");
  $model  = trim($in["model"] ?? "");
  $serial = trim($in["serial_no"] ?? "");
  $rate   = (float)($in["hourly_rate"] ?? 0);
  $dep    = (float)($in["depreciation_rate"] ?? 0);
  $status = $in["status"] ?? "available";
  $lastMaint = $in["last_maintenance_date"] ?? null;

  if ($name === "" || $serial === "" || $rate <= 0) {
    respond(["error" => "Missing required fields"], 400);
  }

  $st = $pdo->prepare(
    "INSERT INTO equipment
     (name, model, serial_no, status, hourly_rate, depreciation_rate, last_maintenance_date, is_active)
     VALUES (?,?,?,?,?,?,?,1)"
  );

  $st->execute([
    $name,
    $model,
    $serial,
    $status,
    $rate,
    $dep,
    $lastMaint
  ]);

  respond(["id" => (int)$pdo->lastInsertId()], 201);
}

/* =======================
   PUT /equipment/{id}
======================= */
if (preg_match('#^equipment/(\\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = json_in();

  $st = $pdo->prepare(
    "UPDATE equipment SET
      name=?,
      model=?,
      serial_no=?,
      status=?,
      hourly_rate=?,
      depreciation_rate=?,
      last_maintenance_date=?
     WHERE id=? AND is_active=1"
  );

  $st->execute([
    $in["name"] ?? "",
    $in["model"] ?? "",
    $in["serial_no"] ?? "",
    $in["status"] ?? "available",
    (float)($in["hourly_rate"] ?? 0),
    (float)($in["depreciation_rate"] ?? 0),
    $in["last_maintenance_date"] ?? null,
    $id
  ]);

  respond(["ok" => true]);
}

/* =======================
   DELETE /equipment/{id}
   (Soft Delete)
======================= */
if (preg_match('#^equipment/(\\d+)$#', $path, $m) && $method === "DELETE") {
  $id = (int)$m[1];

  // ممنوع الحذف لو مؤجرة
  $chk = $pdo->prepare("SELECT status FROM equipment WHERE id=?");
  $chk->execute([$id]);
  $eq = $chk->fetch();

  if (!$eq) respond(["error" => "Equipment not found"], 404);
  if ($eq["status"] === "rented") {
    respond(["error" => "Cannot delete equipment (currently rented)"], 400);
  }

  $pdo->prepare(
    "UPDATE equipment SET is_active=0 WHERE id=?"
  )->execute([$id]);

  respond(["ok" => true]);
}

respond(["error" => "Not Found"], 404);
