<?php
require_once __DIR__ . "/config.php";
$pdo = db();

echo "=== FULL SYSTEM ERRORS AUDIT ===\n\n";

$st = $pdo->query("SELECT id, user_id, api, error_message, stack_trace, created_at FROM system_errors ORDER BY id ASC");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    echo "No errors found.\n";
    exit;
}

$categories = [];

foreach ($rows as $r) {
    $id = $r['id'];
    $msg = $r['error_message'];
    $api = $r['api'];
    $time = $r['created_at'];
    $trace = $r['stack_trace'] ?? '';

    // Determine category
    $cat = 'Unknown';
    $subcat = '';

    if (str_contains($msg, 'Constant') && str_contains($msg, 'already defined')) {
        $cat = 'PHP Error';
        $subcat = 'Constant already defined';
        $severity = 'low';
    } elseif (str_contains($msg, 'Column not found') || str_contains($msg, 'Unknown column')) {
        $cat = 'Database Error';
        $subcat = 'Unknown column';
        $severity = 'high';
    } elseif (str_contains($msg, 'Undefined array key') || str_contains($msg, 'Undefined index') || str_contains($msg, 'Undefined offset')) {
        $cat = 'PHP Error';
        $subcat = 'Undefined key/index';
        $severity = 'medium';
    } elseif (str_contains($msg, 'Division by zero')) {
        $cat = 'PHP Error';
        $subcat = 'Division by zero';
        $severity = 'medium';
    } elseif (str_contains($msg, 'Table') && (str_contains($msg, 'not found') || str_contains($msg, "doesn't exist"))) {
        $cat = 'Database Error';
        $subcat = 'Missing table';
        $severity = 'high';
    } elseif (str_contains($msg, 'SQLSTATE') || str_contains($msg, 'SQL') || str_contains($msg, 'PDO')) {
        $cat = 'Database Error';
        $subcat = 'SQL Error';
        $severity = 'high';
    } elseif (str_contains($msg, 'require') || str_contains($msg, 'include')) {
        $cat = 'PHP Error';
        $subcat = 'Include/require error';
        $severity = 'high';
    } elseif (str_contains($msg, 'Type') || str_contains($msg, 'must be') || str_contains($msg, 'Argument')) {
        $cat = 'PHP Error';
        $subcat = 'Type error';
        $severity = 'medium';
    } elseif (str_contains($msg, 'Authentication') || str_contains($msg, 'auth') || str_contains($msg, 'Unauthorized')) {
        $cat = 'API Error';
        $subcat = 'Authentication';
        $severity = 'high';
    } elseif (str_contains($msg, 'Permission') || str_contains($msg, 'Forbidden') || str_contains($msg, '403')) {
        $cat = 'API Error';
        $subcat = 'Permission';
        $severity = 'high';
    } else {
        $cat = 'Application Error';
        $subcat = 'Other';
        $severity = 'medium';
    }

    // Extract file/line from message
    $file = '';
    $line = '';
    if (preg_match('/in (.+?) on line (\d+)/', $msg, $m)) {
        $file = basename($m[1]);
        $line = $m[2];
    }

    // Determine if it's historical or still relevant
    $isHistorical = 'unknown';
    // Errors before our last fix (2026-06-30 11:30) are likely historical if they match fixed patterns
    $ts = strtotime($time);
    $fixTime = strtotime('2026-06-30 11:30:00');

    if ($subcat === 'Constant already defined') {
        $isHistorical = ($ts < $fixTime) ? 'historical (fixed)' : 'still possible';
    } elseif ($subcat === 'Unknown column' && str_contains($msg, 'r.created_by')) {
        $isHistorical = ($ts < $fixTime) ? 'historical (fixed)' : 'still possible';
    } elseif ($subcat === 'Undefined key/index') {
        $isHistorical = 'needs investigation';
    } else {
        $isHistorical = ($ts < $fixTime) ? 'historical (pre-fix)' : 'recent - needs investigation';
    }

    echo "=== Error #{$id} ===\n";
    echo "  Category:   [$cat] {$subcat}\n";
    echo "  Severity:   {$severity}\n";
    echo "  API:        {$api}\n";
    echo "  Time:       {$time}\n";
    echo "  File/Line:  {$file}:{$line}\n";
    echo "  Status:     {$isHistorical}\n";
    echo "  Message:    " . substr($msg, 0, 200) . "\n";
    echo "  Stack:      " . substr($trace, 0, 300) . "\n";
    echo "\n";

    if (!isset($categories[$cat])) $categories[$cat] = [];
    if (!isset($categories[$cat][$subcat])) $categories[$cat][$subcat] = 0;
    $categories[$cat][$subcat]++;
}

echo "\n=== SUMMARY BY CATEGORY ===\n\n";
foreach ($categories as $cat => $subcats) {
    echo "[{$cat}]\n";
    foreach ($subcats as $subcat => $count) {
        echo "  {$subcat}: {$count}\n";
    }
}

echo "\nTotal errors: " . count($rows) . "\n";
