<?php
declare(strict_types = 1)
;

function json_in(): array
{
  $raw = file_get_contents("php://input");
  $data = json_decode($raw ?: "{}", true);
  return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void
{
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ------------------------------------------------------------
// Schema helpers (MySQL) - backward compatible auto-migration
// ------------------------------------------------------------

function _col_exists(PDO $pdo, string $table, string $column): bool
{
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                        AND COLUMN_NAME = ?");
  $st->execute([$table, $column]);
  return ((int)$st->fetchColumn()) > 0;
}

function _index_exists(PDO $pdo, string $table, string $indexName): bool
{
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                        AND INDEX_NAME = ?");
  $st->execute([$table, $indexName]);
  return ((int)$st->fetchColumn()) > 0;
}

function ensure_column(PDO $pdo, string $table, string $column, string $ddl): void
{
  if (_col_exists($pdo, $table, $column))
    return;
  $pdo->exec($ddl);
}

function ensure_index(PDO $pdo, string $table, string $indexName, string $ddl): void
{
  if (_index_exists($pdo, $table, $indexName))
    return;
  $pdo->exec($ddl);
}

function ensure_table(PDO $pdo, string $table, string $ddl): void
{
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.TABLES
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?");
  $st->execute([$table]);
  if (((int)$st->fetchColumn()) > 0)
    return;
  $pdo->exec($ddl);
}

/**
 * Ensures new columns / tables used by the improved contract payment flow.
 * Safe to call per-request.
 */
function ensure_financials_schema(PDO $pdo): void
{
  // users: permission overrides for contract closing flow
  ensure_column($pdo, 'users', 'permissions_json', "ALTER TABLE users ADD COLUMN permissions_json LONGTEXT NULL");

  // rents: financial state (single source of truth)
  ensure_column($pdo, 'rents', 'paid_amount', "ALTER TABLE rents ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'remaining_amount', "ALTER TABLE rents ADD COLUMN remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'is_paid', "ALTER TABLE rents ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'paid_at', "ALTER TABLE rents ADD COLUMN paid_at DATETIME NULL");

  // rents: closing audit trail
  ensure_column($pdo, 'rents', 'closed_at', "ALTER TABLE rents ADD COLUMN closed_at DATETIME NULL");
  ensure_column($pdo, 'rents', 'closed_by_user_id', "ALTER TABLE rents ADD COLUMN closed_by_user_id INT NULL");
  ensure_column($pdo, 'rents', 'closing_paid_amount', "ALTER TABLE rents ADD COLUMN closing_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'closing_payment_method', "ALTER TABLE rents ADD COLUMN closing_payment_method VARCHAR(30) NULL");
  ensure_column($pdo, 'rents', 'closing_payment_status', "ALTER TABLE rents ADD COLUMN closing_payment_status VARCHAR(30) NULL");
  ensure_column($pdo, 'rents', 'closing_payment_id', "ALTER TABLE rents ADD COLUMN closing_payment_id INT NULL");
  ensure_column($pdo, 'rents', 'pricing_rule_code', "ALTER TABLE rents ADD COLUMN pricing_rule_code VARCHAR(40) NULL");
  ensure_column($pdo, 'rents', 'pricing_rule_label', "ALTER TABLE rents ADD COLUMN pricing_rule_label VARCHAR(120) NULL");
  ensure_column($pdo, 'rents', 'pricing_rule_applied', "ALTER TABLE rents ADD COLUMN pricing_rule_applied TINYINT(1) NOT NULL DEFAULT 0");

  // payments: links and idempotency
  ensure_column($pdo, 'payments', 'equipment_id', "ALTER TABLE payments ADD COLUMN equipment_id INT NULL");
  ensure_index($pdo, 'payments', 'idx_payments_equipment', "CREATE INDEX idx_payments_equipment ON payments (equipment_id)");
  ensure_column($pdo, 'payments', 'idempotency_key', "ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(80) NULL");
  ensure_index($pdo, 'payments', 'uniq_payments_idem_key', "CREATE UNIQUE INDEX uniq_payments_idem_key ON payments (idempotency_key)");

  // app settings
  ensure_table($pdo, 'app_settings', "CREATE TABLE app_settings (
      setting_key VARCHAR(80) PRIMARY KEY,
      setting_value TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // audit log
  ensure_table($pdo, 'audit_logs', "CREATE TABLE audit_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      action VARCHAR(64) NOT NULL,
      entity VARCHAR(64) NULL,
      entity_id INT NULL,
      meta JSON NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // collection follow-up log for overdue/deferred contracts
  ensure_table($pdo, 'collection_followups', "CREATE TABLE collection_followups (
      id INT AUTO_INCREMENT PRIMARY KEY,
      rent_id INT NOT NULL,
      client_id INT NOT NULL,
      contact_type VARCHAR(30) NOT NULL,
      outcome VARCHAR(40) NULL,
      note TEXT NULL,
      next_followup_at DATETIME NULL,
      created_by_user_id INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_collection_followups_rent_id (rent_id),
      INDEX idx_collection_followups_client_id (client_id),
      INDEX idx_collection_followups_next_followup_at (next_followup_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  ensure_column($pdo, 'collection_followups', 'created_by_user_id', "ALTER TABLE collection_followups ADD COLUMN created_by_user_id INT NULL");
}

function setting_get(PDO $pdo, string $key, ?string $default = null): ?string
{
  $st = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  $st->execute([$key]);
  $v = $st->fetchColumn();
  return $v === false ? $default : ($v === null ? $default : (string)$v);
}

function setting_set(PDO $pdo, string $key, ?string $value): void
{
  $st = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
  $st->execute([$key, $value]);
}

function audit_log(PDO $pdo, string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
{
  $userId = null;
  try {
    if (function_exists('auth_user')) {
      $u = auth_user();
      if (is_array($u) && isset($u['id']))
        $userId = (int)$u['id'];
    }
  }
  catch (Throwable $e) {
    $userId = null;
  }

  $st = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, meta)
                       VALUES (?,?,?,?,?)");
  $st->execute([
    $userId,
    $action,
    $entity,
    $entityId,
    empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE)
  ]);
}

function b64url_enc(string $s): string
{
  return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_dec(string $s): string
{
  $s = strtr($s, '-_', '+/');
  return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

function jwt_sign(array $payload, string $secret): string
{
  $header = ["alg" => "HS256", "typ" => "JWT"];
  $h = b64url_enc(json_encode($header));
  $p = b64url_enc(json_encode($payload));
  $sig = b64url_enc(hash_hmac('sha256', "$h.$p", $secret, true));
  return "$h.$p.$sig";
}

function jwt_verify(string $token, string $secret): ?array
{
  $parts = explode('.', $token);
  if (count($parts) !== 3)
    return null;
  [$h, $p, $s] = $parts;
  $sig = b64url_enc(hash_hmac('sha256', "$h.$p", $secret, true));
  if (!hash_equals($sig, $s))
    return null;
  $payload = json_decode(b64url_dec($p), true);
  return is_array($payload) ? $payload : null;
}
function require_auth(): array
{
  global $JWT_SECRET;

  $hdr = "";

  // 1) الطريقة المعتادة
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'];
  }

  // 2) بعض إعدادات Apache
  if ($hdr === "" && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  }

  // 3) getallheaders (لو متاحة)
  if ($hdr === "" && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (!empty($headers['Authorization']))
      $hdr = $headers['Authorization'];
    if ($hdr === "" && !empty($headers['authorization']))
      $hdr = $headers['authorization'];
  }

  if (!preg_match('/Bearer\s+(.*)$/i', $hdr, $m))
    respond(["error" => "Unauthorized"], 401);

  $payload = jwt_verify(trim($m[1]), $JWT_SECRET);
  if (!$payload)
    respond(["error" => "Invalid token"], 401);
  if (isset($payload["exp"]) && time() > (int)$payload["exp"])
    respond(["error" => "Token expired"], 401);

  return $payload;
}


function default_user_permissions(?string $role = null): array
{
  $role = strtolower(trim((string)$role));
  if ($role === 'admin') {
    return [
      'contract_hour_pricing_mode' => 'ask',
      'contract_payment_receipt_mode' => 'ask',
    ];
  }
  return [
    'contract_hour_pricing_mode' => 'inherit',
    'contract_payment_receipt_mode' => 'inherit',
  ];
}

function normalize_user_permissions($raw, ?string $role = null): array
{
  $defaults = default_user_permissions($role);
  if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $raw = $decoded;
  }
  if (!is_array($raw)) $raw = [];

  $hourMode = strtolower(trim((string)($raw['contract_hour_pricing_mode'] ?? $defaults['contract_hour_pricing_mode'])));
  $receiptMode = strtolower(trim((string)($raw['contract_payment_receipt_mode'] ?? $defaults['contract_payment_receipt_mode'])));

  if (!in_array($hourMode, ['inherit', 'auto', 'ask'], true)) {
    $hourMode = $defaults['contract_hour_pricing_mode'];
  }
  if (!in_array($receiptMode, ['inherit', 'auto', 'ask'], true)) {
    $receiptMode = $defaults['contract_payment_receipt_mode'];
  }

  return [
    'contract_hour_pricing_mode' => $hourMode,
    'contract_payment_receipt_mode' => $receiptMode,
  ];
}

function effective_contract_closing_modes(PDO $pdo, ?array $user = null): array
{
  $globalHour = setting_get($pdo, 'contract.hour_pricing_mode', 'ask') ?? 'ask';
  $globalReceipt = setting_get($pdo, 'contract.payment_receipt_mode', 'auto') ?? 'auto';

  $role = strtolower(trim((string)($user['role'] ?? 'employee')));
  $permissions = normalize_user_permissions($user['permissions_json'] ?? ($user['permissions'] ?? null), $role);

  $hourMode = $permissions['contract_hour_pricing_mode'] === 'inherit'
    ? $globalHour
    : $permissions['contract_hour_pricing_mode'];
  $receiptMode = $permissions['contract_payment_receipt_mode'] === 'inherit'
    ? $globalReceipt
    : $permissions['contract_payment_receipt_mode'];

  return [
    'hour_pricing_mode' => $hourMode,
    'payment_receipt_mode' => $receiptMode,
    'global_hour_pricing_mode' => $globalHour,
    'global_payment_receipt_mode' => $globalReceipt,
    'permissions' => $permissions,
  ];
}

function ensure_depreciation_schema(PDO $pdo): void
{
  ensure_column($pdo, 'equipment', 'purchase_price', "ALTER TABLE equipment ADD COLUMN purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER hourly_rate");
  ensure_column($pdo, 'equipment', 'salvage_value', "ALTER TABLE equipment ADD COLUMN salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER purchase_price");
  ensure_column($pdo, 'equipment', 'useful_life_months', "ALTER TABLE equipment ADD COLUMN useful_life_months INT NOT NULL DEFAULT 60 AFTER salvage_value");
  ensure_column($pdo, 'equipment', 'depreciation_start_date', "ALTER TABLE equipment ADD COLUMN depreciation_start_date DATE NULL AFTER useful_life_months");
  ensure_column($pdo, 'equipment', 'depreciation_monthly', "ALTER TABLE equipment ADD COLUMN depreciation_monthly DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER depreciation_rate");
  ensure_column($pdo, 'equipment', 'depreciation_accumulated', "ALTER TABLE equipment ADD COLUMN depreciation_accumulated DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER depreciation_monthly");
  ensure_column($pdo, 'equipment', 'book_value', "ALTER TABLE equipment ADD COLUMN book_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER depreciation_accumulated");
  ensure_column($pdo, 'equipment', 'estimated_usage_days', "ALTER TABLE equipment ADD COLUMN estimated_usage_days INT NOT NULL DEFAULT 365 AFTER book_value");
  ensure_column($pdo, 'equipment', 'operational_depreciation_per_day', "ALTER TABLE equipment ADD COLUMN operational_depreciation_per_day DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estimated_usage_days");
  ensure_column($pdo, 'equipment', 'operational_depreciation_accumulated', "ALTER TABLE equipment ADD COLUMN operational_depreciation_accumulated DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER operational_depreciation_per_day");
  ensure_column($pdo, 'equipment', 'last_depreciation_month', "ALTER TABLE equipment ADD COLUMN last_depreciation_month CHAR(7) NULL AFTER operational_depreciation_accumulated");

  ensure_table($pdo, 'equipment_depreciation_entries', "CREATE TABLE equipment_depreciation_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    depreciation_month CHAR(7) NOT NULL,
    accounting_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    operational_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    voucher_payment_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_equipment_dep_month (equipment_id, depreciation_month),
    INDEX idx_equipment_dep_month (depreciation_month)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function depreciation_compute_values(array $row): array
{
  $purchase = isset($row['purchase_price']) ? (float)$row['purchase_price'] : 0.0;
  $salvage = isset($row['salvage_value']) ? (float)$row['salvage_value'] : 0.0;
  $lifeMonths = max(1, (int)($row['useful_life_months'] ?? 60));
  $estimatedUsageDays = max(1, (int)($row['estimated_usage_days'] ?? 365));
  $base = max($purchase - $salvage, 0.0);
  $monthly = round($base / $lifeMonths, 2);
  $perDay = round($base / $estimatedUsageDays, 2);
  $accountingAccum = isset($row['depreciation_accumulated']) ? (float)$row['depreciation_accumulated'] : 0.0;
  $bookValue = max($purchase - $accountingAccum, $salvage, 0.0);
  return [
    'purchase_price' => $purchase,
    'salvage_value' => $salvage,
    'useful_life_months' => $lifeMonths,
    'estimated_usage_days' => $estimatedUsageDays,
    'depreciation_monthly' => $monthly,
    'operational_depreciation_per_day' => $perDay,
    'book_value' => round($bookValue, 2),
  ];
}

function update_equipment_depreciation_snapshot(PDO $pdo, int $equipmentId): void
{
  ensure_depreciation_schema($pdo);
  $st = $pdo->prepare("SELECT * FROM equipment WHERE id=? LIMIT 1");
  $st->execute([$equipmentId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return;
  $vals = depreciation_compute_values($row);
  $upd = $pdo->prepare("UPDATE equipment SET depreciation_monthly=?, operational_depreciation_per_day=?, book_value=? WHERE id=?");
  $upd->execute([$vals['depreciation_monthly'], $vals['operational_depreciation_per_day'], $vals['book_value'], $equipmentId]);
}

function process_monthly_depreciation(PDO $pdo): void
{
  ensure_financials_schema($pdo);
  ensure_depreciation_schema($pdo);
  $month = date('Y-m');
  $startMonth = date('Y-m-01 00:00:00');
  $endMonth = date('Y-m-t 23:59:59');
  $rows = $pdo->query("SELECT * FROM equipment WHERE COALESCE(is_active,1)=1")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    $equipmentId = (int)$row['id'];
    $startDate = $row['depreciation_start_date'] ?? null;
    if (!$startDate || substr((string)$startDate, 0, 7) > $month) {
      update_equipment_depreciation_snapshot($pdo, $equipmentId);
      continue;
    }

    $chk = $pdo->prepare("SELECT id FROM equipment_depreciation_entries WHERE equipment_id=? AND depreciation_month=? LIMIT 1");
    $chk->execute([$equipmentId, $month]);
    if ($chk->fetchColumn()) {
      update_equipment_depreciation_snapshot($pdo, $equipmentId);
      continue;
    }

    $vals = depreciation_compute_values($row);
    $accountingAmount = (float)$vals['depreciation_monthly'];

    $stDays = $pdo->prepare("SELECT COUNT(DISTINCT DATE(COALESCE(closed_at, end_datetime, start_datetime)))
                             FROM rents
                             WHERE equipment_id=?
                               AND start_datetime <= ?
                               AND COALESCE(closed_at, end_datetime, start_datetime) >= ?");
    $stDays->execute([$equipmentId, $endMonth, $startMonth]);
    $daysUsed = (int)$stDays->fetchColumn();
    $operationalAmount = round($daysUsed * (float)$vals['operational_depreciation_per_day'], 2);

    $pdo->beginTransaction();
    try {
      $paymentId = null;
      if ($accountingAmount > 0.0001) {
        $idem = 'dep_' . $equipmentId . '_' . $month;
        $stPay = $pdo->prepare("SELECT id FROM payments WHERE idempotency_key=? LIMIT 1");
        $stPay->execute([$idem]);
        $paymentId = $stPay->fetchColumn();
        if (!$paymentId) {
          $insPay = $pdo->prepare("INSERT INTO payments (type, amount, client_id, rent_id, equipment_id, method, reference_no, notes, user_id, is_void, idempotency_key, created_at)
                                   VALUES ('depreciation', ?, NULL, NULL, ?, 'system', ?, ?, NULL, 0, ?, NOW())");
          $insPay->execute([
            $accountingAmount,
            $equipmentId,
            'DEP-' . $month . '-' . $equipmentId,
            'قيد إهلاك شهري تلقائي للمعدة #' . $equipmentId . ' عن شهر ' . $month,
            $idem,
          ]);
          $paymentId = (int)$pdo->lastInsertId();
        }
      }

      $ins = $pdo->prepare("INSERT INTO equipment_depreciation_entries (equipment_id, depreciation_month, accounting_amount, operational_amount, voucher_payment_id)
                            VALUES (?,?,?,?,?)");
      $ins->execute([$equipmentId, $month, $accountingAmount, $operationalAmount, $paymentId ?: null]);

      $newAccum = min((float)($row['depreciation_accumulated'] ?? 0) + $accountingAmount, max((float)$vals['purchase_price'] - (float)$vals['salvage_value'], 0.0));
      $newOperationalAccum = (float)($row['operational_depreciation_accumulated'] ?? 0) + $operationalAmount;
      $newBook = max((float)$vals['purchase_price'] - $newAccum, (float)$vals['salvage_value'], 0.0);
      $upd = $pdo->prepare("UPDATE equipment SET depreciation_monthly=?, operational_depreciation_per_day=?, depreciation_accumulated=?, operational_depreciation_accumulated=?, book_value=?, last_depreciation_month=? WHERE id=?");
      $upd->execute([$vals['depreciation_monthly'], $vals['operational_depreciation_per_day'], round($newAccum,2), round($newOperationalAccum,2), round($newBook,2), $month, $equipmentId]);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
    }
  }
}
