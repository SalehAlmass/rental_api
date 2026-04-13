<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

require_auth();

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();
ensure_financials_schema($pdo);

/**
 * يدعم JSON + form-data
 */
function input_all(): array {
  $in = json_in();
  if (!$in) $in = $_POST ?? [];
  if (!is_array($in)) $in = [];
  return $in;
}

/**
 * GET /clients
 */
if ($path === "clients" && $method === "GET") {
  $sql = "SELECT * FROM clients ORDER BY id DESC";
  respond($pdo->query($sql)->fetchAll());
}

/**
 * GET /clients/{id}/collection-followups
 */
if (preg_match('#^clients/(\d+)/collection-followups$#', $path, $m) && $method === "GET") {
  $id = (int)$m[1];

  $chk = $pdo->prepare("SELECT id FROM clients WHERE id=? LIMIT 1");
  $chk->execute([$id]);
  if (!$chk->fetch()) respond(["error" => "Client not found"], 404);

  $st = $pdo->prepare("SELECT f.*, r.id AS rent_no, u.name AS created_by_name
                       FROM collection_followups f
                       LEFT JOIN rents r ON f.rent_id = r.id
                       LEFT JOIN users u ON f.created_by_user_id = u.id
                       WHERE f.client_id = ?
                       ORDER BY f.created_at DESC, f.id DESC
                       LIMIT 50");
  $st->execute([$id]);
  respond($st->fetchAll());
}

/**
 * POST /clients
 */
if ($path === "clients" && $method === "POST") {
  $in = input_all();

  $name         = trim((string)($in["name"] ?? ""));
  $national_id  = trim((string)($in["national_id"] ?? ""));
  $phone        = trim((string)($in["phone"] ?? ""));
  $address      = trim((string)($in["address"] ?? ""));
  $is_frozen    = (int)($in["is_frozen"] ?? 0);
  $credit_limit = (float)($in["credit_limit"] ?? 0);
  $image_path   = $in["image_path"] ?? null;

  if ($name === "") respond(["error" => "name is required"], 400);

  // (اختياري) منع تكرار الهوية لو تحب
  // if ($national_id !== "") { ... }

  $st = $pdo->prepare("INSERT INTO clients (name, national_id, phone, address, is_frozen, credit_limit, image_path)
                       VALUES (?,?,?,?,?,?,?)");
  $st->execute([$name, $national_id, $phone, $address, $is_frozen, $credit_limit, $image_path]);

  respond(["id" => (int)$pdo->lastInsertId()], 201);
}

/**
 * PUT /clients/{id}
 */
if (preg_match('#^clients/(\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = input_all();

  // تأكد العميل موجود
  $chk = $pdo->prepare("SELECT id FROM clients WHERE id=?");
  $chk->execute([$id]);
  if (!$chk->fetch()) respond(["error" => "Client not found"], 404);

  // نبني تحديث ديناميكي (يحدث فقط اللي تم إرساله)
  $fields = [];
  $params = [];

  $map = [
    "name"         => "name",
    "national_id"  => "national_id",
    "phone"        => "phone",
    "address"      => "address",
    "is_frozen"    => "is_frozen",
    "credit_limit" => "credit_limit",
    "image_path"   => "image_path",
  ];

  foreach ($map as $k => $col) {
    if (array_key_exists($k, $in)) {
      $fields[] = "$col = ?";
      if ($k === "is_frozen") $params[] = (int)$in[$k];
      else if ($k === "credit_limit") $params[] = (float)$in[$k];
      else $params[] = $in[$k];
    }
  }

  if (empty($fields)) respond(["error" => "No fields to update"], 400);

  $params[] = $id;
  $sql = "UPDATE clients SET " . implode(", ", $fields) . " WHERE id = ?";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  respond(["ok" => true, "id" => $id]);
}

/**
 * DELETE /clients/{id}
 */
if (preg_match('#^clients/(\d+)$#', $path, $m) && $method === "DELETE") {
  $id = (int)$m[1];

  // لو العميل مرتبط بعقود/سندات، MySQL قد يرفض بسبب FK
  // فنرجّع رسالة واضحة
  try {
    $st = $pdo->prepare("DELETE FROM clients WHERE id=?");
    $st->execute([$id]);
  } catch (PDOException $e) {
    respond(["error" => "Cannot delete client (has related records)"], 409);
  }

  respond(["ok" => true, "id" => $id]);
}

respond(["error" => "Not Found"], 404);
