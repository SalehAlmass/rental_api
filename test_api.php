<?php
require 'config.php';
require 'helpers.php';
function require_auth() { return ['sub'=>1, 'role'=>'admin']; }
ob_start();
$_SERVER['REQUEST_METHOD']='GET';
$_GET['path']='shifts/today-summary';
$_GET['date']=date('Y-m-d');
try {
  require 'shifts.php';
} catch (Exception $e) {
  echo $e->getMessage();
}
$out = ob_get_clean();
echo "OUTPUT:\n$out";
