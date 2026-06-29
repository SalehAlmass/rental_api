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
    if ($auth["role"] !== "admin") {
        respond(["error" => "ممنوع"], 403);
    }

    $pdo = db();
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
    if ($auth["role"] !== "admin") respond(["error" => "ممنوع"], 403);

    $in = json_in();
    $username = trim((string)($in["username"] ?? ""));
    $password = trim((string)($in["password"] ?? ""));
    $role = trim((string)($in["role"] ?? "employee"));
    $rawInput = isset($in['permissions']) && is_array($in['permissions']) ? $in['permissions'] : [];
    $normalized = normalize_user_permissions($rawInput, $role);
    $permissions = array_merge_recursive_distinct($rawInput, $normalized);

    if ($username === "" || $password === "") respond(["error" => "حقول مفقودة"], 400);

    $pdo = db();
    $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $check->execute([$username]);
    if ($check->fetch()) respond(["error" => "اسم المستخدم موجود بالفعل"], 409);

    $st = $pdo->prepare(
        "INSERT INTO users (username,password,role,is_active,created_at,permissions_json) VALUES (?, ?, ?, 1, NOW(), ?)"
    );
    $st->execute([$username, $password, $role, json_encode($permissions, JSON_UNESCAPED_UNICODE)]);

    respond([
        "id" => (int)$pdo->lastInsertId(),
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
    if ($auth["role"] !== "admin") respond(["error" => "ممنوع"], 403);

    $id = (int)$m[1];
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
            $values[] = $in[$f];
        }
    }

    if (isset($in["is_active"])) {
        $fields[] = "is_active=?";
        $values[] = (int)$in["is_active"];
    }

    $pdo = db();
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

    respond($data, 200);
}

/*
|----------------------------------------------------------------------
| USERS - DELETE
|----------------------------------------------------------------------
*/
if (preg_match("#^users/(\d+)$#", $path, $m) && $method === "DELETE") {
    $auth = require_auth();
    if ($auth["role"] !== "admin") respond(["error" => "ممنوع"], 403);

    $id = (int)$m[1];
    $loggedInUserId = (int)($auth["sub"] ?? $auth["uid"] ?? 0);
    if ($id === $loggedInUserId) {
        respond(["error" => "لا يمكنك حذف حسابك الخاص كمدير"], 400);
    }

    $pdo = db();
    $st = $pdo->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$id]);

    respond(["message" => "تم حذف المستخدم"], 200);
}

respond(["error" => "غير موجود"], 404);
