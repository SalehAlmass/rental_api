<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";

$auth = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// -----------------------------------------------------------------------------
// Schema (auto-migrate)
// -----------------------------------------------------------------------------

function ensure_attendance_schema(PDO $pdo): void {
  // attendance_logs
  $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('in','out') NOT NULL,
    ts DATETIME NOT NULL,
    method VARCHAR(20) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_ts (user_id, ts)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // payroll fields on users (non-breaking)
  try {
    $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) NULL");
  } catch (Throwable $e) {}
  try {
    $pdo->exec("ALTER TABLE users ADD COLUMN monthly_salary DECIMAL(10,2) NULL");
  } catch (Throwable $e) {}
  try {
    $pdo->exec("ALTER TABLE users ADD COLUMN salary_type ENUM('hourly','monthly') NULL");
  } catch (Throwable $e) {}
}

ensure_attendance_schema($pdo);

// -----------------------------------------------------------------------------
// Policy / Work rules
// -----------------------------------------------------------------------------
// Friday is weekly holiday (Saudi)
const HR_WEEKLY_HOLIDAY_DOW = 5; // 0=Sun ... 5=Fri
const HR_MORNING_START = '06:00:00';
const HR_MORNING_END   = '12:00:00';
const HR_EVENING_START = '16:00:00';
const HR_EVENING_END   = '21:00:00';
const HR_GRACE_MINUTES = 15; // lateness grace
const HR_WORKDAY_HOURS = 11;

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function to_dt(string $s): string {
  // accept ISO or SQL datetime
  $ts = strtotime($s);
  if (!$ts) return date('Y-m-d H:i:s');
  return date('Y-m-d H:i:s', $ts);
}

function last_log(PDO $pdo, int $uid): ?array {
  $st = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? ORDER BY ts DESC, id DESC LIMIT 1");
  $st->execute([$uid]);
  $row = $st->fetch();
  return $row ?: null;
}

function _shift_bounds_for_ts(int $ts): array {
  $day = date('Y-m-d', $ts);
  $midday = strtotime($day . ' 12:00:00');
  if ($ts < $midday) {
    return [strtotime($day . ' ' . HR_MORNING_START), strtotime($day . ' ' . HR_MORNING_END), 'morning'];
  }
  return [strtotime($day . ' ' . HR_EVENING_START), strtotime($day . ' ' . HR_EVENING_END), 'evening'];
}

function compute_hours(PDO $pdo, int $uid, string $from, string $to): float {
  $st = $pdo->prepare("SELECT type, ts FROM attendance_logs
                       WHERE user_id=? AND ts>=? AND ts<?
                       ORDER BY ts ASC, id ASC");
  $st->execute([$uid, $from, $to]);
  $rows = $st->fetchAll();

  $totalSec = 0;
  $openIn = null;
  foreach ($rows as $r) {
    $t = strtolower((string)$r['type']);
    $ts = strtotime((string)$r['ts']);
    if (!$ts) continue;
    if ($t === 'in') {
      $openIn = $ts;
    } elseif ($t === 'out') {
      if ($openIn !== null && $ts > $openIn) {
        [$shiftStart, $shiftEnd] = _shift_bounds_for_ts($openIn);
        $startClamped = max($openIn, $shiftStart);
        $endClamped = min($ts, $shiftEnd);
        if ($endClamped > $startClamped) {
          $totalSec += ($endClamped - $startClamped);
        }
      }
      $openIn = null;
    }
  }
  // لا نحتسب جلسة مفتوحة بدون خروج
  return round($totalSec / 3600, 2);
}

// -----------------------------------------------------------------------------
// Routes
// -----------------------------------------------------------------------------

// GET attendance/me?from=YYYY-MM-DD&to=YYYY-MM-DD
if ($path === 'attendance/me' && $method === 'GET') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'Unauthorized'], 401);
  $from = trim((string)($_GET['from'] ?? ''));
  $to   = trim((string)($_GET['to'] ?? ''));
  if ($from === '' || $to === '') {
    // default: current month
    $from = date('Y-m-01 00:00:00');
    $to = date('Y-m-d 23:59:59', strtotime('last day of this month'));
  } else {
    $from = date('Y-m-d 00:00:00', strtotime($from));
    $to = date('Y-m-d 23:59:59', strtotime($to));
  }

  $st = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts DESC, id DESC LIMIT 500");
  $st->execute([$uid, $from, $to]);
  $logs = $st->fetchAll();

  $hours = compute_hours($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to) + 1));
  $last = last_log($pdo, $uid);
  $status = $last ? strtolower((string)$last['type']) : 'out';
  $inDuty = ($status === 'in');

  respond([
    'success' => true,
    'data' => [
      'from' => $from,
      'to' => $to,
      'in_duty' => $inDuty,
      'worked_hours' => $hours,
      'logs' => $logs,
    ]
  ]);
}

