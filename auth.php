<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];

date_default_timezone_set('Asia/Riyadh');

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/
if ($path === "auth/login" && $method === "POST") {

  $in = json_in();
  $username = trim((string)($in["username"] ?? ""));
  $password = trim((string)($in["password"] ?? ""));

  if ($username === "" || $password === "") {
    respond(["error" => "حقول مفقودة"], 400);
  }

  $pdo = db();
  ensure_financials_schema($pdo);
  $st = $pdo->prepare(
    "SELECT id, username, password, role, is_active, permissions_json FROM users WHERE username=?"
  );
  $st->execute([$username]);
  $user = $st->fetch();

  if (!$user) {
    audit_log($pdo, 'login_failed', 'user', null, null, null, ['username' => $username, 'reason' => 'user_not_found']);
    respond(["error" => "بيانات الدخول غير صحيحة"], 401);
  }
  if ((int)($user['is_active'] ?? 1) !== 1) {
    audit_log($pdo, 'login_failed', 'user', (int)$user['id'], null, null, ['username' => $username, 'reason' => 'account_inactive']);
    respond(["error" => "الحساب غير مفعل"], 403);
  }

  $isCorrect = false;
  $storedPassword = (string)$user['password'];
  if (password_verify($password, $storedPassword)) {
    $isCorrect = true;
  } elseif ($password === $storedPassword) {
    $isCorrect = true;
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->execute([$hashed, $user['id']]);
    audit_log($pdo, 'password_changed', 'user', (int)$user['id'], null, null, ['reason' => 'auto_upgrade_to_hash']);
  }

  if (!$isCorrect) {
    audit_log($pdo, 'login_failed', 'user', (int)$user['id'], null, null, ['username' => $username, 'reason' => 'wrong_password']);
    respond(["error" => "بيانات الدخول غير صحيحة"], 401);
  }

  global $JWT_SECRET;
  $expiresAt = time() + 86400; // 24 hours
  $token = jwt_sign([
    "sub"      => (int)$user["id"],
    "username" => $user["username"],
    "role"     => $user["role"],
    "exp"      => $expiresAt
  ], $JWT_SECRET);

  // Register session
  $tokenHash = hash('sha256', $token);
  $deviceName = trim((string)($in["device_name"] ?? ""));
  $devicePlatform = trim((string)($in["device_platform"] ?? ""));

  $stSession = $pdo->prepare(
    "INSERT INTO user_sessions (user_id, token_hash, device_name, device_platform, last_activity, expires_at)
     VALUES (?, ?, ?, ?, NOW(), FROM_UNIXTIME(?))"
  );
  $stSession->execute([$user['id'], $tokenHash, $deviceName, $devicePlatform, $expiresAt]);
  $sessionId = (int)$pdo->lastInsertId();

  audit_log($pdo, 'login_success', 'user', (int)$user['id'], null, null, [
    'session_id' => $sessionId,
    'device_name' => $deviceName,
    'device_platform' => $devicePlatform
  ]);
  audit_log($pdo, 'session_created', 'session', $sessionId, null, null, [
    'user_id' => (int)$user['id'],
    'expires_at' => date("Y-m-d H:i:s", $expiresAt)
  ]);

  respond([
    "token" => $token,
    "user"  => ["id" => (int)$user["id"], "username" => $user["username"], "role" => $user["role"]]
  ], 200);
}

/*
|--------------------------------------------------------------------------
| PROFILE (AUTH USER)
| GET auth/profile
|--------------------------------------------------------------------------
*/
if ($path === "auth/profile" && $method === "GET") {

  $auth = require_auth();
  $userId = (int)($auth["sub"] ?? 0);
  if ($userId <= 0) respond(["error" => "غير مصرح"], 401);

  $pdo = db();
  ensure_financials_schema($pdo);
  $st = $pdo->prepare(
    "SELECT id, username, role, is_active, created_at, permissions_json FROM users WHERE id=?"
  );
  $st->execute([$userId]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u) respond(["error" => "المستخدم غير موجود"], 404);

  $u['id'] = (int)$u['id'];
  $u['is_active'] = (int)$u['is_active'];
  $u['permissions'] = normalize_user_permissions($u['permissions_json'] ?? null, (string)($u['role'] ?? 'employee'));
  $u = array_merge($u, effective_contract_closing_modes($pdo, $u));
  $u['screen_permissions'] = (object)($u['permissions']['screen_permissions'] ?? []);

  respond($u, 200);
}

/*
|--------------------------------------------------------------------------
| USERS - GET ALL (ADMIN)
|--------------------------------------------------------------------------
*/
if ($path === "users" && $method === "GET") {

  $auth = require_auth();
  if ($auth["role"] !== "admin") {
    respond(["error" => "ممنوع"], 403);
  }

  $pdo = db();
  $st = $pdo->query(
    "SELECT id, username, role, is_active, created_at FROM users ORDER BY id DESC"
  );

  respond($st->fetchAll(), 200);
}

