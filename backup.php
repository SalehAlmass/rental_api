<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

// ✅ Require login
$auth   = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

date_default_timezone_set('Asia/Riyadh');

// مكان حفظ النسخ
$backupDir = __DIR__ . "/backups";
if (!is_dir($backupDir)) @mkdir($backupDir, 0777, true);

// تأكد من قابلية الكتابة
if (!is_writable($backupDir)) {
  // حاول تخليها قابلة للكتابة
  @chmod($backupDir, 0777);
}

function safe_name($name) {
  $name = basename((string)$name);
  // اسم آمن
  $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
  return $name;
}

function infer_backup_type($filename) {
  // backup_full_YYYY...sql | backup_def_YYYY...sql | backup_log_YYYY...sql
  if (preg_match('/^backup_(full|def|log)_/i', $filename, $m)) {
    return strtolower($m[1]);
  }
  // legacy name: backup_YYYY...
  return 'full';
}

function list_backups($dir) {
  $files = glob($dir . "/backup_*.sql");
  rsort($files);
  $out = [];
  foreach ($files as $f) {
    $name = basename($f);
    $bytes = @filesize($f);
    if ($bytes === false) $bytes = 0;
    $out[] = [
      // ✅ align with Flutter: file/size/created_at
      "file" => $name,
      "name" => $name, // keep backward compatibility
      "type" => infer_backup_type($name),
      "size" => (int)$bytes,
      "size_bytes" => (int)$bytes,
      "size_kb" => round(((float)$bytes) / 1024, 1),
      "created_at" => date("Y-m-d H:i:s", filemtime($f)),
      "modified_at" => date("Y-m-d H:i:s", filemtime($f)),
    ];
  }
  return $out;
}

