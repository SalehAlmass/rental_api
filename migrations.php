<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/migrations/migration_manager.php";

$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// Only admins can run migrations
if (($auth["role"] ?? "") !== "admin") {
  respond(["error" => "Admin access required"], 403);
}

MigrationManager::ensureMigrationTableColumns($pdo);
$manager = new MigrationManager($pdo);

if ($method === "GET" && $path === "migrations/status") {
  respond($manager->status());
}

if ($method === "GET" && $path === "migrations/report") {
  $path = $manager->generateReport();
  $content = file_get_contents($path);
  header("Content-Type: text/markdown; charset=utf-8");
  echo $content;
  exit;
}

if ($method === "POST" && $path === "migrations/run") {
  respond($manager->run());
}

respond(["error" => "Not found"], 404);
