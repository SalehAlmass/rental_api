<?php
declare(strict_types=1);

// Prevent browser/web execution (Cron security)
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  die("Forbidden: This script can only be run from the command line.\n");
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers.php";

$pdo = db();
echo "Starting monthly depreciation run...\n";
process_monthly_depreciation($pdo);
echo "Depreciation run completed successfully.\n";
