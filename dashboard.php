<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

ensure_financials_schema($pdo);

function ok($data, int $code = 200): void {
  respond(["success" => true, "data" => $data], $code);
}

function fail(string $msg, int $code = 400): void {
  respond(["success" => false, "error" => $msg], $code);
}

// GET /dashboard/summary
// Returns: income/expense today & month, open/closed rents counts, last 10 rents & payments
if ($path === 'dashboard/summary' && $method === 'GET') {
  $tz = 'Asia/Riyadh'; // adjust if needed
  $today = new DateTime('now', new DateTimeZone($tz));
  $todayStr = $today->format('Y-m-d');
  $monthStart = $today->format('Y-m-01');

  // income/expense (payments)
  $qSum = function(string $type, string $from, string $to) use ($pdo): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0)
                        FROM payments
                        WHERE type=?
                          AND (is_void=0 OR is_void IS NULL)
                          AND DATE(created_at) BETWEEN ? AND ?");
    $st->execute([$type, $from, $to]);
    return (float)$st->fetchColumn();
  };

  $incomeToday = $qSum('in', $todayStr, $todayStr);
  $expenseToday = $qSum('out', $todayStr, $todayStr);
  $incomeMonth = $qSum('in', $monthStart, $todayStr);
  $expenseMonth = $qSum('out', $monthStart, $todayStr);

  $st = $pdo->query("SELECT
                        SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_count,
                        SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) AS closed_count,
                        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
                      FROM rents");
  $counts = $st->fetch() ?: ["open_count" => 0, "closed_count" => 0, "cancelled_count" => 0];

  $lastRents = $pdo->query("SELECT r.*, c.name AS client_name, e.name AS equipment_name
                            FROM rents r
                            JOIN clients c ON r.client_id = c.id
                            JOIN equipment e ON r.equipment_id = e.id
                            ORDER BY r.id DESC
                            LIMIT 10")->fetchAll();

  $lastPayments = $pdo->query("SELECT p.*, c.name AS client_name
                               FROM payments p
                               LEFT JOIN clients c ON p.client_id = c.id
                               ORDER BY p.id DESC
                               LIMIT 10")->fetchAll();

  ok([
    'today' => [
      'income' => $incomeToday,
      'expense' => $expenseToday,
    ],
    'month' => [
      'income' => $incomeMonth,
      'expense' => $expenseMonth,
    ],
    'rents' => [
      'open' => (int)($counts['open_count'] ?? 0),
      'closed' => (int)($counts['closed_count'] ?? 0),
      'cancelled' => (int)($counts['cancelled_count'] ?? 0),
    ],
    'latest' => [
      'rents' => $lastRents,
      'payments' => $lastPayments,
    ],
  ]);
}

fail('Not Found', 404);
