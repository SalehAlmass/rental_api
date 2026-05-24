<?php
require 'config.php';
require 'helpers.php';
$pdo = db();
  $st = $pdo->prepare("
    SELECT
      IFNULL(SUM(CASE WHEN LOWER(method) IN ('cash','???','????') THEN amount ELSE 0 END), 0) AS cash_total,
      IFNULL(SUM(CASE WHEN LOWER(method) IN ('transfer','?????','bank') THEN amount ELSE 0 END), 0) AS transfer_total,
      IFNULL(SUM(amount), 0) AS total_income
    FROM payments
    WHERE type='in'
      AND (is_void=0 OR is_void IS NULL)
      AND DATE(created_at) = '2026-05-23'
  ");
$st->execute();
print_r($st->fetchAll(PDO::FETCH_ASSOC));