// POST attendance/checkin
if ($path === 'attendance/checkin' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'Unauthorized'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $methodName = $in['method'] ?? 'biometric';
  $note = $in['note'] ?? null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');

  $last = last_log($pdo, $uid);
  if ($last && strtolower((string)$last['type']) === 'in') {
    respond(['success'=>false, 'error'=>'Already checked-in'], 409);
  }

  $st = $pdo->prepare("INSERT INTO attendance_logs (user_id, type, ts, method, note) VALUES (?,?,?,?,?)");
  $st->execute([$uid, 'in', $ts, $methodName, $note]);
  respond(['success'=>true, 'data'=>['id'=>(int)$pdo->lastInsertId(), 'ts'=>$ts]]);
}

// POST attendance/checkout
if ($path === 'attendance/checkout' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'Unauthorized'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $methodName = $in['method'] ?? 'biometric';
  $note = $in['note'] ?? null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');

  $last = last_log($pdo, $uid);
  if (!$last || strtolower((string)$last['type']) !== 'in') {
    respond(['success'=>false, 'error'=>'Not checked-in'], 409);
  }

  $st = $pdo->prepare("INSERT INTO attendance_logs (user_id, type, ts, method, note) VALUES (?,?,?,?,?)");
  $st->execute([$uid, 'out', $ts, $methodName, $note]);
  respond(['success'=>true, 'data'=>['id'=>(int)$pdo->lastInsertId(), 'ts'=>$ts]]);
}

// -----------------------------------------------------------------------------
// Admin monitoring (summary + filters)
// GET attendance/admin?month=YYYY-MM&filter=all|present|absent|late
// Friday is holiday
// -----------------------------------------------------------------------------

function _month_range(string $month): array {
  if ($month === '') $month = date('Y-m');
  $from = date('Y-m-01 00:00:00', strtotime($month . '-01'));
  $to = date('Y-m-d 23:59:59', strtotime('last day of ' . $month));
  return [$from, $to];
}

function _is_workday(int $ts): bool {
  $dow = (int)date('w', $ts); // 0..6
  return $dow !== HR_WEEKLY_HOLIDAY_DOW;
}

function _expected_in_ts(int $dayTs, string $shift): int {
  $day = date('Y-m-d', $dayTs);
  return strtotime($day . ' ' . ($shift === 'evening' ? HR_EVENING_START : HR_MORNING_START));
}

function compute_daily_metrics(PDO $pdo, int $uid, string $from, string $to): array {
  // Fetch logs for the user
  $st = $pdo->prepare("SELECT type, ts FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
  $st->execute([$uid, $from, $to]);
  $rows = $st->fetchAll();

  // Group by date for first valid check-in per day and detect shift
  $firstInByDay = []; // Y-m-d => ['ts'=>..., 'shift'=>...]
  foreach ($rows as $r) {
    if (strtolower((string)$r['type']) !== 'in') continue;
    $ts = strtotime((string)$r['ts']);
    if (!$ts) continue;
    $day = date('Y-m-d', $ts);
    [, , $shift] = _shift_bounds_for_ts($ts);
    if (!isset($firstInByDay[$day]) || $ts < $firstInByDay[$day]['ts']) {
      $firstInByDay[$day] = ['ts' => $ts, 'shift' => $shift];
    }
  }

  // Present / absent / late
  $presentDays = 0;
  $absentDays = 0;
  $lateMinutes = 0;
  $workDays = 0;

  $start = strtotime(substr($from, 0, 10) . ' 00:00:00');
  $end = strtotime(substr($to, 0, 10) . ' 23:59:59');
  for ($t = $start; $t <= $end; $t = strtotime('+1 day', $t)) {
    if (!_is_workday($t)) continue; // Friday holiday
    $workDays++;
    $day = date('Y-m-d', $t);
    if (!isset($firstInByDay[$day])) {
      $absentDays++;
      continue;
    }
    $presentDays++;

    $actualInfo = $firstInByDay[$day];
    $actual = (int)$actualInfo['ts'];
    $shift = (string)$actualInfo['shift'];
    $expected = _expected_in_ts($t, $shift) + (HR_GRACE_MINUTES * 60);
    if ($actual > $expected) {
      $lateMinutes += (int)floor(($actual - $expected) / 60);
    }
  }

  // Hours
  $hours = compute_hours($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to) + 1));

  return [
    'worked_hours' => $hours,
    'work_days' => $workDays,
    'present_days' => $presentDays,
    'absent_days' => $absentDays,
    'late_minutes' => $lateMinutes,
  ];
}

