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
  try { $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) NULL"); } catch (Throwable $e) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN monthly_salary DECIMAL(10,2) NULL"); } catch (Throwable $e) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN salary_type ENUM('hourly','monthly') NULL"); } catch (Throwable $e) {}
}

ensure_attendance_schema($pdo);

// -----------------------------------------------------------------------------
// HR rules (Friday holiday)
// -----------------------------------------------------------------------------
const HR_WEEKLY_HOLIDAY_DOW = 5; // 0=Sun..6=Sat (5=Fri)
const HR_EXPECTED_IN = '08:00:00';
const HR_GRACE_MINUTES = 15;
const HR_WORKDAY_HOURS = 8;

function _is_workday(int $ts): bool {
  $dow = (int)date('w', $ts);
  return $dow !== HR_WEEKLY_HOLIDAY_DOW;
}

function _expected_in_ts(int $dayTs): int {
  return strtotime(date('Y-m-d', $dayTs) . ' ' . HR_EXPECTED_IN);
}

// Ensure schema exists (same as attendance.php but without routes)
try {
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
} catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN monthly_salary DECIMAL(10,2) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN salary_type ENUM('hourly','monthly') NULL"); } catch (Throwable $e) {}

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
        $totalSec += ($ts - $openIn);
      }
      $openIn = null;
    }
  }
  return round($totalSec / 3600, 2);
}

function compute_daily_metrics(PDO $pdo, int $uid, string $from, string $to): array {
  // first check-in per day (to compute late)
  $st = $pdo->prepare("SELECT type, ts FROM attendance_logs
                       WHERE user_id=? AND ts>=? AND ts<=?
                       ORDER BY ts ASC, id ASC");
  $st->execute([$uid, $from, $to]);
  $rows = $st->fetchAll();

  $firstInByDay = [];
  foreach ($rows as $r) {
    if (strtolower((string)$r['type']) !== 'in') continue;
    $ts = strtotime((string)$r['ts']);
    if (!$ts) continue;
    $day = date('Y-m-d', $ts);
    if (!isset($firstInByDay[$day]) || $ts < $firstInByDay[$day]) $firstInByDay[$day] = $ts;
  }

  $presentDays = 0;
  $absentDays = 0;
  $lateMinutes = 0;
  $workDays = 0;

  $start = strtotime(substr($from, 0, 10) . ' 00:00:00');
  $end = strtotime(substr($to, 0, 10) . ' 23:59:59');
  for ($t = $start; $t <= $end; $t = strtotime('+1 day', $t)) {
    if (!_is_workday($t)) continue;
    $workDays++;
    $day = date('Y-m-d', $t);
    if (!isset($firstInByDay[$day])) {
      $absentDays++;
      continue;
    }
    $presentDays++;
    $expected = _expected_in_ts($t) + (HR_GRACE_MINUTES * 60);
    $actual = $firstInByDay[$day];
    if ($actual > $expected) $lateMinutes += (int)floor(($actual - $expected) / 60);
  }

  $hours = compute_hours($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to) + 1));

  return [
    'worked_hours' => $hours,
    'work_days' => $workDays,
    'present_days' => $presentDays,
    'absent_days' => $absentDays,
    'late_minutes' => $lateMinutes,
  ];
}

