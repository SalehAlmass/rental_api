<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth = require_auth();

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

require_permission($pdo, $auth, 'clients');

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
  $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $perPage = isset($_GET['per_page']) ? max(1, min(200, (int)$_GET['per_page'])) : 200;

  $conds = [];
  $params = [];

  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  if ($q !== '') {
    $conds[] = "(MATCH(name) AGAINST(? IN BOOLEAN MODE) OR name LIKE ? OR phone LIKE ? OR national_id LIKE ? OR id = ?)";
    $params[] = $q . "*";
    $params[] = "%" . $q . "%";
    $params[] = "%" . $q . "%";
    $params[] = "%" . $q . "%";
    $params[] = is_numeric($q) ? (int)$q : 0;
  }

  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  $countSt = $pdo->prepare("SELECT COUNT(*) FROM clients $where");
  $countSt->execute($params);
  $total   = (int)$countSt->fetchColumn();

  $offset  = ($page - 1) * $perPage;

  $allowedSorts = [
    'name' => 'name',
    'phone' => 'phone',
    'national_id' => 'national_id',
    'created_at' => 'created_at',
    'id' => 'id'
  ];
  $sortSql = get_sort_sql($allowedSorts, 'id', 'desc', 'id DESC');

  $sql = "SELECT * FROM clients $where $sortSql LIMIT $perPage OFFSET $offset";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if (!empty($rows)) {
    $clientIds = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    
    // Get all rents for these clients
    $stRents = $pdo->prepare("SELECT id, client_id, status, start_datetime, end_datetime, rate, paid_amount, remaining_amount, discount_amount FROM rents WHERE client_id IN ($placeholders)");
    $stRents->execute($clientIds);
    $rents = $stRents->fetchAll(PDO::FETCH_ASSOC);

    // Filter open rents to fetch their items
    $openRentIds = [];
    foreach ($rents as $r) {
      if ($r['status'] === 'open') {
        $openRentIds[] = (int)$r['id'];
      }
    }

    $itemsByRent = [];
    if (!empty($openRentIds)) {
      $itemPlaceholders = implode(',', array_fill(0, count($openRentIds), '?'));
      $stItems = $pdo->prepare("SELECT rent_id, rate, start_datetime, end_datetime, status FROM rent_items WHERE rent_id IN ($itemPlaceholders) AND LOWER(status) != 'replaced'");
      $stItems->execute($openRentIds);
      while ($ri = $stItems->fetch(PDO::FETCH_ASSOC)) {
        $itemsByRent[(int)$ri['rent_id']][] = $ri;
      }
    }

    // Include helpers_financial.php for get_billable_days
    require_once __DIR__ . "/helpers_financial.php";

    $debtByClient = array_fill_keys($clientIds, 0.0);
    foreach ($rents as $rent) {
      $clientId = (int)$rent['client_id'];
      if ($rent['status'] === 'open') {
        $rentId = (int)$rent['id'];
        $items = $itemsByRent[$rentId] ?? [];
        $liveTotal = 0.0;
        if (!empty($items)) {
          foreach ($items as $item) {
            $startStr = $item['start_datetime'] ?? $rent['start_datetime'];
            $endStr = $item['end_datetime'] ?? null;
            $rate = (float)($item['rate'] ?? 0);
            $days = get_billable_days($startStr, $endStr);
            $liveTotal += $rate * $days;
          }
        } else {
          $startStr = $rent['start_datetime'];
          $endStr = $rent['end_datetime'] ?? null;
          $rate = (float)($rent['rate'] ?? 0);
          $days = get_billable_days($startStr, $endStr);
          $liveTotal += $rate * $days;
        }
        $paid = (float)($rent['paid_amount'] ?? 0);
        $discount = (float)($rent['discount_amount'] ?? 0);
        $remaining = max($liveTotal - $discount - $paid, 0.0);
        $debtByClient[$clientId] += $remaining;
      } else {
        $remaining = (float)($rent['remaining_amount'] ?? 0);
        if ($remaining > 0) {
          $debtByClient[$clientId] += $remaining;
        }
      }
    }

    foreach ($rows as &$r) {
      $cid = (int)$r['id'];
      $r['total_debt'] = round($debtByClient[$cid] ?? 0.0, 2);
    }
  }
  respond(["success" => true, "data" => $rows, "pagination" => ["total" => $total, "page" => $page, "per_page" => $perPage]]);
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

  if ($name === "") respond(["error" => "الاسم مطلوب"], 400);

  $stChk = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR (national_id != '' AND national_id = ?) OR (phone != '' AND phone = ?)");
  $stChk->execute([$name, $national_id, $phone]);
  if ($stChk->fetch()) {
    respond(["error" => "تمت اضافة هذا العميل مسبقا"], 409);
  }

  $st = $pdo->prepare("INSERT INTO clients (name, national_id, phone, address, is_frozen, credit_limit, image_path)
                       VALUES (?,?,?,?,?,?,?)");
  $st->execute([$name, $national_id, $phone, $address, $is_frozen, $credit_limit, $image_path]);

  respond(["id" => (int)$pdo->lastInsertId()], 201);
}

