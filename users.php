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
    $permissions = normalize_user_permissions($in['permissions'] ?? null, $role);

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
        $fields[] = "permissions_json=?";
        $values[] = json_encode(normalize_user_permissions($in['permissions'], $nextRole), JSON_UNESCAPED_UNICODE);
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
    $pdo = db();
    $st = $pdo->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$id]);

    respond(["message" => "تم حذف المستخدم"], 200);
}

respond(["error" => "غير موجود"], 404);