function compute_pay(array $userRow, array $metrics): array {
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
    $salaryType = 'hourly';
    $base = $metrics['worked_hours'] * $hourlyRate;
    $lateDed = $metrics['late_minutes'] * (($hourlyRate > 0 ? $hourlyRate : 0) / 60.0);
    $absenceDed = 0.0;
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

function calc_amount(array $user, float $hours): float {
  $type = strtolower((string)($user['salary_type'] ?? ''));
  $hourly = isset($user['hourly_rate']) ? (float)$user['hourly_rate'] : 0.0;
  $monthly = isset($user['monthly_salary']) ? (float)$user['monthly_salary'] : 0.0;
  if ($type === 'monthly' && $monthly > 0) {
    // Simple: prorate based on 30 days * 8 hours = 240 hours baseline
    $baseHours = 240.0;
    return round(($hours / $baseHours) * $monthly, 2);
  }
  // default hourly
  return round($hours * $hourly, 2);
}

// GET payroll/me?month=YYYY-MM
if ($path === 'payroll/me' && $method === 'GET') {
  $uid = (int)$auth['uid'];
  $month = trim((string)($_GET['month'] ?? ''));
  if ($month === '') $month = date('Y-m');
  $from = date('Y-m-01 00:00:00', strtotime($month . '-01'));
  $to = date('Y-m-d 23:59:59', strtotime('last day of ' . $month));

  $st = $pdo->prepare("SELECT id, username, role, hourly_rate, monthly_salary, salary_type FROM users WHERE id=?");
  $st->execute([$uid]);
  $u = $st->fetch();
  if (!$u) respond(['success'=>false, 'error'=>'User not found'], 404);

  $hours = compute_hours($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to)+1));
  $amount = calc_amount($u, $hours);

  respond(['success'=>true, 'data'=>[
    'month'=>$month,
    'from'=>$from,
    'to'=>$to,
    'user'=>['id'=>(int)$u['id'], 'username'=>$u['username'], 'role'=>$u['role']],
    'worked_hours'=>$hours,
    'amount'=>$amount,
    'salary_type'=>$u['salary_type'] ?? null,
    'hourly_rate'=>isset($u['hourly_rate']) ? (float)$u['hourly_rate'] : null,
    'monthly_salary'=>isset($u['monthly_salary']) ? (float)$u['monthly_salary'] : null,
  ]]);
}

// GET payroll/summary?month=YYYY-MM (Admin)
if ($path === 'payroll/summary' && $method === 'GET') {
  if (strtolower((string)$auth['role']) !== 'admin') {
    respond(['success'=>false, 'error'=>'Forbidden'], 403);
  }
  $month = trim((string)($_GET['month'] ?? ''));
  if ($month === '') $month = date('Y-m');
  $from = date('Y-m-01 00:00:00', strtotime($month . '-01'));
  $to = date('Y-m-d 23:59:59', strtotime('last day of ' . $month));

  $users = $pdo->query("SELECT id, username, role, hourly_rate, monthly_salary, salary_type FROM users ORDER BY id ASC")->fetchAll();
  $items = [];
  foreach ($users as $u) {
    $uid = (int)$u['id'];
    $metrics = compute_daily_metrics($pdo, $uid, $from, $to);
    $pay = compute_pay($u, $metrics);
    $items[] = array_merge([
      'user_id' => $uid,
      'username' => $u['username'],
      'role' => $u['role'],
    ], $metrics, $pay, [
      // backward-compatible for older app builds
      'amount' => $pay['net_amount'],
    ]);
  }

  respond(['success'=>true, 'data'=>[
    'month'=>$month,
    'from'=>$from,
    'to'=>$to,
    'items'=>$items,
  ]]);
}

// PUT payroll/user/{id} (Admin) - update salary settings
if (preg_match('#^payroll/user/(\\d+)$#', $path, $m) && $method === 'PUT') {
  if (strtolower((string)$auth['role']) !== 'admin') {
    respond(['success'=>false, 'error'=>'Forbidden'], 403);
  }
  $uid = (int)$m[1];
  $in = json_in();
  if (!$in) $in = $_POST;
  $salaryType = isset($in['salary_type']) ? strtolower(trim((string)$in['salary_type'])) : null;
  $hourly = isset($in['hourly_rate']) ? (float)$in['hourly_rate'] : null;
  $monthly = isset($in['monthly_salary']) ? (float)$in['monthly_salary'] : null;

  if ($salaryType !== null && !in_array($salaryType, ['hourly','monthly'], true)) {
    respond(['success'=>false, 'error'=>'salary_type must be hourly or monthly'], 400);
  }

  $st = $pdo->prepare("UPDATE users SET salary_type=COALESCE(?, salary_type), hourly_rate=COALESCE(?, hourly_rate), monthly_salary=COALESCE(?, monthly_salary) WHERE id=?");
  $st->execute([$salaryType, $hourly, $monthly, $uid]);
  respond(['success'=>true, 'data'=>['ok'=>true]]);
}

respond(['success'=>false, 'error'=>'Not Found'], 404);