/*
|--------------------------------------------------------------------------
| USERS - CREATE (ADMIN)
|--------------------------------------------------------------------------
*/
if ($path === "users" && $method === "POST") {

  $auth = require_auth();
  if ($auth["role"] !== "admin") {
    respond(["error" => "ممنوع"], 403);
  }

  $in = json_in();
  $username = trim((string)($in["username"] ?? ""));
  $password = trim((string)($in["password"] ?? ""));
  $role     = trim((string)($in["role"] ?? "employee"));

  if ($username === "" || $password === "") {
    respond(["error" => "حقول مفقودة"], 400);
  }

  $pdo = db();

  $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
  $check->execute([$username]);
  if ($check->fetch()) {
    respond(["error" => "اسم المستخدم موجود بالفعل"], 409);
  }

  $st = $pdo->prepare(
    "INSERT INTO users (username, password, role, is_active, created_at)
     VALUES (?, ?, ?, 1, NOW())"
  );
  $st->execute([$username, $password, $role]);

  respond([
    "id" => $pdo->lastInsertId(),
    "username" => $username,
    "role" => $role,
    "is_active" => 1,
    "created_at" => date("Y-m-d H:i:s")
  ], 201);
}

/*
|--------------------------------------------------------------------------
| USERS - UPDATE (ADMIN)
|--------------------------------------------------------------------------
*/
if (preg_match("#^users/(\d+)$#", $path, $m) && $method === "PUT") {

  $auth = require_auth();
  if ($auth["role"] !== "admin") {
    respond(["error" => "ممنوع"], 403);
  }

  $id = (int)$m[1];
  $in = json_in();

  $fields = [];
  $values = [];

  foreach (["username", "password", "role"] as $f) {
    if (isset($in[$f])) {
      $fields[] = "$f=?";
      $values[] = $in[$f];
    }
  }

  if (isset($in["is_active"])) {
    $fields[] = "is_active=?";
    $values[] = (int)$in["is_active"];
  }

  if (!$fields) {
    respond(["error" => "لا شيء للتحديث"], 400);
  }

  $values[] = $id;

  $pdo = db();
  $st = $pdo->prepare(
    "UPDATE users SET " . implode(",", $fields) . " WHERE id=?"
  );
  $st->execute($values);

  $user = $pdo->prepare(
    "SELECT id, username, role, is_active, created_at, permissions_json FROM users WHERE id=?"
  );
  $user->execute([$id]);

  respond($user->fetch(), 200);
}

/*
|--------------------------------------------------------------------------
| USERS - DELETE (ADMIN)
|--------------------------------------------------------------------------
*/
if (preg_match("#^users/(\d+)$#", $path, $m) && $method === "DELETE") {

  $auth = require_auth();
  if ($auth["role"] !== "admin") {
    respond(["error" => "ممنوع"], 403);
  }

  $pdo = db();
  $st = $pdo->prepare("DELETE FROM users WHERE id=?");
  $st->execute([(int)$m[1]]);

  respond(["message" => "تم حذف المستخدم"], 200);
}


/*
|--------------------------------------------------------------------------
| REGISTER / CREATE USER (ADMIN)
|--------------------------------------------------------------------------
*/
if ($path === "auth/register" && $method === "POST") {
  $auth = require_auth();
  $pdo = db();
  require_permission($pdo, $auth, 'user_management');

  $in = json_in();
  $username = trim((string)($in["username"] ?? ""));
  $password = trim((string)($in["password"] ?? ""));
  $role = trim((string)($in["role"] ?? "employee"));
  $permissions = normalize_user_permissions($in['permissions'] ?? null, $role);

  if ($username === "" || $password === "") {
    respond(["error" => "حقول مفقودة"], 400);
  }

  ensure_financials_schema($pdo);
  $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
  $check->execute([$username]);
  if ($check->fetch()) {
    respond(["error" => "اسم المستخدم موجود بالفعل"], 409);
  }

  $st = $pdo->prepare(
    "INSERT INTO users (username, password, role, is_active, created_at, permissions_json)
     VALUES (?, ?, ?, 1, NOW(), ?)"
  );
  $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, json_encode($permissions, JSON_UNESCAPED_UNICODE)]);

  $newId = (int)$pdo->lastInsertId();
  audit_log($pdo, 'user_created', 'user', $newId, null, [
      'username' => $username,
      'role' => $role,
      'is_active' => 1,
      'permissions' => $permissions
  ]);

  respond([
    "id" => $newId,
    "username" => $username,
    "role" => $role,
    "is_active" => 1,
    "permissions" => $permissions,
    "screen_permissions" => (object)($permissions['screen_permissions'] ?? []),
    "created_at" => date("Y-m-d H:i:s")
  ], 201);
}

