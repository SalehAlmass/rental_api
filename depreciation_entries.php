<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// Access check: holds either 'equipment' or 'reports' screen permission
$hasEquipment = has_permission($pdo, $auth, 'equipment');
$hasReports = has_permission($pdo, $auth, 'reports');

if (!$hasEquipment && !$hasReports) {
  respond(["error" => "ممنوع: ليس لديك الصلاحية الكافية للوصول إلى سجل الإهلاك"], 403);
}

// Immutability: No POST/PUT/DELETE allowed on this route
if ($path === "depreciation-entries" && $method === "GET") {
  $limit  = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
  $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
  
  $from        = $_GET['from'] ?? null; // YYYY-MM
  $to          = $_GET['to'] ?? null;   // YYYY-MM
  $equipmentId = isset($_GET['equipment_id']) && $_GET['equipment_id'] !== '' ? (int)$_GET['equipment_id'] : null;
  $type        = isset($_GET['depreciation_type']) && $_GET['depreciation_type'] !== '' ? trim((string)$_GET['depreciation_type']) : null;

  $conds = [];
  $params = [];

  if ($from) {
    $conds[] = "de.depreciation_month >= ?";
    $params[] = $from;
  }
  if ($to) {
    $conds[] = "de.depreciation_month <= ?";
    $params[] = $to;
  }
  if ($equipmentId !== null && $equipmentId > 0) {
    $conds[] = "de.equipment_id = ?";
    $params[] = $equipmentId;
  }
  if ($type) {
    $conds[] = "de.depreciation_type = ?";
    $params[] = $type;
  }

  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  // Get total count
  $stCount = $pdo->prepare("SELECT COUNT(*) FROM equipment_depreciation_entries de $where");
  $stCount->execute($params);
  $total = (int)$stCount->fetchColumn();

  // Get paginated entries
  $sql = "SELECT de.*, e.name AS equipment_name, e.serial_no AS equipment_serial
          FROM equipment_depreciation_entries de
          JOIN equipment e ON de.equipment_id = e.id
          $where
          ORDER BY de.depreciation_month DESC, de.id DESC
          LIMIT $limit OFFSET $offset";
  
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Format data types for response
  foreach ($rows as &$row) {
    $row['id'] = (int)$row['id'];
    $row['equipment_id'] = (int)$row['equipment_id'];
    $row['amount'] = (float)$row['amount'];
    $row['accum_before'] = (float)$row['accum_before'];
    $row['accum_after'] = (float)$row['accum_after'];
    $row['book_before'] = (float)$row['book_before'];
    $row['book_after'] = (float)$row['book_after'];
    $row['accounting_amount'] = (float)$row['accounting_amount'];
    $row['operational_amount'] = (float)$row['operational_amount'];
    $row['voucher_payment_id'] = $row['voucher_payment_id'] !== null ? (int)$row['voucher_payment_id'] : null;
  }

  respond([
    "success" => true,
    "total" => $total,
    "limit" => $limit,
    "offset" => $offset,
    "data" => $rows
  ]);
}

if ($path === "depreciation-entries") {
  respond(["error" => "طريقة الطلب غير مسموح بها"], 405);
}

respond(["error" => "غير موجود"], 404);
