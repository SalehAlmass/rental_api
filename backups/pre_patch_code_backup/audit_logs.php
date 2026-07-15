<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// Enforce security: admin or audit_logs permission required
require_permission($pdo, $auth, 'audit_logs');

// ONLY GET is allowed (Immutability: no update or delete endpoint)
if ($path === "audit-logs" && $method === "GET") {
  $limit  = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20; // Pagination security: max limit = 100
  $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
  
  $from   = $_GET['from'] ?? null;
  $to     = $_GET['to'] ?? null;
  $userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
  $entity = isset($_GET['entity']) && $_GET['entity'] !== '' ? trim((string)$_GET['entity']) : null;
  $action = isset($_GET['action']) && $_GET['action'] !== '' ? trim((string)$_GET['action']) : null;
  $search = isset($_GET['search']) && $_GET['search'] !== '' ? trim((string)$_GET['search']) : null;

  $conds = [];
  $params = [];

  if ($from) {
    $conds[] = "al.created_at >= ?";
    $params[] = $from . " 00:00:00";
  }
  if ($to) {
    $conds[] = "al.created_at <= ?";
    $params[] = $to . " 23:59:59";
  }
  if ($userId !== null && $userId > 0) {
    $conds[] = "al.user_id = ?";
    $params[] = $userId;
  }
  if ($entity) {
    $conds[] = "al.entity = ?";
    $params[] = $entity;
  }
  if ($action) {
    $conds[] = "al.action = ?";
    $params[] = $action;
  }
  if ($search) {
    $conds[] = "(al.action LIKE ? OR al.entity LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
  }

  $where = count($conds) ? ("WHERE " . implode(" AND ", $conds)) : "";

  // 1. Get total count
  $countSql = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where";
  $stCount = $pdo->prepare($countSql);
  $stCount->execute($params);
  $total = (int)$stCount->fetchColumn();

  // 2. Fetch paginated records
  $sql = "SELECT al.*, u.username AS username
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.id
          $where
          ORDER BY al.created_at DESC, al.id DESC
          LIMIT $limit OFFSET $offset";
  
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $logs = $st->fetchAll(PDO::FETCH_ASSOC);

  // Decode JSON values for representation
  foreach ($logs as &$log) {
    $log['id'] = (int)$log['id'];
    $log['user_id'] = $log['user_id'] !== null ? (int)$log['user_id'] : null;
    $log['entity_id'] = $log['entity_id'] !== null ? (int)$log['entity_id'] : null;
    
    // Decode old_values
    if ($log['old_values'] !== null) {
      $decoded = json_decode($log['old_values'], true);
      $log['old_values'] = is_array($decoded) ? $decoded : null;
    }
    // Decode new_values
    if ($log['new_values'] !== null) {
      $decoded = json_decode($log['new_values'], true);
      $log['new_values'] = is_array($decoded) ? $decoded : null;
    }
    // Decode meta
    if ($log['meta'] !== null) {
      $decoded = json_decode($log['meta'], true);
      $log['meta'] = is_array($decoded) ? $decoded : null;
    }
  }

  respond([
    "success" => true,
    "total" => $total,
    "limit" => $limit,
    "offset" => $offset,
    "data" => $logs
  ]);
}

// Any other method or path on /audit-logs is not allowed (405 Method Not Allowed / 404 Not Found)
if ($path === "audit-logs") {
  respond(["error" => "طريقة الطلب غير مسموح بها"], 405);
}

respond(["error" => "غير موجود"], 404);