function dump_database(PDO $pdo, string $mode = 'full') {
  // تحسينات للأداء
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
    $batchSize = 200; // عدّل إذا احتجت
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
      $vals = [];
      for ($i=0; $i<$colsCount; $i++) {
        $v = $r[$i];
        if ($v === null) {
          $vals[] = "NULL";
        } else {
          // Escaping آمن
          $vals[] = $pdo->quote($v);
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

function strip_sql_comments($sql) {
  // remove /* ... */ blocks
  $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
  // remove lines starting with -- or # (allow leading spaces)
  $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql);
  return $sql;
}

function split_sql_statements($sql) {
  // تقسيم بسيط (يكفي لمعظم النسخ التي نولدها هنا)
  $statements = [];
  $buffer = "";
  $inString = false;
  $stringChar = '';

  $len = strlen($sql);
  for ($i=0; $i<$len; $i++) {
    $ch = $sql[$i];
    $buffer .= $ch;

    if ($inString) {
      // خروج من النص
      if ($ch === $stringChar) {
        // تحقق من escape
        $backslashes = 0;
        $j = $i - 1;
        while ($j >= 0 && $sql[$j] === "\\") { $backslashes++; $j--; }
        if (($backslashes % 2) === 0) {
          $inString = false;
          $stringChar = '';
        }
      }
      continue;
    } else {
      if ($ch === "'" || $ch === '"') {
        $inString = true;
        $stringChar = $ch;
        continue;
      }
      if ($ch === ";") {
        $stmt = trim($buffer);
        $buffer = "";
        if ($stmt !== "" && $stmt !== ";") {
          $statements[] = $stmt;
        }
      }
    }
  }

  $tail = trim($buffer);
  if ($tail !== "") $statements[] = $tail;

  return $statements;
}

/*
|-----------------------------------------------------------
| GET backup/list
|-----------------------------------------------------------
*/
if ($path === "backup/list" && $method === "GET") {
  respond([
    "success" => true,
    "data" => list_backups($backupDir),
  ], 200);
}

/*
|-----------------------------------------------------------
| POST backup/create
|-----------------------------------------------------------
*/
if ($path === "backup/create" && $method === "POST") {
  if (!is_writable($backupDir)) {
    respond(["error" => "Backup folder is not writable: " . $backupDir], 500);
  }

  // وقت أطول للنسخ
  @set_time_limit(120);

  $in = json_in();
  $type = strtolower((string)($in['type'] ?? $_GET['type'] ?? 'full'));
  if (!in_array($type, ['full','def','log'], true)) {
    $type = 'full';
  }

  $filename = "backup_{$type}_" . date("Y-m-d_H-i-s") . ".sql";
  $fullpath = $backupDir . "/" . $filename;

  try {
    $sql = dump_database($pdo, $type);
    file_put_contents($fullpath, $sql);
    respond([
      "success" => true,
      "data" => [
        "file" => $filename,
        "name" => $filename,
        "type" => $type,
        "size" => (int)filesize($fullpath),
        "size_bytes" => (int)filesize($fullpath),
        "created_at" => date("Y-m-d H:i:s", filemtime($fullpath)),
        "modified_at" => date("Y-m-d H:i:s", filemtime($fullpath)),
      ],
    ], 201);
  } catch (Throwable $e) {
    respond(["error" => "Backup failed: " . $e->getMessage()], 500);
  }
}

/*
|-----------------------------------------------------------
| GET backup/download?name=backup_xxx.sql
|-----------------------------------------------------------
*/
if ($path === "backup/download" && $method === "GET") {
  $name = safe_name($_GET["name"] ?? "");
  if ($name === "") respond(["error" => "name required"], 422);

  $fullpath = $backupDir . "/" . $name;
  if (!file_exists($fullpath)) respond(["error" => "Backup not found"], 404);

  header("Content-Type: application/sql; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"$name\"");
  readfile($fullpath);
  exit;
}

/*
|-----------------------------------------------------------
| POST backup/restore
| body: { name: "backup_xxx.sql" }
|-----------------------------------------------------------
*/
if ($path === "backup/restore" && $method === "POST") {
  @set_time_limit(300);

  $in = json_in();
  // ✅ accept both 'name' and 'file' (Flutter used file previously)
  $name = safe_name($in["name"] ?? ($in["file"] ?? ""));
  if ($name === "") respond(["error" => "name required"], 422);

  $fullpath = $backupDir . "/" . $name;
  if (!file_exists($fullpath)) respond(["error" => "Backup not found"], 404);

  $sql = file_get_contents($fullpath);
  if ($sql === false || trim($sql) === "") respond(["error" => "Backup file is empty"], 422);

  try {
    // ✅ important: remove comments before splitting, otherwise statements start with comments and get skipped
    $sql = strip_sql_comments($sql);
    $stmts = split_sql_statements($sql);

    $pdo->beginTransaction();
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    foreach ($stmts as $s) {
      $s = trim($s);
      if ($s === "") continue;
      $pdo->exec($s);
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $pdo->commit();

    respond(["success" => true, "message" => "Restored successfully", "name" => $name], 200);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(["error" => "Restore failed: " . $e->getMessage()], 500);
  }
}
/*
|-----------------------------------------------------------
| DELETE backup/delete?name=backup_xxx.sql
| أو POST backup/delete  body: { name: "backup_xxx.sql" }
|-----------------------------------------------------------
*/
if ($path === "backup/delete" && in_array($method, ["DELETE","POST"], true)) {

  // استقبل الاسم من query أو body
  $name = $_GET["name"] ?? null;

  if ($name === null || $name === "") {
    $in = json_in();
    $name = $in["name"] ?? "";
  }

  $name = safe_name($name);
  if ($name === "") respond(["error" => "name required"], 422);

  $fullpath = $backupDir . "/" . $name;
  if (!file_exists($fullpath)) respond(["error" => "Backup not found"], 404);

  if (@unlink($fullpath)) {
    respond(["success" => true, "message" => "Deleted", "name" => $name], 200);
  } else {
    respond(["error" => "Delete failed (permission?)"], 500);
  }
}

/*
|-----------------------------------------------------------
| DELETE backup/clear  (يحذف كل النسخ backup_*.sql)
|-----------------------------------------------------------
*/
if ($path === "backup/clear" && $method === "DELETE") {

  $files = glob($backupDir . "/backup_*.sql") ?: [];
  $deleted = 0;
  $failed = [];

  foreach ($files as $f) {
    if (@unlink($f)) {
      $deleted++;
    } else {
      $failed[] = basename($f);
    }
  }

  respond([
    "success" => true,
    "deleted" => $deleted,
    "failed"  => $failed,
  ], 200);
}

respond(["error" => "Not Found"], 404);