/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD (AUTH USER)
|--------------------------------------------------------------------------
*/
if ($path === "auth/change-password" && $method === "POST") {

  $auth = require_auth();
  $userId = (int)$auth["sub"];

  $in = json_in();
  $old = trim((string)($in["old_password"] ?? ""));
  $new = trim((string)($in["new_password"] ?? ""));

  if ($old === "" || $new === "") {
    respond(["error" => "حقول مفقودة"], 400);
  }

  $pdo = db();
  $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
  $st->execute([$userId]);
  $row = $st->fetch();

  $storedPassword = (string)($row["password"] ?? "");
  $isCorrect = false;
  if (password_verify($old, $storedPassword)) {
    $isCorrect = true;
  } elseif ($old === $storedPassword) {
    $isCorrect = true;
  }

  if (!$isCorrect) {
    respond(["error" => "كلمة المرور القديمة غير صحيحة"], 401);
  }

  $hashedNew = password_hash($new, PASSWORD_DEFAULT);
  $up = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
  $up->execute([$hashedNew, $userId]);

  audit_log($pdo, 'password_changed', 'user', $userId, null, null, ['reason' => 'user_initiated']);

  respond(["message" => "تم تغيير كلمة المرور بنجاح"], 200);
}

/*
|--------------------------------------------------------------------------
| LOGOUT (AUTH USER)
|--------------------------------------------------------------------------
*/
if ($path === "auth/logout" && $method === "POST") {
  $auth = require_auth();
  $tokenHash = $GLOBALS['auth_token_hash'] ?? null;
  
  if ($tokenHash) {
    $pdo = db();
    
    // Find session details to log it
    $stSession = $pdo->prepare("SELECT id FROM user_sessions WHERE token_hash = ?");
    $stSession->execute([$tokenHash]);
    $sessionId = $stSession->fetchColumn();
    
    $st = $pdo->prepare("DELETE FROM user_sessions WHERE token_hash = ?");
    $st->execute([$tokenHash]);
    
    if ($sessionId) {
      $sessionId = (int)$sessionId;
      audit_log($pdo, 'logout', 'user', (int)$auth['sub'], null, null, ['session_id' => $sessionId]);
      audit_log($pdo, 'session_revoked', 'session', $sessionId, null, null, ['reason' => 'logout']);
    }
  }
  
  respond(["success" => true, "message" => "تم تسجيل الخروج بنجاح"], 200);
}

/*
|--------------------------------------------------------------------------
| LIST ACTIVE SESSIONS (AUTH USER)
|--------------------------------------------------------------------------
*/
if ($path === "auth/sessions" && $method === "GET") {
  $auth = require_auth();
  $userId = (int)$auth["sub"];
  
  $pdo = db();
  $st = $pdo->prepare("SELECT id, device_name, device_platform, last_activity, expires_at, created_at FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
  $st->execute([$userId]);
  $sessions = $st->fetchAll(PDO::FETCH_ASSOC);
  
  respond(["success" => true, "data" => $sessions], 200);
}

/*
|--------------------------------------------------------------------------
| REVOKE SPECIFIC SESSION (AUTH USER)
|--------------------------------------------------------------------------
*/
if (preg_match("#^auth/sessions/(\d+)$#", $path, $m) && $method === "DELETE") {
  $auth = require_auth();
  $userId = (int)$auth["sub"];
  $sessionId = (int)$m[1];
  
  $pdo = db();
  
  // Verify session belongs to the user
  $check = $pdo->prepare("SELECT id FROM user_sessions WHERE id = ? AND user_id = ?");
  $check->execute([$sessionId, $userId]);
  if (!$check->fetch()) {
    respond(["error" => "الجلسة غير موجودة أو غير مصرح لك بإلغائها"], 404);
  }
  
  $st = $pdo->prepare("DELETE FROM user_sessions WHERE id = ?");
  $st->execute([$sessionId]);
  
  audit_log($pdo, 'session_revoked', 'session', $sessionId, null, null, ['reason' => 'user_revoked', 'user_id' => $userId]);
  
  respond(["success" => true, "message" => "تم إنهاء الجلسة بنجاح"], 200);
}

/*
|--------------------------------------------------------------------------
| ADMIN FORCE USER LOGOUT
|--------------------------------------------------------------------------
*/
if ($path === "admin/sessions/revoke-user" && $method === "POST") {
  $auth = require_auth();
  $pdo = db();
  require_permission($pdo, $auth, 'user_management');
  
  $in = json_in();
  $targetUserId = (int)($in["user_id"] ?? 0);
  
  if ($targetUserId <= 0) {
    respond(["error" => "معرف المستخدم مطلوب"], 400);
  }
  
  // Revoke all sessions for this user
  $st = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
  $st->execute([$targetUserId]);
  
  audit_log($pdo, 'admin_force_logout', 'user', $targetUserId, null, null, ['admin_user_id' => (int)$auth['sub']]);
  
  respond(["success" => true, "message" => "تم إنهاء جميع جلسات المستخدم بنجاح"], 200);
}

/*
|--------------------------------------------------------------------------
| NOT FOUND
|--------------------------------------------------------------------------
*/
respond(["error" => "غير موجود"], 404);
