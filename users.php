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
        respond(["error" => "Forbidden"], 403);
    }

    $pdo = db();
    $st = $pdo->query(
        "SELECT id, username, role, is_active, created_at FROM users ORDER BY id DESC"
    );

    $users = array_map(function($u) {
        $u['id'] = (int)$u['id'];
        $u['is_active'] = (int)$u['is_active'];
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
    if ($auth["role"] !== "admin") respond(["error" => "Forbidden"], 403);

    $in = json_in();
    $username = trim((string)($in["username"] ?? ""));
    $password = trim((string)($in["password"] ?? ""));
    $role = trim((string)($in["role"] ?? "employee"));

    if ($username === "" || $password === "") respond(["error" => "Missing fields"], 400);

    $pdo = db();
    $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $check->execute([$username]);
    if ($check->fetch()) respond(["error" => "Username already exists"], 409);

    $st = $pdo->prepare(
        "INSERT INTO users (username,password,role,is_active,created_at) VALUES (?, ?, ?, 1, NOW())"
    );
    $st->execute([$username, $password, $role]);

    respond([
        "id" => (int)$pdo->lastInsertId(),
        "username" => $username,
        "role" => $role,
        "is_active" => 1,
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
    if ($auth["role"] !== "admin") respond(["error" => "Forbidden"], 403);

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

    if (!$fields) respond(["error" => "Nothing to update"], 400);

    $values[] = $id;
    $pdo = db();
    $st = $pdo->prepare("UPDATE users SET " . implode(",", $fields) . " WHERE id=?");
    $st->execute($values);

    $user = $pdo->prepare("SELECT id, username, role, is_active, created_at FROM users WHERE id=?");
    $user->execute([$id]);
    $data = $user->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $data['id'] = (int)$data['id'];
        $data['is_active'] = (int)$data['is_active'];
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
    if ($auth["role"] !== "admin") respond(["error" => "Forbidden"], 403);

    $id = (int)$m[1];
    $pdo = db();
    $st = $pdo->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$id]);

    respond(["message" => "User deleted"], 200);
}

respond(["error" => "Not Found"], 404);
