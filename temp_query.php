<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'config.php';
require 'helpers.php';
$pdo = db();
$st = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5');
print_r($st->fetchAll(PDO::FETCH_ASSOC));
