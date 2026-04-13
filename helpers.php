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

  // payments: idempotency
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