/**
 * GET /clients/{id}
 */
if (preg_match('#^clients/(\d+)$#', $path, $m) && $method === "GET") {
  $id = (int)$m[1];
  $sql = "
    SELECT c.*,
           IFNULL(debt.total_debt, 0) AS total_debt
    FROM clients c
    LEFT JOIN (
      SELECT client_id, SUM(remaining_amount) AS total_debt
      FROM rents
      WHERE remaining_amount > 0
      GROUP BY client_id
    ) debt ON debt.client_id = c.id
    WHERE c.id = ?
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) respond(["error" => "العميل غير موجود"], 404);
  $row['total_debt'] = (float)($row['total_debt'] ?? 0);
  respond(["success" => true, "data" => $row]);
}

/**
 * PUT /clients/{id}
 */
if (preg_match('#^clients/(\d+)$#', $path, $m) && $method === "PUT") {
  $id = (int)$m[1];
  $in = input_all();

  // تأكد العميل موجود
  $chk = $pdo->prepare("SELECT * FROM clients WHERE id=?");
  $chk->execute([$id]);
  $currentClient = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$currentClient) respond(["error" => "العميل غير موجود"], 404);

  $newName = array_key_exists('name', $in) ? trim((string)$in['name']) : $currentClient['name'];
  $newNatId = array_key_exists('national_id', $in) ? trim((string)$in['national_id']) : $currentClient['national_id'];
  $newPhone = array_key_exists('phone', $in) ? trim((string)$in['phone']) : $currentClient['phone'];

  $stDup = $pdo->prepare("SELECT id FROM clients WHERE id != ? AND (name = ? OR (national_id != '' AND national_id = ?) OR (phone != '' AND phone = ?))");
  $stDup->execute([$id, $newName, $newNatId, $newPhone]);
  if ($stDup->fetch()) {
    respond(["error" => "تمت اضافة هذا العميل مسبقا"], 409);
  }

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

  if (empty($fields)) respond(["error" => "لا توجد حقول للتحديث"], 400);

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
    respond(["error" => "لا يمكن حذف العميل لوجود سجلات مرتبطة به"], 409);
  }

  respond(["ok" => true, "id" => $id]);
}

/**
 * GET /clients/{id}/collection-followups
 */
if (preg_match('#^clients/(\d+)/collection-followups$#', $path, $m) && $method === "GET") {
  $clientId = (int)$m[1];

  $st = $pdo->prepare("
    SELECT cf.*, u.username AS created_by_name
    FROM collection_followups cf
    LEFT JOIN users u ON cf.created_by_user_id = u.id
    WHERE cf.client_id = ?
    ORDER BY cf.created_at DESC
  ");
  $st->execute([$clientId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['rent_id'] = (int)$r['rent_id'];
    $r['rent_no'] = (int)$r['rent_id'];
    $r['client_id'] = (int)$r['client_id'];
    $r['created_by_user_id'] = $r['created_by_user_id'] !== null ? (int)$r['created_by_user_id'] : null;
  }

  respond(["data" => $rows]);
}

respond(["error" => "غير موجود"], 404);
