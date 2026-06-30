<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];

/*
|----------------------------------------------------------------------
| USERS - GET ALL
|----------------------------------------------------------------------
*/
if ($path === "users" && $method === "GET") {
    $auth = require_auth();
    $pdo = db();
    require_permission($pdo, $auth, 'user_management');
    ensure_financials_schema($pdo);
    $st = $pdo->query(
        "SELECT id, username, role, is_active, created_at, permissions_json FROM users ORDER BY id DESC"
    );

    $users = array_map(function($u) {
        $u['id'] = (int)$u['id'];
        $u['is_active'] = (int)$u['is_active'];
        $u['permissions'] = normalize_user_permissions($u['permissions_json'] ?? null, (string)($u['role'] ?? 'employee'));
        $u['screen_permissions'] = (object)($u['permissions']['screen_permissions'] ?? []);
        return $u;
    }, $st->fetchAll(PDO::FETCH_ASSOC));

    respond($users, 200);
}

/*
|----------------------------------------------------------------------
| USERS - CREATE
|----------------------------------------------------------------------
*/
if ($path === "users" && $method === "POST") {
    $auth = require_auth();
    $pdo = db();
    require_permission($pdo, $auth, 'user_management');

    $in = json_in();
    $username = trim((string)($in["username"] ?? ""));
    $password = trim((string)($in["password"] ?? ""));
    $role = trim((string)($in["role"] ?? "employee"));
    $rawInput = isset($in['permissions']) && is_array($in['permissions']) ? $in['permissions'] : [];
    $normalized = normalize_user_permissions($rawInput, $role);
    $permissions = array_merge_recursive_distinct($rawInput, $normalized);

    if ($username === "" || $password === "") respond(["error" => "حقول مفقودة"], 400);
    $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $check->execute([$username]);
    if ($check->fetch()) respond(["error" => "اسم المستخدم موجود بالفعل"], 409);

    $st = $pdo->prepare(
        "INSERT INTO users (username,password,role,is_active,created_at,permissions_json) VALUES (?, ?, ?, 1, NOW(), ?)"
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
|----------------------------------------------------------------------
| USERS - UPDATE
|----------------------------------------------------------------------
*/
if (preg_match("#^users/(\d+)$#", $path, $m) && $method === "PUT") {
    $auth = require_auth();
    $pdo = db();
    require_permission($pdo, $auth, 'user_management');

    $id = (int)$m[1];
    
    // Fetch old values for audit comparison
    $oldUserSt = $pdo->prepare("SELECT username, role, is_active, permissions_json FROM users WHERE id=?");
    $oldUserSt->execute([$id]);
    $oldUser = $oldUserSt->fetch(PDO::FETCH_ASSOC);
    if (!$oldUser) respond(["error" => "المستخدم غير موجود"], 404);

    $in = json_in();

    $loggedInUserId = (int)($auth["sub"] ?? $auth["uid"] ?? 0);
    if ($id === $loggedInUserId) {
        if (isset($in["is_active"]) && (int)$in["is_active"] !== 1) {
            respond(["error" => "لا يمكنك تعطيل حسابك الخاص كمدير"], 400);
        }
        if (isset($in["role"]) && $in["role"] !== "admin") {
            respond(["error" => "لا يمكنك تغيير دورك الخاص كمدير لتجنب فقدان الوصول الكامل"], 400);
        }
    }

    $fields = [];
    $values = [];
    foreach (["username", "password", "role"] as $f) {
        if (isset($in[$f])) {
            $fields[] = "$f=?";
            $values[] = ($f === 'password') ? password_hash($in[$f], PASSWORD_DEFAULT) : $in[$f];
        }
    }

    if (isset($in["is_active"])) {
        $fields[] = "is_active=?";
        $values[] = (int)$in["is_active"];
    }

    ensure_financials_schema($pdo);

    $nextRole = (string)($in['role'] ?? '');
    if ($nextRole === '') {
        $currentRoleSt = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $currentRoleSt->execute([$id]);
        $nextRole = (string)($currentRoleSt->fetchColumn() ?: 'employee');
    }
    if (array_key_exists('permissions', $in)) {
        $existingSt = $pdo->prepare("SELECT permissions_json FROM users WHERE id=?");
        $existingSt->execute([$id]);
        $existingJson = $existingSt->fetchColumn();
        $existingPerms = [];
        if ($existingJson) {
            $existingPerms = json_decode($existingJson, true) ?: [];
        }

        $rawInput = is_array($in['permissions']) ? $in['permissions'] : [];
        $merged = array_merge_recursive_distinct($existingPerms, $rawInput);
        $normalized = normalize_user_permissions($merged, $nextRole);
        $finalPerms = array_merge_recursive_distinct($merged, $normalized);

        $fields[] = "permissions_json=?";
        $values[] = json_encode($finalPerms, JSON_UNESCAPED_UNICODE);
    }

    if (!$fields) respond(["error" => "لا شيء للتحديث"], 400);

    $values[] = $id;
    $st = $pdo->prepare("UPDATE users SET " . implode(",", $fields) . " WHERE id=?");
    $st->execute($values);

    $user = $pdo->prepare("SELECT id, username, role, is_active, created_at, permissions_json FROM users WHERE id=?");
    $user->execute([$id]);
    $data = $user->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $data['id'] = (int)$data['id'];
        $data['is_active'] = (int)$data['is_active'];
        $data['permissions'] = normalize_user_permissions($data['permissions_json'] ?? null, (string)($data['role'] ?? 'employee'));
        $data['screen_permissions'] = (object)($data['permissions']['screen_permissions'] ?? []);
    }

    // Compare and log audit trail changes
    $newUsername = isset($in['username']) ? trim($in['username']) : $oldUser['username'];
    $newRole = isset($in['role']) ? trim($in['role']) : $oldUser['role'];
    $newIsActive = isset($in['is_active']) ? (int)$in['is_active'] : (int)$oldUser['is_active'];
    
    $oldPermsDecoded = json_decode($oldUser['permissions_json'] ?? '{}', true) ?: [];
    $oldPermissions = normalize_user_permissions($oldPermsDecoded, $oldUser['role']);
    $newPermissions = isset($finalPerms) ? $finalPerms : $oldPermsDecoded;

    $changedOld = [];
    $changedNew = [];

    if ($newUsername !== $oldUser['username']) {
        $changedOld['username'] = $oldUser['username'];
        $changedNew['username'] = $newUsername;
    }
    if ($newRole !== $oldUser['role']) {
        $changedOld['role'] = $oldUser['role'];
        $changedNew['role'] = $newRole;
    }
    if ($newIsActive !== (int)$oldUser['is_active']) {
        $changedOld['is_active'] = (int)$oldUser['is_active'];
        $changedNew['is_active'] = $newIsActive;
    }
    if (json_encode($newPermissions) !== json_encode($oldPermsDecoded)) {
        $changedOld['permissions'] = $oldPermsDecoded;
        $changedNew['permissions'] = $newPermissions;
    }

    if (!empty($changedOld)) {
        if (isset($changedOld['role'])) {
            audit_log($pdo, 'role_changed', 'user', $id, ['role' => $oldUser['role']], ['role' => $newRole]);
        }
        if (isset($changedOld['permissions'])) {
            $oldScreen = $oldPermissions['screen_permissions'] ?? [];
            $newScreen = normalize_user_permissions($newPermissions, $newRole)['screen_permissions'] ?? [];
            if (json_encode($oldScreen) !== json_encode($newScreen)) {
                audit_log($pdo, 'permissions_changed', 'user', $id, ['screen_permissions' => $oldScreen], ['screen_permissions' => $newScreen]);
            }
        }
        if (isset($changedOld['is_active'])) {
            $action = $newIsActive === 1 ? 'user_activated' : 'user_deactivated';
            audit_log($pdo, $action, 'user', $id, ['is_active' => (int)$oldUser['is_active']], ['is_active' => $newIsActive]);
        }
        
        audit_log($pdo, 'user_updated', 'user', $id, $changedOld, $changedNew);
    }

    respond($data, 200);
}

/*
|----------------------------------------------------------------------
| USERS - DELETE
|----------------------------------------------------------------------
*/
if (preg_match("#^users/(\d+)$#", $path, $m) && $method === "DELETE") {
    $auth = require_auth();
    $pdo = db();
    require_permission($pdo, $auth, 'user_management');

    $id = (int)$m[1];
    $loggedInUserId = (int)($auth["sub"] ?? $auth["uid"] ?? 0);
    if ($id === $loggedInUserId) {
        respond(["error" => "لا يمكنك حذف حسابك الخاص كمدير"], 400);
    }

    // Fetch details before delete
    $oldUserSt = $pdo->prepare("SELECT username, role, is_active FROM users WHERE id=?");
    $oldUserSt->execute([$id]);
    $oldUser = $oldUserSt->fetch(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$id]);

    if ($oldUser) {
        audit_log($pdo, 'user_deleted', 'user', $id, $oldUser, null);
    }

    respond(["message" => "تم حذف المستخدم"], 200);
}

respond(["error" => "غير موجود"], 404);
