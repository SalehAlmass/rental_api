<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

require_auth();
$pdo = db();
$path = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];

ensure_financials_schema($pdo);
ensure_depreciation_schema($pdo);
process_monthly_depreciation($pdo);

function equipment_payload(PDO $pdo, array $in, array $current = []): array {
  $rate = (float)($in["daily_rate"] ?? ($in["hourly_rate"] ?? ($current['daily_rate'] ?? $current['hourly_rate'] ?? 0)));
  $purchase = (float)($in['purchase_price'] ?? ($current['purchase_price'] ?? 0));
  $salvage = (float)($in['salvage_value'] ?? ($current['salvage_value'] ?? 0));
  $lifeMonths = max(1, (int)($in['useful_life_months'] ?? ($current['useful_life_months'] ?? 60)));
  $estimatedUsageDays = max(1, (int)($in['estimated_usage_days'] ?? ($current['estimated_usage_days'] ?? 365)));
  $startDate = $in['depreciation_start_date'] ?? ($current['depreciation_start_date'] ?? date('Y-m-d'));

  $tmp = [
    'purchase_price' => $purchase,
    'salvage_value' => $salvage,
    'useful_life_months' => $lifeMonths,
    'estimated_usage_days' => $estimatedUsageDays,
    'depreciation_accumulated' => $current['depreciation_accumulated'] ?? 0,
  ];
  $vals = depreciation_compute_values($tmp);

  return [
    'name' => trim((string)($in['name'] ?? ($current['name'] ?? ''))),
    'model' => trim((string)($in['model'] ?? ($current['model'] ?? ''))),
    'serial_no' => trim((string)($in['serial_no'] ?? ($current['serial_no'] ?? ''))),
    'status' => (string)($in['status'] ?? ($current['status'] ?? 'available')),
    'daily_rate' => $rate,
    'hourly_rate' => $rate,
    'depreciation_rate' => (float)($in['depreciation_rate'] ?? ($current['depreciation_rate'] ?? 0)),
    'last_maintenance_date' => $in['last_maintenance_date'] ?? ($current['last_maintenance_date'] ?? null),
    'is_active' => isset($in['is_active']) ? ((int)$in['is_active'] ? 1 : 0) : ((int)($current['is_active'] ?? 1)),
    'purchase_price' => $purchase,
    'salvage_value' => $salvage,
    'useful_life_months' => $lifeMonths,
    'depreciation_start_date' => $startDate,
    'depreciation_monthly' => $vals['depreciation_monthly'],
    'estimated_usage_days' => $estimatedUsageDays,
    'operational_depreciation_per_day' => $vals['operational_depreciation_per_day'],
    'book_value' => $current ? (float)($current['book_value'] ?? $purchase) : $purchase,
  ];
}

if ($path === "equipment" && $method === "GET") {
  $sql = "SELECT * FROM equipment WHERE COALESCE(is_active,1)=1 ORDER BY id DESC";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as &$row) {
    $vals = depreciation_compute_values($row);
    $row['depreciation_monthly'] = $vals['depreciation_monthly'];
    $row['operational_depreciation_per_day'] = $vals['operational_depreciation_per_day'];
    if (!isset($row['book_value']) || (float)$row['book_value'] <= 0) {
      $row['book_value'] = $vals['book_value'];
    }
  }
  respond($rows);
}

if ($path === "equipment" && $method === "POST") {
  $in = json_in();
  $data = equipment_payload($pdo, $in);
  if ($data['name'] === '' || $data['serial_no'] === '' || $data['daily_rate'] <= 0) {
    respond(["error" => "Missing required fields"], 400);
  }

  $st = $pdo->prepare("INSERT INTO equipment
    (name, model, serial_no, status, daily_rate, hourly_rate, depreciation_rate, last_maintenance_date, is_active,
     purchase_price, salvage_value, useful_life_months, depreciation_start_date, depreciation_monthly,
     estimated_usage_days, operational_depreciation_per_day, book_value)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $st->execute([
    $data['name'], $data['model'], $data['serial_no'], $data['status'], $data['daily_rate'], $data['hourly_rate'],
    $data['depreciation_rate'], $data['last_maintenance_date'], $data['is_active'],
    $data['purchase_price'], $data['salvage_value'], $data['useful_life_months'], $data['depreciation_start_date'],
    $data['depreciation_monthly'], $data['estimated_usage_days'], $data['operational_depreciation_per_day'],
    $data['purchase_price']
  ]);
  respond(["id" => (int)$pdo->lastInsertId()], 201);
}

if (preg_match('#^equipment/(\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $curSt = $pdo->prepare("SELECT * FROM equipment WHERE id=? AND COALESCE(is_active,1)=1 LIMIT 1");
  $curSt->execute([$id]);
  $current = $curSt->fetch(PDO::FETCH_ASSOC);
  if (!$current) respond(["error" => "Equipment not found"], 404);
  $in = json_in();
  $data = equipment_payload($pdo, $in, $current);

  $st = $pdo->prepare("UPDATE equipment SET
      name=?, model=?, serial_no=?, status=?, daily_rate=?, hourly_rate=?, depreciation_rate=?, last_maintenance_date=?, is_active=?,
      purchase_price=?, salvage_value=?, useful_life_months=?, depreciation_start_date=?, depreciation_monthly=?,
      estimated_usage_days=?, operational_depreciation_per_day=?, book_value=?
     WHERE id=? AND COALESCE(is_active,1)=1");
  $st->execute([
    $data['name'], $data['model'], $data['serial_no'], $data['status'], $data['daily_rate'], $data['hourly_rate'],
    $data['depreciation_rate'], $data['last_maintenance_date'], $data['is_active'],
    $data['purchase_price'], $data['salvage_value'], $data['useful_life_months'], $data['depreciation_start_date'],
    $data['depreciation_monthly'], $data['estimated_usage_days'], $data['operational_depreciation_per_day'],
    max($data['purchase_price'] - (float)($current['depreciation_accumulated'] ?? 0), $data['salvage_value'], 0),
    $id
  ]);
  respond(["ok" => true]);
}

if (preg_match('#^equipment/(\d+)$#', $path, $m) && $method === "DELETE") {
  $id = (int)$m[1];
  $chk = $pdo->prepare("SELECT status FROM equipment WHERE id=?");
  $chk->execute([$id]);
  $eq = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$eq) respond(["error" => "Equipment not found"], 404);
  if (($eq["status"] ?? '') === "rented") respond(["error" => "Cannot delete equipment (currently rented)"], 400);
  $pdo->prepare("UPDATE equipment SET is_active=0 WHERE id=?")->execute([$id]);
  respond(["ok" => true]);
}

respond(["error" => "Not Found"], 404);
