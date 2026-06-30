<?php
declare(strict_types=1);

function json_in(): array {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw ?: "{}", true);
  return is_array($data) ? $data : [];
}

function respond($data, int $code=200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ------------------------------------------------------------
// Schema helpers (MySQL) - backward compatible auto-migration
// ------------------------------------------------------------

function _col_exists(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                        AND COLUMN_NAME = ?");
  $st->execute([$table, $column]);
  return ((int)$st->fetchColumn()) > 0;
}

function _index_exists(PDO $pdo, string $table, string $indexName): bool {
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                        AND INDEX_NAME = ?");
  $st->execute([$table, $indexName]);
  return ((int)$st->fetchColumn()) > 0;
}

function ensure_column(PDO $pdo, string $table, string $column, string $ddl): void {
  if (_col_exists($pdo, $table, $column)) return;
  $pdo->exec($ddl);
}

function ensure_index(PDO $pdo, string $table, string $indexName, string $ddl): void {
  if (_index_exists($pdo, $table, $indexName)) return;
  $pdo->exec($ddl);
}

function ensure_table(PDO $pdo, string $table, string $ddl): void {
  $st = $pdo->prepare("SELECT COUNT(*)
                      FROM INFORMATION_SCHEMA.TABLES
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?");
  $st->execute([$table]);
  if (((int)$st->fetchColumn()) > 0) return;
  $pdo->exec($ddl);
}

/**
 * Ensures new columns / tables used by the improved contract payment flow.
 * Safe to call per-request.
 */
function ensure_financials_schema(PDO $pdo): void {
  // rents: financial state (single source of truth)
  ensure_column($pdo, 'rents', 'paid_amount', "ALTER TABLE rents ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'remaining_amount', "ALTER TABLE rents ADD COLUMN remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'is_paid', "ALTER TABLE rents ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'paid_at', "ALTER TABLE rents ADD COLUMN paid_at DATETIME NULL");
  ensure_column($pdo, 'rents', 'discount_amount', "ALTER TABLE rents ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
  ensure_column($pdo, 'rents', 'discount_note', "ALTER TABLE rents ADD COLUMN discount_note TEXT NULL");

  // rents: archive support (soft-hide closed contracts without deleting)
  ensure_column($pdo, 'rents', 'archived_at', "ALTER TABLE rents ADD COLUMN archived_at DATETIME NULL");

  // payments: idempotency
  ensure_column($pdo, 'payments', 'idempotency_key', "ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(80) NULL");
  // unique when key present (same key cannot be inserted twice)
  ensure_index($pdo, 'payments', 'uniq_payments_idem_key', "CREATE UNIQUE INDEX uniq_payments_idem_key ON payments (idempotency_key)");
  ensure_index($pdo, 'payments', 'idx_payments_user_date_void', "CREATE INDEX idx_payments_user_date_void ON payments (user_id, created_at, is_void)");

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

  ensure_column($pdo, 'audit_logs', 'old_values', "ALTER TABLE audit_logs ADD COLUMN old_values JSON NULL AFTER entity_id");
  ensure_column($pdo, 'audit_logs', 'new_values', "ALTER TABLE audit_logs ADD COLUMN new_values JSON NULL AFTER old_values");

  // rent_items: multiple equipment per rent
  ensure_table($pdo, 'rent_items', "CREATE TABLE rent_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      rent_id INT NOT NULL,
      equipment_id INT NOT NULL,
      rate DECIMAL(12,2) NOT NULL DEFAULT 0,
      notes TEXT NULL,
      status VARCHAR(32) NOT NULL DEFAULT 'open',
      start_datetime DATETIME NULL,
      end_datetime DATETIME NULL,
      replaced_by_id INT NULL,
      INDEX(rent_id),
      INDEX(equipment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // auto-migrate existing single-equipment rents into rent_items
  try {
    $stCount = $pdo->query("SELECT COUNT(*) FROM rent_items");
    if (((int)$stCount->fetchColumn()) === 0) {
      $pdo->exec("
        INSERT INTO rent_items (rent_id, equipment_id, rate, notes, status, start_datetime, end_datetime)
        SELECT id, equipment_id, rate, notes, status, start_datetime, end_datetime
        FROM rents
        WHERE equipment_id IS NOT NULL AND equipment_id > 0
      ");
    }
  } catch (Throwable $e) {}

  // collection_followups: متابعات التحصيل
  ensure_table($pdo, 'collection_followups', "CREATE TABLE collection_followups (
      id INT AUTO_INCREMENT PRIMARY KEY,
      rent_id INT NOT NULL,
      client_id INT NOT NULL,
      created_by_user_id INT NULL,
      contact_type VARCHAR(32) NOT NULL DEFAULT 'call',
      outcome VARCHAR(64) NULL,
      note TEXT NULL,
      next_followup_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX(rent_id),
      INDEX(client_id),
      INDEX(created_by_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Update closed contracts that have NULL closed_at to have closed_at = end_datetime
  try {
    $pdo->exec("UPDATE rents SET closed_at = end_datetime WHERE status = 'closed' AND closed_at IS NULL");
  } catch (Throwable $e) {}

  // Run database indexing checks to optimize queries and ensure concurrency stability
  ensure_performance_indexes($pdo);
  ensure_enterprise_schema($pdo);
}

function ensure_performance_indexes(PDO $pdo): void {
  // 1. rents indexes
  ensure_index($pdo, 'rents', 'idx_rents_client_id', "CREATE INDEX idx_rents_client_id ON rents (client_id)");
  ensure_index($pdo, 'rents', 'idx_rents_status', "CREATE INDEX idx_rents_status ON rents (status)");
  ensure_index($pdo, 'rents', 'idx_rents_created_at', "CREATE INDEX idx_rents_created_at ON rents (created_at)");

  // 2. payments indexes
  ensure_index($pdo, 'payments', 'idx_payments_client_id', "CREATE INDEX idx_payments_client_id ON payments (client_id)");
  ensure_index($pdo, 'payments', 'idx_payments_rent_id', "CREATE INDEX idx_payments_rent_id ON payments (rent_id)");
  ensure_index($pdo, 'payments', 'idx_payments_user_id', "CREATE INDEX idx_payments_user_id ON payments (user_id)");
  ensure_index($pdo, 'payments', 'idx_payments_created_at', "CREATE INDEX idx_payments_created_at ON payments (created_at)");
  ensure_index($pdo, 'payments', 'idx_payments_user_created', "CREATE INDEX idx_payments_user_created ON payments (user_id, created_at)");

  // 3. audit_logs indexes
  ensure_index($pdo, 'audit_logs', 'idx_audit_logs_user_id', "CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id)");
  ensure_index($pdo, 'audit_logs', 'idx_audit_logs_created_at', "CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)");
  ensure_index($pdo, 'audit_logs', 'idx_audit_logs_entity_id', "CREATE INDEX idx_audit_logs_entity_id ON audit_logs (entity, entity_id)");
  ensure_index($pdo, 'audit_logs', 'idx_audit_logs_entity', "CREATE INDEX idx_audit_logs_entity ON audit_logs (entity)");
  ensure_index($pdo, 'audit_logs', 'idx_audit_logs_action', "CREATE INDEX idx_audit_logs_action ON audit_logs (action)");

  // 4. clients indexes
  ensure_index($pdo, 'clients', 'idx_clients_phone', "CREATE INDEX idx_clients_phone ON clients (phone)");
  ensure_index($pdo, 'clients', 'idx_clients_national_id', "CREATE INDEX idx_clients_national_id ON clients (national_id)");
  ensure_index($pdo, 'clients', 'idx_clients_name', "CREATE INDEX idx_clients_name ON clients (name)");

  // 5. Additional payments indexes for financial reports
  ensure_index($pdo, 'payments', 'idx_payments_type', "CREATE INDEX idx_payments_type ON payments (type)");
  ensure_index($pdo, 'payments', 'idx_payments_method', "CREATE INDEX idx_payments_method ON payments (method)");
  ensure_index($pdo, 'payments', 'idx_payments_is_void', "CREATE INDEX idx_payments_is_void ON payments (is_void)");
  ensure_index($pdo, 'payments', 'idx_payments_type_method', "CREATE INDEX idx_payments_type_method ON payments (type, method)");

  // 6. Rents indexes for financial reports
  ensure_index($pdo, 'rents', 'idx_rents_status_created', "CREATE INDEX idx_rents_status_created ON rents (status, created_at)");

  // 7. Attendance logs indexes for performance
  ensure_index($pdo, 'attendance_logs', 'idx_attendance_user_ts', "CREATE INDEX idx_attendance_user_ts ON attendance_logs (user_id, ts)");
  ensure_index($pdo, 'attendance_logs', 'idx_attendance_type', "CREATE INDEX idx_attendance_type ON attendance_logs (type)");
}

function audit_log(
  PDO $pdo,
  string $action,
  ?string $entity = null,
  ?int $entityId = null,
  ?array $oldValues = null,
  ?array $newValues = null,
  array $meta = []
): void {
  $userId = null;
  try {
    if (function_exists('auth_user')) {
      $u = auth_user();
      if (is_array($u) && isset($u['id'])) $userId = (int)$u['id'];
    }
  } catch (Throwable $e) {
    $userId = null;
  }

  // Sensitive data protection: redact passwords, password hashes, tokens, auth secrets
  $sensitiveKeys = ['password', 'password_hash', 'token', 'auth_secret', 'secret'];
  $redact = function($arr) use (&$redact, $sensitiveKeys) {
    if ($arr === null) return null;
    if (!is_array($arr)) return $arr;
    $res = [];
    foreach ($arr as $key => $val) {
      if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
        $res[$key] = '[REDACTED]';
      } elseif (is_array($val)) {
        $res[$key] = $redact($val);
      } else {
        $res[$key] = $val;
      }
    }
    return $res;
  };

  $oldValuesRedacted = $redact($oldValues);
  $newValuesRedacted = $redact($newValues);
  $metaRedacted = $redact($meta) ?: [];

  $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
  $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $st = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, old_values, new_values, meta, ip_address, device_info)
                       VALUES (?,?,?,?,?,?,?,?,?)");
  $st->execute([
    $userId,
    $action,
    $entity,
    $entityId,
    $oldValuesRedacted !== null ? json_encode($oldValuesRedacted, JSON_UNESCAPED_UNICODE) : null,
    $newValuesRedacted !== null ? json_encode($newValuesRedacted, JSON_UNESCAPED_UNICODE) : null,
    empty($metaRedacted) ? null : json_encode($metaRedacted, JSON_UNESCAPED_UNICODE),
    $ipAddress,
    $deviceInfo
  ]);
}

function b64url_enc(string $s): string {
  return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_dec(string $s): string {
  $s = strtr($s, '-_', '+/');
  return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

function jwt_sign(array $payload, string $secret): string {
  $header = ["alg"=>"HS256","typ"=>"JWT"];
  $h = b64url_enc(json_encode($header));
  $p = b64url_enc(json_encode($payload));
  $sig = b64url_enc(hash_hmac('sha256', "$h.$p", $secret, true));
  return "$h.$p.$sig";
}

function jwt_verify(string $token, string $secret): ?array {
  $parts = explode('.', $token);
  if (count($parts) !== 3) return null;
  [$h,$p,$s] = $parts;
  $sig = b64url_enc(hash_hmac('sha256', "$h.$p", $secret, true));
  if (!hash_equals($sig, $s)) return null;
  $payload = json_decode(b64url_dec($p), true);
  return is_array($payload) ? $payload : null;
}
function auth_user(): ?array {
  $payload = $GLOBALS['auth_payload'] ?? null;
  if (!$payload) return null;
  return [
    'id' => (int)($payload['sub'] ?? 0),
    'username' => $payload['username'] ?? '',
    'role' => $payload['role'] ?? '',
  ];
}

function require_auth(): array {
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
    if (!empty($headers['Authorization'])) $hdr = $headers['Authorization'];
    if ($hdr === "" && !empty($headers['authorization'])) $hdr = $headers['authorization'];
  }

  if (!preg_match('/Bearer\s+(.*)$/i', $hdr, $m)) respond(["error"=>"جلسة الدخول غير صالحة، يرجى تسجيل الدخول مرة أخرى."], 401);

  $token = trim($m[1]);
  $payload = jwt_verify($token, $JWT_SECRET);
  if (!$payload) respond(["error"=>"رمز الجلسة غير صالح، يرجى تسجيل الدخول مجدداً."], 401);
  if (isset($payload["exp"]) && time() > (int)$payload["exp"]) respond(["error"=>"انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى."], 401);

  $tokenHash = hash('sha256', $token);
  $pdo = db();

  // Validate session in database
  $stSession = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE token_hash = ? AND expires_at > NOW()");
  $stSession->execute([$tokenHash]);
  $sessionExists = (int)$stSession->fetchColumn() > 0;

  $migrationComplete = (setting_get($pdo, 'session_migration_complete', '0') === '1');
  if ($migrationComplete && !$sessionExists) {
    respond(["error" => "انتهت صلاحية الجلسة أو تم تسجيل الخروج"], 401);
  }

  if ($sessionExists) {
    $upd = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE token_hash = ?");
    $upd->execute([$tokenHash]);
  }

  $GLOBALS['auth_payload'] = $payload;
  $GLOBALS['auth_token_hash'] = $tokenHash;

  return $payload;
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

function array_merge_recursive_distinct(array $array1, array $array2): array
{
  $merged = $array1;
  foreach ($array2 as $key => $value) {
    if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
      $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
    } else {
      $merged[$key] = $value;
    }
  }
  return $merged;
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

  // Default values for screen permissions based on role
  $screenDefaults = [];
  if (strtolower((string)$role) === 'admin') {
    $screenDefaults = [
      'dashboard' => true, 'rents' => true, 'clients' => true, 'equipment' => true,
      'payments' => true, 'receipts' => true, 'reports' => true, 'hr' => true,
      'attendance' => true, 'shifts' => true, 'backup' => true, 'settings' => true,
      'user_management' => true, 'print' => true, 'export' => true, 'audit_logs' => true,
      'financial_reports' => true,
    ];
  } elseif (strtolower((string)$role) === 'manager') {
    $screenDefaults = [
      'dashboard' => true, 'rents' => true, 'clients' => true, 'equipment' => true,
      'payments' => true, 'receipts' => true, 'reports' => true, 'hr' => true,
      'attendance' => true, 'shifts' => true, 'backup' => true, 'settings' => true,
      'user_management' => false, 'print' => true, 'export' => true, 'audit_logs' => false,
      'financial_reports' => true,
    ];
  } else { // employee
    $screenDefaults = [
      'dashboard' => true, 'rents' => true, 'clients' => true, 'equipment' => true,
      'payments' => true, 'receipts' => true, 'reports' => false, 'hr' => false,
      'attendance' => true, 'shifts' => true, 'backup' => false, 'settings' => false,
      'user_management' => false, 'print' => true, 'export' => true, 'audit_logs' => false,
      'financial_reports' => false,
    ];
  }

  $screenPermissions = [];
  $rawScreen = $raw['screen_permissions'] ?? null;
  if (is_array($rawScreen)) {
    foreach ($screenDefaults as $k => $defVal) {
      if (array_key_exists($k, $rawScreen)) {
        $screenPermissions[$k] = filter_var($rawScreen[$k], FILTER_VALIDATE_BOOLEAN);
      } else {
        $screenPermissions[$k] = $defVal;
      }
    }
  } else {
    // Fallback to legacy/root keys first, or default if missing
    foreach ($screenDefaults as $k => $defVal) {
      if (array_key_exists($k, $raw)) {
        $screenPermissions[$k] = filter_var($raw[$k], FILTER_VALIDATE_BOOLEAN);
      } else {
        $screenPermissions[$k] = $defVal;
      }
    }
  }

  return [
    'contract_hour_pricing_mode' => $hourMode,
    'contract_payment_receipt_mode' => $receiptMode,
    'screen_permissions' => $screenPermissions,
  ];
}

function has_permission(PDO $pdo, array $auth, string $permissionKey): bool
{
  if (strtolower(trim((string)($auth['role'] ?? ''))) === 'admin') {
    return true;
  }

  $userId = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($userId <= 0) return false;

  $st = $pdo->prepare("SELECT permissions_json FROM users WHERE id = ? LIMIT 1");
  $st->execute([$userId]);
  $permissionsJson = $st->fetchColumn();
  if (!$permissionsJson) return false;

  $perms = json_decode($permissionsJson, true);
  if (!is_array($perms)) return false;

  if (isset($perms['screen_permissions']) && is_array($perms['screen_permissions'])) {
    return !empty($perms['screen_permissions'][$permissionKey]);
  }

  // Legacy fallback: check root keys
  return !empty($perms[$permissionKey]);
}

function require_permission(PDO $pdo, array $auth, string $permissionKey): void
{
  if (!has_permission($pdo, $auth, $permissionKey)) {
    respond(["error" => "ممنوع: ليس لديك صلاحية الوصول إلى هذه العملية ($permissionKey)"], 403);
  }
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  ensure_column($pdo, 'equipment_depreciation_entries', 'depreciation_type', "ALTER TABLE equipment_depreciation_entries ADD COLUMN depreciation_type VARCHAR(20) NOT NULL DEFAULT 'accounting' AFTER depreciation_month");
  ensure_column($pdo, 'equipment_depreciation_entries', 'amount', "ALTER TABLE equipment_depreciation_entries ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER depreciation_type");
  ensure_column($pdo, 'equipment_depreciation_entries', 'accum_before', "ALTER TABLE equipment_depreciation_entries ADD COLUMN accum_before DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount");
  ensure_column($pdo, 'equipment_depreciation_entries', 'accum_after', "ALTER TABLE equipment_depreciation_entries ADD COLUMN accum_after DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER accum_before");
  ensure_column($pdo, 'equipment_depreciation_entries', 'book_before', "ALTER TABLE equipment_depreciation_entries ADD COLUMN book_before DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER accum_after");
  ensure_column($pdo, 'equipment_depreciation_entries', 'book_after', "ALTER TABLE equipment_depreciation_entries ADD COLUMN book_after DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER book_before");

  // Modify indexes dynamically
  try {
    $pdo->exec("ALTER TABLE equipment_depreciation_entries DROP INDEX uniq_equipment_dep_month");
  } catch (Throwable $e) {}

  ensure_index($pdo, 'equipment_depreciation_entries', 'uniq_equipment_dep_month_type', "CREATE UNIQUE INDEX uniq_equipment_dep_month_type ON equipment_depreciation_entries (equipment_id, depreciation_month, depreciation_type)");
  ensure_index($pdo, 'equipment_depreciation_entries', 'idx_equipment_dep_entries_equipment_id', "CREATE INDEX idx_equipment_dep_entries_equipment_id ON equipment_depreciation_entries (equipment_id)");
  ensure_index($pdo, 'equipment_depreciation_entries', 'idx_equipment_dep_entries_depreciation_month', "CREATE INDEX idx_equipment_dep_entries_depreciation_month ON equipment_depreciation_entries (depreciation_month)");
  ensure_index($pdo, 'equipment_depreciation_entries', 'idx_equipment_dep_entries_depreciation_type', "CREATE INDEX idx_equipment_dep_entries_depreciation_type ON equipment_depreciation_entries (depreciation_type)");
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

    // 1. Process Accounting Depreciation
    $chkAcc = $pdo->prepare("SELECT id FROM equipment_depreciation_entries WHERE equipment_id=? AND depreciation_month=? AND depreciation_type='accounting' LIMIT 1");
    $chkAcc->execute([$equipmentId, $month]);
    if (!$chkAcc->fetchColumn() && $accountingAmount > 0.0001) {
      $pdo->beginTransaction();
      try {
        $paymentId = null;
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

        $accumBefore = (float)($row['depreciation_accumulated'] ?? 0);
        $accumAfter = min($accumBefore + $accountingAmount, max((float)$vals['purchase_price'] - (float)$vals['salvage_value'], 0.0));
        $bookBefore = (float)($row['book_value'] ?? $vals['purchase_price']);
        $bookAfter = max((float)$vals['purchase_price'] - $accumAfter, (float)$vals['salvage_value'], 0.0);

        $ins = $pdo->prepare("INSERT INTO equipment_depreciation_entries 
          (equipment_id, depreciation_month, depreciation_type, amount, accum_before, accum_after, book_before, book_after, accounting_amount, operational_amount, voucher_payment_id)
          VALUES (?, ?, 'accounting', ?, ?, ?, ?, ?, ?, 0, ?)");
        $ins->execute([
          $equipmentId, $month, $accountingAmount, 
          $accumBefore, $accumAfter, $bookBefore, $bookAfter,
          $accountingAmount, $paymentId ?: null
        ]);

        $upd = $pdo->prepare("UPDATE equipment SET depreciation_accumulated=?, book_value=?, last_depreciation_month=? WHERE id=?");
        $upd->execute([round($accumAfter, 2), round($bookAfter, 2), $month, $equipmentId]);

        $pdo->commit();

        audit_log($pdo, 'depreciation_generated', 'equipment', $equipmentId, null, null, [
          'depreciation_type' => 'accounting',
          'amount' => $accountingAmount,
          'period' => $month
        ]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        audit_log($pdo, 'depreciation_error', 'equipment', $equipmentId, null, null, [
          'depreciation_type' => 'accounting',
          'error' => $e->getMessage(),
          'period' => $month
        ]);
      }
    }

    // Reload row to get updated book values before running operational
    $stRow = $pdo->prepare("SELECT * FROM equipment WHERE id=? LIMIT 1");
    $stRow->execute([$equipmentId]);
    $row = $stRow->fetch(PDO::FETCH_ASSOC);

    // 2. Process Operational Depreciation
    $chkOp = $pdo->prepare("SELECT id FROM equipment_depreciation_entries WHERE equipment_id=? AND depreciation_month=? AND depreciation_type='operational' LIMIT 1");
    $chkOp->execute([$equipmentId, $month]);
    if (!$chkOp->fetchColumn() && $operationalAmount > 0.0001) {
      $pdo->beginTransaction();
      try {
        $accumBefore = (float)($row['operational_depreciation_accumulated'] ?? 0);
        $accumAfter = $accumBefore + $operationalAmount;
        $bookBefore = (float)($row['book_value'] ?? $vals['purchase_price']);
        $bookAfter = $bookBefore; 

        $ins = $pdo->prepare("INSERT INTO equipment_depreciation_entries 
          (equipment_id, depreciation_month, depreciation_type, amount, accum_before, accum_after, book_before, book_after, accounting_amount, operational_amount, voucher_payment_id)
          VALUES (?, ?, 'operational', ?, ?, ?, ?, ?, 0, ?, NULL)");
        $ins->execute([
          $equipmentId, $month, $operationalAmount,
          $accumBefore, $accumAfter, $bookBefore, $bookAfter,
          $operationalAmount
        ]);

        $upd = $pdo->prepare("UPDATE equipment SET operational_depreciation_accumulated=?, last_depreciation_month=? WHERE id=?");
        $upd->execute([round($accumAfter, 2), $month, $equipmentId]);

        $pdo->commit();

        audit_log($pdo, 'depreciation_generated', 'equipment', $equipmentId, null, null, [
          'depreciation_type' => 'operational',
          'amount' => $operationalAmount,
          'period' => $month
        ]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        audit_log($pdo, 'depreciation_error', 'equipment', $equipmentId, null, null, [
          'depreciation_type' => 'operational',
          'error' => $e->getMessage(),
          'period' => $month
        ]);
      }
    }

    // Sync latest calculated book_value and per-day values to snapshot columns
    update_equipment_depreciation_snapshot($pdo, $equipmentId);
  }
}

function update_rent_closing_payment_status(PDO $pdo, int $rentId, int $paymentId, string $method): void {
  $st = $pdo->prepare("SELECT status FROM rents WHERE id=? LIMIT 1");
  $st->execute([$rentId]);
  $status = strtolower((string)$st->fetchColumn());
  if ($status === 'closed') {
    $upd = $pdo->prepare("UPDATE rents SET closing_payment_status='created', closing_payment_id=?, closing_payment_method=? WHERE id=?");
    $upd->execute([$paymentId, $method, $rentId]);
  }
}

function auto_close_rent_if_fully_paid(PDO $pdo, int $rentId): bool {
  // 1. Fetch rent details
  $st = $pdo->prepare("SELECT id, status, start_datetime, rate, equipment_id, discount_amount FROM rents WHERE id=?");
  $st->execute([$rentId]);
  $rent = $st->fetch();
  if (!$rent) return false;

  $status = strtolower((string)($rent['status'] ?? ''));
  if ($status !== 'open') return false;

  // 2. Calculate dynamic total at current time
  $now = date('Y-m-d H:i:s');
  $now_ts = strtotime($now);

  $stItems = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id=?");
  $stItems->execute([$rentId]);
  $items = $stItems->fetchAll(PDO::FETCH_ASSOC);

  $total = 0.0;
  if (empty($items)) {
    $start_ts = strtotime((string)$rent["start_datetime"]);
    if ($start_ts) {
      $hrs = ($now_ts - $start_ts) / 3600;
      $days = ceil($hrs / 24);
      if ($days < 1) $days = 1;
      $total = round($days * (float)($rent["rate"] ?? 0), 2);
    }
  } else {
    foreach ($items as $it) {
      $itemEnd = $it['status'] === 'open' ? $now : ($it['end_datetime'] ?? $now);
      $start_ts = strtotime((string)$it['start_datetime']);
      $iEnd_ts = strtotime($itemEnd);
      if ($start_ts && $iEnd_ts) {
        $hrs = ($iEnd_ts - $start_ts) / 3600;
        $days = ceil($hrs / 24);
        if ($days < 1) $days = 1;
        $total += round($days * (float)$it['rate'], 2);
      }
    }
  }

  // 3. Fetch paid amount for this rent
  $st2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE rent_id=? AND (is_void=0 OR is_void IS NULL) AND type='in'");
  $st2->execute([$rentId]);
  $paid = (float)$st2->fetchColumn();

  $discount = (float)($rent['discount_amount'] ?? 0);
  $remaining = max($total - $discount - $paid, 0.0);

  // 4. If remaining is 0 (or close to 0), close the rent!
  if ($remaining <= 0.0001) {
    // A. Update rent items status to closed
    $updItem = $pdo->prepare("UPDATE rent_items SET end_datetime=?, status='closed' WHERE rent_id=? AND status='open'");
    $updItem->execute([$now, $rentId]);

    // B. Update equipment status to available
    $updEq = $pdo->prepare("UPDATE equipment SET status='available' WHERE id IN (SELECT equipment_id FROM rent_items WHERE rent_id=?)");
    $updEq->execute([$rentId]);
    
    // Also backward compatibility equipment update
    $eqid = (int)$rent["equipment_id"];
    if ($eqid > 0) {
      $updEqSingle = $pdo->prepare("UPDATE equipment SET status='available' WHERE id=?");
      $updEqSingle->execute([$eqid]);
    }

    $closed_by_user_id = null;
    $auth = auth_user();
    if ($auth && !empty($auth['id'])) {
      $closed_by_user_id = (int)$auth['id'];
    }

    // C. Update rent status to closed
    $upd = $pdo->prepare("UPDATE rents SET end_datetime=?, total_amount=?, status='closed', closed_at=NOW(), closed_by_user_id=? WHERE id=?");
    $upd->execute([$now, $total, $closed_by_user_id, $rentId]);

    audit_log($pdo, 'rent_closed_auto', 'rent', $rentId, ['total_amount' => $total, 'trigger' => 'payment_paid_in_full']);
    return true;
  }

  return false;
}

function dump_database(PDO $pdo, string $mode = 'full'): string {
  $pdo->exec("SET NAMES utf8mb4");
  $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
  $sql = "";
  $sql .= "-- Rental System Backup\n";
  $sql .= "-- Type: {$mode}\n";
  $sql .= "-- Generated at: " . date("Y-m-d H:i:s") . "\n\n";
  $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
  $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

  foreach ($tables as $t) {
    $table = $t[0];

    // إنشاء الجدول (full/def)
    if ($mode !== 'log') {
      $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
      $create = $row["Create Table"] ?? "";
      $sql .= "\n-- ----------------------------\n";
      $sql .= "-- Table: `$table`\n";
      $sql .= "-- ----------------------------\n";
      $sql .= "DROP TABLE IF EXISTS `$table`;\n";
      $sql .= $create . ";\n\n";
    }

    // بيانات الجدول (full/log)
    if ($mode === 'def') {
      continue;
    }

    $stmt = $pdo->query("SELECT * FROM `$table`");
    $colsCount = $stmt->columnCount();

    $batch = [];
    $batchSize = 200;
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
      $vals = [];
      for ($i=0; $i<$colsCount; $i++) {
        $v = $r[$i];
        if ($v === null) {
          $vals[] = "NULL";
        } else {
          $vals[] = $pdo->quote((string)$v);
        }
      }
      $batch[] = "(" . implode(",", $vals) . ")";
      if (count($batch) >= $batchSize) {
        $sql .= "INSERT INTO `$table` VALUES \n" . implode(",\n", $batch) . ";\n";
        $batch = [];
      }
    }
    if (!empty($batch)) {
      $sql .= "INSERT INTO `$table` VALUES \n" . implode(",\n", $batch) . ";\n";
    }
  }

  $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
  return $sql;
}

function is_safe_backup_path(string $path): bool {
  $real = realpath($path);
  if ($real === false) {
    $parent = dirname($path);
    $real = realpath($parent);
    if ($real === false) return false;
  }
  
  $path = str_replace('\\', '/', strtolower($real));
  $path = rtrim($path, '/');
  
  $unsafePrefixes = [
    'c:/windows', 'c:/program files', 'c:/program files (x86)', 'c:/users/administrator',
    '/etc', '/var', '/bin', '/usr', '/sys', '/boot', '/proc', '/dev', '/lib', '/lib64'
  ];
  
  foreach ($unsafePrefixes as $prefix) {
    if (str_starts_with($path, $prefix)) return false;
  }
  
  // Prevent writing inside code folders except backups
  $apiDir = str_replace('\\', '/', strtolower(realpath(__DIR__)));
  if (str_starts_with($path, $apiDir) && !str_starts_with($path, $apiDir . '/backups')) {
    return false;
  }
  
  return true;
}

function copy_backup_to_custom_paths(PDO $pdo, string $sourceFullpath, string $filename): void {
  $path1 = setting_get($pdo, "backup_custom_path_1", "");
  $path2 = setting_get($pdo, "backup_custom_path_2", "");

  if ($path1 !== "") {
    $path1 = rtrim(str_replace('\\', '/', $path1), '/');
    if (is_dir($path1) && is_safe_backup_path($path1)) {
      $dest = $path1 . '/' . $filename;
      if (!file_exists($dest)) {
        @copy($sourceFullpath, $dest);
      }
    }
  }

  if ($path2 !== "") {
    $path2 = rtrim(str_replace('\\', '/', $path2), '/');
    if (is_dir($path2) && is_safe_backup_path($path2)) {
      $dest = $path2 . '/' . $filename;
      if (!file_exists($dest)) {
        @copy($sourceFullpath, $dest);
      }
    }
  }
}

function ensure_enterprise_schema(PDO $pdo): void {
  // Create tables if not exists
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token_hash VARCHAR(64) NOT NULL,
      device_name VARCHAR(255) NULL,
      device_platform VARCHAR(100) NULL,
      last_activity DATETIME NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE INDEX idx_token_hash (token_hash),
      INDEX idx_user_id (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS backup_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      file_name VARCHAR(255) NOT NULL,
      file_size INT NOT NULL,
      status VARCHAR(20) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS system_errors (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      api VARCHAR(255) NOT NULL,
      error_message TEXT NOT NULL,
      stack_trace TEXT NULL,
      request_data JSON NULL,
      status ENUM('open','resolved') NOT NULL DEFAULT 'open',
      resolved_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ensure columns on system_errors (for existing installations)
  ensure_column($pdo, 'system_errors', 'status', "ALTER TABLE system_errors ADD COLUMN status ENUM('open','resolved') NOT NULL DEFAULT 'open' AFTER request_data");
  ensure_column($pdo, 'system_errors', 'resolved_at', "ALTER TABLE system_errors ADD COLUMN resolved_at DATETIME NULL AFTER status");

  // ensure columns on audit_logs
  ensure_column($pdo, 'audit_logs', 'ip_address', "ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER created_at");
  ensure_column($pdo, 'audit_logs', 'device_info', "ALTER TABLE audit_logs ADD COLUMN device_info VARCHAR(255) NULL AFTER ip_address");
}

function respond_success($data = null, string $message = "", int $code = 200): void {
  respond([
    "success" => true,
    "data" => $data,
    "message" => $message
  ], $code);
}

function respond_error(string $message, string $errorCode = "SERVER_ERROR", int $code = 500): void {
  respond([
    "success" => false,
    "error" => $message, // legacy support
    "error_code" => $errorCode,
    "message" => $message
  ], $code);
}

function categorize_error(string $msg): array {
  if (str_contains($msg, 'Constant') && str_contains($msg, 'already defined')) {
    return [
      'title_ar' => 'خطأ في تعريف الثوابت (Constants)',
      'cause_ar' => 'تمت محاولة تعريف ثابت (Constant) سبق تعريفه في ملف آخر',
      'severity' => 'منخفض',
      'suggested_action_ar' => 'نقل جميع تعريفات الثوابت إلى ملف واحد (attendance_calculator.php) واستخدام require_once فقط'
    ];
  }
  if (str_contains($msg, 'Column not found') || str_contains($msg, 'Unknown column')) {
    return [
      'title_ar' => 'خطأ في استعلام قاعدة البيانات',
      'cause_ar' => 'استعلام SQL يشير إلى عامود (Column) غير موجود في جدول قاعدة البيانات',
      'severity' => 'عالي',
      'suggested_action_ar' => 'التحقق من أسماء الأعمدة في استعلام SQL ومطابقتها لهيكل الجدول الفعلي في قاعدة البيانات'
    ];
  }
  if (str_contains($msg, 'Table') && (str_contains($msg, 'not found') || str_contains($msg, 'doesn\'t exist'))) {
    return [
      'title_ar' => 'خطأ في قاعدة البيانات - جدول غير موجود',
      'cause_ar' => 'استعلام SQL يشير إلى جدول غير موجود في قاعدة البيانات',
      'severity' => 'عالي',
      'suggested_action_ar' => 'التأكد من وجود الجدول في قاعدة البيانات أو تفعيل الترحيل التلقائي (auto-migration)'
    ];
  }
  if (str_contains($msg, 'SQLSTATE') || str_contains($msg, 'PDO') || str_contains($msg, 'SQL')) {
    return [
      'title_ar' => 'خطأ في قاعدة البيانات',
      'cause_ar' => 'حدث خطأ أثناء تنفيذ استعلام على قاعدة البيانات',
      'severity' => 'عالي',
      'suggested_action_ar' => 'مراجعة سجل الأخطاء التقني واستعلام SQL المسبب للخطأ'
    ];
  }
  if (str_contains($msg, 'Division by zero')) {
    return [
      'title_ar' => 'خطأ رياضي - قسمة على صفر',
      'cause_ar' => 'تمت محاولة قسمة رقم على صفر في إحدى العمليات الحسابية',
      'severity' => 'متوسط',
      'suggested_action_ar' => 'إضافة شرط للتحقق من القيمة قبل إجراء عملية القسمة'
    ];
  }
  if (str_contains($msg, 'Undefined array key') || str_contains($msg, 'Undefined index') || str_contains($msg, 'Undefined offset')) {
    return [
      'title_ar' => 'خطأ في الوصول إلى مصفوفة',
      'cause_ar' => 'محاولة الوصول إلى مفتاح غير موجود في مصفوفة (Array)',
      'severity' => 'متوسط',
      'suggested_action_ar' => 'التأكد من وجود المفتاح باستخدام isset() أو array_key_exists() قبل الوصول إليه'
    ];
  }
  if (str_contains($msg, 'Call to undefined function')) {
    return [
      'title_ar' => 'خطأ في استدعاء دالة غير معرفة',
      'cause_ar' => 'محاولة استدعاء دالة (Function) غير موجودة في ملف PHP',
      'severity' => 'عالي',
      'suggested_action_ar' => 'التأكد من تضمين الملف الذي يحتوي على تعريف الدالة باستخدام require_once'
    ];
  }
  if (str_contains($msg, 'Trying to access array offset on false') || str_contains($msg, 'Trying to get property')) {
    return [
      'title_ar' => 'خطأ في التعامل مع القيم الفارغة',
      'cause_ar' => 'محاولة التعامل مع قيمة فارغة (null/false) كأنها مصفوفة أو كائن',
      'severity' => 'متوسط',
      'suggested_action_ar' => 'التحقق من صحة القيمة قبل الوصول إلى خصائصها باستخدام isset() أو empty()'
    ];
  }
  return [
    'title_ar' => 'خطأ في النظام',
    'cause_ar' => $msg,
    'severity' => 'متوسط',
    'suggested_action_ar' => 'مراجعة سجل الأخطاء التقني (Stack Trace) لمزيد من التفاصيل'
  ];
}

function log_system_error(string $errorMessage, ?string $stackTrace = null): void {
  try {
    $pdo = db();
    $uid = null;
    try {
      if (function_exists('auth_user')) {
        $u = auth_user();
        if (is_array($u) && isset($u['id'])) $uid = (int)$u['id'];
      }
    } catch (Throwable $ignore) {}
    
    $api = $_SERVER['REQUEST_URI'] ?? 'CLI';
    
    $requestData = [
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
      'get' => $_GET,
      'post' => json_in() ?: $_POST
    ];
    // redact passwords
    $sensitiveKeys = ['password', 'password_hash', 'token', 'auth_secret', 'secret'];
    if (isset($requestData['post']) && is_array($requestData['post'])) {
      foreach ($requestData['post'] as $k => $v) {
        if (in_array(strtolower((string)$k), $sensitiveKeys, true)) {
          $requestData['post'][$k] = '[REDACTED]';
        }
      }
    }
    
    $st = $pdo->prepare("INSERT INTO system_errors (user_id, api, error_message, stack_trace, request_data) VALUES (?, ?, ?, ?, ?)");
    $st->execute([
      $uid,
      $api,
      $errorMessage,
      $stackTrace,
      json_encode($requestData, JSON_UNESCAPED_UNICODE)
    ]);
  } catch (Throwable $e) {
    // Fail silently to prevent infinite loops
  }
}

// Global enterprise error handlers
if (php_sapi_name() !== 'cli') {
  set_exception_handler(function(Throwable $e) {
    log_system_error($e->getMessage(), $e->getTraceAsString());
    respond_error("حدث خطأ غير متوقع في الخادم، يرجى المحاولة لاحقاً", "SERVER_ERROR", 500);
  });

  set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
      return false;
    }
    $msg = "PHP Error: $errstr in $errfile on line $errline";
    log_system_error($msg);
    if ($errno === E_ERROR || $errno === E_USER_ERROR || $errno === E_RECOVERABLE_ERROR) {
      respond_error("حدث خطأ في النظام، يرجى المحاولة لاحقاً", "SYSTEM_ERROR", 500);
    }
    return false;
  });
}