function compute_pay(PDO $pdo, array $userRow, array $metrics): array {
  $salaryType = $userRow['salary_type'] ?? null;
  $hourlyRate = isset($userRow['hourly_rate']) ? (float)$userRow['hourly_rate'] : 0.0;
  $monthlySalary = isset($userRow['monthly_salary']) ? (float)$userRow['monthly_salary'] : 0.0;

  $base = 0.0;
  $absenceDed = 0.0;
  $lateDed = 0.0;

  if ($salaryType === 'monthly' && $monthlySalary > 0) {
    $base = $monthlySalary;
    $workDays = max(1, (int)$metrics['work_days']);
    $dailyRate = $monthlySalary / $workDays;
    $effectiveHourly = $dailyRate / HR_WORKDAY_HOURS;
    $absenceDed = $metrics['absent_days'] * $dailyRate;
    $lateDed = $metrics['late_minutes'] * ($effectiveHourly / 60.0);
  } else {
    // hourly (default)
    $salaryType = 'hourly';
    $base = $metrics['worked_hours'] * $hourlyRate;
    $lateDed = $metrics['late_minutes'] * (($hourlyRate > 0 ? $hourlyRate : 0) / 60.0);
    $absenceDed = 0.0; // hourly: absence already reduces hours
  }

  $deductions = round($absenceDed + $lateDed, 2);
  $net = round(max(0, $base - $deductions), 2);

  return [
    'salary_type' => $salaryType,
    'hourly_rate' => $hourlyRate > 0 ? $hourlyRate : null,
    'monthly_salary' => $monthlySalary > 0 ? $monthlySalary : null,
    'base_amount' => round($base, 2),
    'absence_deduction' => round($absenceDed, 2),
    'late_deduction' => round($lateDed, 2),
    'deductions' => $deductions,
    'net_amount' => $net,
  ];
}

if ($path === 'attendance/admin' && $method === 'GET') {
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin') {
    respond(['success'=>false,'error'=>'Forbidden'], 403);
  }

  $month = trim((string)($_GET['month'] ?? ''));
  $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
  [$from, $to] = _month_range($month);

  $users = $pdo->query("SELECT id, username, role, hourly_rate, monthly_salary, salary_type FROM users WHERE is_active=1 ORDER BY id ASC")->fetchAll();
  $items = [];
  foreach ($users as $u) {
    $uid = (int)$u['id'];
    $metrics = compute_daily_metrics($pdo, $uid, $from, $to);
    $pay = compute_pay($pdo, $u, $metrics);

    $row = array_merge([
      'user_id' => $uid,
      'username' => $u['username'],
      'role' => $u['role'],
    ], $metrics, $pay);

    $include = true;
    if ($filter === 'present') {
      $include = ($metrics['present_days'] > 0);
    } elseif ($filter === 'absent') {
      $include = ($metrics['absent_days'] > 0);
    } elseif ($filter === 'late') {
      $include = ($metrics['late_minutes'] > 0);
    }

    if ($include) $items[] = $row;
  }

  respond(['success'=>true, 'data'=>[
    'month' => ($month === '' ? date('Y-m') : $month),
    'from' => $from,
    'to' => $to,
    'weekly_holiday' => 'Friday',
    'items' => $items,
  ]]);
}

// GET attendance/summary?month=YYYY-MM  (Admin)
if ($path === 'attendance/summary' && $method === 'GET') {
  if (strtolower((string)$auth['role']) !== 'admin') {
    respond(['success'=>false, 'error'=>'Forbidden'], 403);
  }
  $month = trim((string)($_GET['month'] ?? ''));
  if ($month === '') $month = date('Y-m');
  $from = date('Y-m-01 00:00:00', strtotime($month . '-01'));
  $to = date('Y-m-d 23:59:59', strtotime('last day of ' . $month));

  $users = $pdo->query("SELECT id, username, role, hourly_rate, monthly_salary, salary_type FROM users ORDER BY id ASC")->fetchAll();
  $out = [];
  foreach ($users as $u) {
    $uid = (int)$u['id'];
    $hours = compute_hours($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to)+1));
    $out[] = [
      'user_id' => $uid,
      'username' => $u['username'],
      'role' => $u['role'],
      'worked_hours' => $hours,
      'salary_type' => $u['salary_type'] ?? null,
      'hourly_rate' => isset($u['hourly_rate']) ? (float)$u['hourly_rate'] : null,
      'monthly_salary' => isset($u['monthly_salary']) ? (float)$u['monthly_salary'] : null,
    ];
  }
  respond(['success'=>true, 'data'=>['month'=>$month, 'from'=>$from, 'to'=>$to, 'items'=>$out]]);
}

respond(["success"=>false, "error"=>"Not Found"], 404);
