<?php
require 'config.php';
require 'helpers.php';
$pdo = db();
echo "TABLES:\n";
print_r($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
echo "\nFOLLOWUPS SCHEMA:\n";
try {
    print_r($pdo->query('DESCRIBE collection_followups')->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
