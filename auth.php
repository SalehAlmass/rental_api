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
    respond(["error" => "Missing fields"], 400);
  }

  $pdo = db();
  $st = $pdo->prepare(
    "SELECT id, username, role FROM users WHERE username=? AND password=?"
  );
  $st->execute([$username, $password]);
  $user = $st->fetch();

  if (!$user) {
    respond(["error" => "Invalid credentials"], 401);
  }

  global $JWT_SECRET;
  $token = jwt_sign([
    "sub"      => (int)$user["id"],
    "username" => $user["username"],
    "role"     => $user["role"],
    "exp"      => time() + 86400
  ], $JWT_SECRET);

  respond([
    "token" => $token,
    "user"  => $user
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
  if ($userId <= 0) respond(["error" => "Unauthorized"], 401);

  $pdo = db();
  $st = $pdo->prepare(
    "SELECT id, username, role, is_active, created_at FROM users WHERE id=?"
  );
  $st->execute([$userId]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u) respond(["error" => "User not found"], 404);

  $u['id'] = (int)$u['id'];
  $u['is_active'] = (int)$u['is_active'];

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
    respond(["error" => "Forbidden"], 403);
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
    respond(["error" => "Forbidden"], 403);
  }

  $in = json_in();
  $username = trim((string)($in["username"] ?? ""));
  $password = trim((string)($in["password"] ?? ""));
  $role     = trim((string)($in["role"] ?? "employee"));

  if ($username === "" || $password === "") {
    respond(["error" => "Missing fields"], 400);
  }

  $pdo = db();

  $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
  $check->execute([$username]);
  if ($check->fetch()) {
    respond(["error" => "Username already exists"], 409);
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
    respond(["error" => "Forbidden"], 403);
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
    respond(["error" => "Nothing to update"], 400);
  }

  $values[] = $id;

  $pdo = db();
  $st = $pdo->prepare(
    "UPDATE users SET " . implode(",", $fields) . " WHERE id=?"
  );
  $st->execute($values);

  $user = $pdo->prepare(
    "SELECT id, username, role, is_active, created_at FROM users WHERE id=?"
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
    respond(["error" => "Forbidden"], 403);
  }

  $pdo = db();
  $st = $pdo->prepare("DELETE FROM users WHERE id=?");
  $st->execute([(int)$m[1]]);

  respond(["message" => "User deleted"], 200);
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
    respond(["error" => "Missing fields"], 400);
  }

  $pdo = db();
  $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
  $st->execute([$userId]);
  $row = $st->fetch();

  if (!$row || $row["password"] !== $old) {
    respond(["error" => "Old password incorrect"], 401);
  }

  $up = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
  $up->execute([$new, $userId]);

  respond(["message" => "Password changed successfully"], 200);
}

/*
|--------------------------------------------------------------------------
| NOT FOUND
|--------------------------------------------------------------------------
*/
respond(["error" => "Not Found"], 404);
