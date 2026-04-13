<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$path = $_GET["path"] ?? "";
$path = trim($path, "/");

switch (true) {
  case str_starts_with($path, "auth"):
    require_once __DIR__ . "/auth.php";
    break;
    case str_starts_with($path, "print"):
  require_once __DIR__ . "/print.php";
  break;
  case str_starts_with($path, "backup"):
  require_once __DIR__ . "/backup.php";
  break;
  case str_starts_with($path, "users"):
    require_once __DIR__ . "/users.php";
    break;
  case str_starts_with($path, "clients"):
    require_once __DIR__ . "/clients.php";
    break;
  case str_starts_with($path, "equipment"):
    require_once __DIR__ . "/equipment.php";
    break;
  case str_starts_with($path, "rents"):
    require_once __DIR__ . "/rents.php";
    break;
  case str_starts_with($path, "payments"):
    require_once __DIR__ . "/payments.php";
    break;
	  case str_starts_with($path, "dashboard"):
	    require_once __DIR__ . "/dashboard.php";
	    break;
  case str_starts_with($path, "reports"):
    require_once __DIR__ . "/reports.php";
    break;
  case str_starts_with($path, "shifts"):
    require_once __DIR__ . "/shifts.php";
    break;
  case str_starts_with($path, "attendance"):
    require_once __DIR__ . "/attendance.php";
    break;
  case str_starts_with($path, "payroll"):
    require_once __DIR__ . "/payroll.php";
    break;
  case str_starts_with($path, "settings"):
    require_once __DIR__ . "/settings.php";
    break;
  default:
    respond(["ok"=>true, "service"=>"rental_api_php_fixed", "path"=>$path], 200);
}

