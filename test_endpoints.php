<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

// Test direct API calls
function api_call($path, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init('http://localhost/alkhair/rental_api/index.php?path=' . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    $opts = [CURLOPT_RETURNTRANSFER => true];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    if ($method === 'GET' && $data) {
        curl_close($ch);
        $ch = curl_init('http://localhost/alkhair/rental_api/index.php?path=' . $path . '&' . http_build_query($data));
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($res, true);
    return ['http' => $httpCode, 'body' => $decoded, 'raw' => $res];
}

// 1. Login
echo "=== 1. Login ===\n";
$r = api_call('auth/login', 'POST', ['username' => 'admin', 'password' => 'admin123']);
if (!$r['body'] || !isset($r['body']['token'])) {
    echo "LOGIN FAILED: " . json_encode($r['body']) . "\n";
    exit(1);
}
$token = $r['body']['token'];
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// 2. system-health
echo "=== 2. GET system-health ===\n";
$r = api_call('system-health', 'GET', null, $token);
echo "HTTP: {$r['http']}, Success: " . ($r['body']['success'] ?? false ? 'YES' : 'NO') . "\n";
if (!$r['body']['success']) {
    echo "Error: " . json_encode($r['body']['error'] ?? '') . "\n";
}

// 3. system-health/errors
echo "\n=== 3. GET system-health/errors ===\n";
$r = api_call('system-health/errors', 'GET', null, $token);
echo "HTTP: {$r['http']}, Count: " . count($r['body']['data'] ?? []) . "\n";
if (!empty($r['body']['data'])) {
    // Show first error with categorization
    $first = $r['body']['data'][0];
    echo "First error:\n";
    echo "  title_ar: " . ($first['title_ar'] ?? 'N/A') . "\n";
    echo "  cause_ar: " . ($first['cause_ar'] ?? 'N/A') . "\n";
    echo "  severity: " . ($first['severity'] ?? 'N/A') . "\n";
    echo "  suggested_action_ar: " . ($first['suggested_action_ar'] ?? 'N/A') . "\n";
}

// 4. system-integrity
echo "\n=== 4. GET system-integrity ===\n";
$r = api_call('system-integrity', 'GET', null, $token);
echo "HTTP: {$r['http']}, Success: " . ($r['body']['success'] ?? false ? 'YES' : 'NO') . "\n";
echo "Issues count: " . count($r['body']['issues'] ?? []) . "\n";

// 5. attendance/me
echo "\n=== 5. GET attendance/me ===\n";
$r = api_call('attendance/me', 'GET', ['month' => '2026-06'], $token);
echo "HTTP: {$r['http']}\n";
if (isset($r['body']['success']) && $r['body']['success']) {
    echo "Success: YES\n";
} else {
    echo "Response: " . substr(json_encode($r['body']), 0, 200) . "\n";
}

// 6. Test payroll endpoints
echo "\n=== 6. GET payroll/me ===\n";
$r = api_call('payroll/me', 'GET', ['month' => '2026-06'], $token);
echo "HTTP: {$r['http']}\n";
if (isset($r['body']['success']) && $r['body']['success']) {
    echo "Success: YES\n";
} else {
    echo "Response: " . substr(json_encode($r['body']), 0, 200) . "\n";
}

echo "\n=== All tests completed ===\n";
