<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth = require_auth();
$path = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo = db();

// Health and integrity screens are strictly admin-only
if (strtolower((string)($auth["role"] ?? "")) !== "admin") {
  respond(["error" => "ممنوع: هذه لوحة إدارية فقط"], 403);
}

/*
|--------------------------------------------------------------------------
| GET system-health
|--------------------------------------------------------------------------
*/
if ($path === "system-health" && $method === "GET") {
  $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

  // 1. DB connection and size
  $dbStatus = 'connected';
  $dbSizeMb = 0.0;
  $tablesCount = 0;
  try {
    $st = $pdo->prepare("
      SELECT 
        COUNT(*) as count,
        SUM(DATA_LENGTH + INDEX_LENGTH) as size_bytes
      FROM information_schema.TABLES 
      WHERE TABLE_SCHEMA = DATABASE()
    ");
    $st->execute();
    $dbInfo = $st->fetch();
    $tablesCount = (int)($dbInfo['count'] ?? 0);
    $dbSizeBytes = (float)($dbInfo['size_bytes'] ?? 0);
    $dbSizeMb = round($dbSizeBytes / (1024 * 1024), 2);
  } catch (Throwable $e) {
    $dbStatus = 'error';
  }

  // 2. Disk space
  $diskFreeGb = 0.0;
  try {
    $freeBytes = @disk_free_space(__DIR__);
    if ($freeBytes !== false) {
      $diskFreeGb = round($freeBytes / (1024 * 1024 * 1024), 2);
    }
  } catch (Throwable $ignore) {}

  // 3. Required PHP Extensions status
  $requiredExtensions = ['pdo_mysql', 'openssl', 'json', 'mbstring', 'zip'];
  $extensionsStatus = [];
  foreach ($requiredExtensions as $ext) {
    $extensionsStatus[$ext] = extension_loaded($ext);
  }

  // 4. Last Backup Details
  $lastBackup = null;
  $lastSuccessfulBackupDate = null;
  try {
    $st = $pdo->query("SELECT file_name, status, created_at FROM backup_logs ORDER BY created_at DESC LIMIT 1");
    $lastBackup = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $stSuccess = $pdo->query("SELECT MAX(created_at) FROM backup_logs WHERE status='success'");
    $lastSuccessfulBackupDate = $stSuccess->fetchColumn() ?: null;
  } catch (Throwable $ignore) {}

  // 5. Last System Error
  $lastError = null;
  try {
    $st = $pdo->query("SELECT api, error_message, created_at FROM system_errors ORDER BY created_at DESC LIMIT 1");
    $lastError = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $ignore) {}

  // 6. Active sessions count
  $activeSessionsCount = 0;
  try {
    $st = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE expires_at > NOW()");
    $activeSessionsCount = (int)$st->fetchColumn();
  } catch (Throwable $ignore) {}

  // Calculate API response time
  $endTime = microtime(true);
  $responseTimeMs = (int)round(($endTime - $startTime) * 1000);

  respond([
    "success" => true,
    "data" => [
      "database" => [
        "status" => $dbStatus,
        "tables_count" => $tablesCount,
        "size_mb" => $dbSizeMb
      ],
      "system" => [
        "php_version" => PHP_VERSION,
        "disk_free_gb" => $diskFreeGb,
        "extensions" => $extensionsStatus,
        "api_status" => "online",
        "api_response_time_ms" => $responseTimeMs
      ],
      "backup" => [
        "last_backup" => $lastBackup,
        "last_successful_backup_date" => $lastSuccessfulBackupDate
      ],
      "errors" => [
        "last_error" => $lastError
      ],
      "active_users" => [
        "sessions_count" => $activeSessionsCount
      ]
    ]
  ], 200);
}

/*
|--------------------------------------------------------------------------
| GET system-integrity
|--------------------------------------------------------------------------
*/
if ($path === "system-integrity" && $method === "GET") {
  $issues = [];

  // 1. Contracts without Client
  try {
    $rows = $pdo->query("
      SELECT r.id, r.contract_number 
      FROM rents r 
      LEFT JOIN clients c ON r.client_id = c.id 
      WHERE c.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "contracts",
        "issue" => "عقد بدون عميل مرتبط",
        "details" => "رقم العقد: " . ($r['contract_number'] ?? 'مجهول') . " (ID: {$r['id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص عقود بدون عميل: " . $e->getMessage(), "details" => ""];
  }

  // 2. Contracts without Equipment Items
  try {
    $rows = $pdo->query("
      SELECT r.id, r.contract_number 
      FROM rents r 
      LEFT JOIN rent_items ri ON r.id = ri.rent_id 
      WHERE ri.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "contracts",
        "issue" => "عقد تأجير لا يحتوي على بنود معدات",
        "details" => "رقم العقد: " . ($r['contract_number'] ?? 'مجهول') . " (ID: {$r['id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص عقود بدون معدات: " . $e->getMessage(), "details" => ""];
  }

  // 3. Payments without Contract
  try {
    $rows = $pdo->query("
      SELECT p.id, p.payment_number, p.rent_id 
      FROM payments p 
      LEFT JOIN rents r ON p.rent_id = r.id 
      WHERE p.rent_id IS NOT NULL AND r.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "payments",
        "issue" => "سند مرتبط بعقد غير موجود بالقاعدة",
        "details" => "رقم السند: " . ($r['payment_number'] ?? 'مجهول') . " (ID: {$r['id']}, العقد المفترض: {$r['rent_id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص سندات بدون عقد: " . $e->getMessage(), "details" => ""];
  }

  // 4. Payments with invalid amounts (<= 0)
  try {
    $rows = $pdo->query("
      SELECT id, payment_number, amount 
      FROM payments 
      WHERE amount <= 0 AND (is_void = 0 OR is_void IS NULL)
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "payments",
        "issue" => "سند مالي بقيمة سالبة أو صفرية",
        "details" => "رقم السند: " . ($r['payment_number'] ?? 'مجهول') . " (المبلغ: {$r['amount']}, ID: {$r['id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص قيم السندات: " . $e->getMessage(), "details" => ""];
  }

  // 5. Equipment with invalid status
  try {
    $rows = $pdo->query("
      SELECT id, name, status 
      FROM equipment 
      WHERE status NOT IN ('available', 'rented', 'maintenance')
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "equipment",
        "issue" => "معدة بحالة تشغيلية غير صالحة بالنظام",
        "details" => "اسم المعدة: {$r['name']} (الحالة الحالية: '{$r['status']}', ID: {$r['id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص حالات المعدات: " . $e->getMessage(), "details" => ""];
  }

  // 6. Equipment with depreciation exceeding purchase price
  try {
    $rows = $pdo->query("
      SELECT id, name, purchase_price, depreciation_accumulated 
      FROM equipment 
      WHERE depreciation_accumulated > purchase_price
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "equipment",
        "issue" => "الإهلاك المتراكم يتجاوز القيمة الأصلية لشراء المعدة",
        "details" => "اسم المعدة: {$r['name']} (قيمة الشراء: {$r['purchase_price']}, الإهلاك: {$r['depreciation_accumulated']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص إهلاك المعدات: " . $e->getMessage(), "details" => ""];
  }

  // 7. Attendance checkin without checkout exceeding 24 hours
  try {
    $rows = $pdo->query("
      SELECT l.id, l.user_id, u.username, l.ts 
      FROM attendance_logs l 
      JOIN users u ON l.user_id = u.id 
      WHERE l.type='in' 
        AND l.ts < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        AND NOT EXISTS (
          SELECT 1 FROM attendance_logs out_log 
          WHERE out_log.user_id = l.user_id 
            AND out_log.ts > l.ts 
            AND out_log.type = 'out'
        )
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "attendance",
        "issue" => "تسجيل حضور مستمر بدون تسجيل انصراف لأكثر من 24 ساعة",
        "details" => "الموظف: {$r['username']} (تاريخ الحضور: {$r['ts']}, ID السجل: {$r['id']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص حضور معلق: " . $e->getMessage(), "details" => ""];
  }

  // 8. Attendance duplicate checkins on the same day
  try {
    $rows = $pdo->query("
      SELECT l1.user_id, u.username, DATE(l1.ts) as date_val, COUNT(*) as count_val 
      FROM attendance_logs l1 
      JOIN users u ON l1.user_id = u.id
      WHERE l1.type='in' 
      GROUP BY l1.user_id, DATE(l1.ts) 
      HAVING count_val > 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $issues[] = [
        "component" => "attendance",
        "issue" => "تسجيلات حضور متعددة مكررة في نفس اليوم للموظف",
        "details" => "الموظف: {$r['username']} (تاريخ التكرار: {$r['date_val']}, عدد التكرارات: {$r['count_val']})"
      ];
    }
  } catch (Throwable $e) {
    $issues[] = ["component" => "system", "issue" => "فشل فحص حضور مكرر: " . $e->getMessage(), "details" => ""];
  }

  respond([
    "success" => true,
    "issues" => $issues
  ], 200);
}

/*
|--------------------------------------------------------------------------
| GET system-health/errors
|--------------------------------------------------------------------------
*/
if ($path === "system-health/errors" && $method === "GET") {
  $statusFilter = trim((string)($_GET['status'] ?? ''));
  $where = '';
  $params = [];
  if ($statusFilter === 'open') {
    $where = "WHERE status = 'open'";
  } elseif ($statusFilter === 'resolved') {
    $where = "WHERE status = 'resolved'";
  }
  $st = $pdo->prepare("SELECT id, user_id, api, error_message, stack_trace, status, resolved_at, created_at FROM system_errors {$where} ORDER BY id DESC LIMIT 50");
  $st->execute($params);
  $errors = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($errors as &$err) {
    $cat = categorize_error($err['error_message'] ?? '');
    $err['title_ar'] = $cat['title_ar'];
    $err['cause_ar'] = $cat['cause_ar'];
    $err['severity'] = $cat['severity'];
    $err['suggested_action_ar'] = $cat['suggested_action_ar'];
  }
  // Counts for filter badges
  $counts = [];
  $cst = $pdo->query("SELECT status, COUNT(*) as cnt FROM system_errors GROUP BY status");
  foreach ($cst->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $counts[$c['status']] = (int)$c['cnt'];
  }
  respond([
    "success" => true,
    "data" => $errors,
    "counts" => [
      "total" => ($counts['open'] ?? 0) + ($counts['resolved'] ?? 0),
      "open" => $counts['open'] ?? 0,
      "resolved" => $counts['resolved'] ?? 0,
    ]
  ], 200);
}

respond(["error" => "غير موجود"], 404);
