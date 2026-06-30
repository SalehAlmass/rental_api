<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/attendance_calculator.php";

$auth = require_auth();
$path   = trim($_GET["path"] ?? "", "/");
$method = $_SERVER["REQUEST_METHOD"];
$pdo    = db();

// -----------------------------------------------------------------------------
// Schema (auto-migrate)
// -----------------------------------------------------------------------------

function ensure_attendance_schema(PDO $pdo): void {
  // Alter type column to VARCHAR(20) to support break types
  try {
    $pdo->exec("ALTER TABLE attendance_logs MODIFY COLUMN type VARCHAR(20) NOT NULL");
  } catch (Throwable $e) {}

  // Alter shift column to VARCHAR(20) NULL
  try {
    $pdo->exec("ALTER TABLE attendance_logs MODIFY COLUMN shift VARCHAR(20) NULL");
  } catch (Throwable $e) {}

  // attendance_logs base table
  $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    ts DATETIME NOT NULL,
    method VARCHAR(20) NULL,
    shift VARCHAR(20) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_ts (user_id, ts)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // New columns for metadata and daily summary metrics
  $cols = [
    'break_minutes' => "ALTER TABLE attendance_logs ADD COLUMN break_minutes INT NULL AFTER note",
    'late_minutes' => "ALTER TABLE attendance_logs ADD COLUMN late_minutes INT NULL AFTER break_minutes",
    'early_leave_minutes' => "ALTER TABLE attendance_logs ADD COLUMN early_leave_minutes INT NULL AFTER late_minutes",
    'overtime_minutes' => "ALTER TABLE attendance_logs ADD COLUMN overtime_minutes INT NULL AFTER early_leave_minutes",
    'worked_hours' => "ALTER TABLE attendance_logs ADD COLUMN worked_hours DECIMAL(10,2) NULL AFTER overtime_minutes",
    'device_timezone' => "ALTER TABLE attendance_logs ADD COLUMN device_timezone VARCHAR(100) NULL AFTER worked_hours",
    'device_platform' => "ALTER TABLE attendance_logs ADD COLUMN device_platform VARCHAR(50) NULL AFTER device_timezone",
    'device_app_version' => "ALTER TABLE attendance_logs ADD COLUMN device_app_version VARCHAR(50) NULL AFTER device_platform",
    'device_name' => "ALTER TABLE attendance_logs ADD COLUMN device_name VARCHAR(100) NULL AFTER device_app_version",
    'server_ts' => "ALTER TABLE attendance_logs ADD COLUMN server_ts DATETIME NULL AFTER device_name"
  ];

  foreach ($cols as $colName => $alterDdl) {
    try {
      if (!_col_exists($pdo, 'attendance_logs', $colName)) {
        $pdo->exec($alterDdl);
      }
    } catch (Throwable $e) {}
  }

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
if (!defined('HR_WEEKLY_HOLIDAY_DOW')) define('HR_WEEKLY_HOLIDAY_DOW', 5);
if (!defined('HR_MORNING_START')) define('HR_MORNING_START', '06:00:00');
if (!defined('HR_MORNING_END')) define('HR_MORNING_END', '12:00:00');
if (!defined('HR_EVENING_START')) define('HR_EVENING_START', '16:00:00');
if (!defined('HR_EVENING_END')) define('HR_EVENING_END', '21:00:00');
if (!defined('HR_GRACE_MINUTES')) define('HR_GRACE_MINUTES', 15);
if (!defined('HR_WORKDAY_HOURS')) define('HR_WORKDAY_HOURS', 11);

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

function _shift_bounds_for_ts(int $ts, ?string $forcedShift = null): array {
  $day = date('Y-m-d', $ts);
  $shift = in_array($forcedShift, ['morning', 'evening'], true) ? $forcedShift : null;
  if ($shift === null) {
    $midday = strtotime($day . ' 12:00:00');
    $shift = $ts < $midday ? 'morning' : 'evening';
  }
  if ($shift === 'evening') {
    return [strtotime($day . ' ' . HR_EVENING_START), strtotime($day . ' ' . HR_EVENING_END), 'evening'];
  }
  return [strtotime($day . ' ' . HR_MORNING_START), strtotime($day . ' ' . HR_MORNING_END), 'morning'];
}

function compute_hours(PDO $pdo, int $uid, string $from, string $to): float {
  return compute_hours_engine($pdo, $uid, $from, $to);
}

function get_monthly_attendance_summary(PDO $pdo, int $uid, string $month): array {
  [$from, $to] = _month_range($month);

  // 1. Fetch all logs for the month
  $st = $pdo->prepare("SELECT * FROM attendance_logs 
                       WHERE user_id=? AND ts>=? AND ts<=? 
                       ORDER BY ts ASC, id ASC");
  $st->execute([$uid, $from, $to]);
  $logs = $st->fetchAll(PDO::FETCH_ASSOC);

  // 2. Group logs by day
  $logsByDay = [];
  foreach ($logs as $l) {
    $day = substr($l['ts'], 0, 10);
    $logsByDay[$day][] = $l;
  }

  // 3. Loop through each day of the month to build the calendar and compute stats
  $presentDays = 0;
  $absentDays = 0;
  $lateDays = 0;
  $totalHours = 0.0;
  $totalOvertime = 0;
  $totalEarlyLeave = 0;
  $workingDays = 0;

  $arrivalTimes = [];
  $checkoutTimes = [];

  $calendar = [];

  $startTs = strtotime($from);
  $endTs = strtotime($to);
  $todayStr = date('Y-m-d');

  for ($t = $startTs; $t <= $endTs; $t = strtotime('+1 day', $t)) {
    $day = date('Y-m-d', $t);
    $isWeekend = date('w', $t) == HR_WEEKLY_HOLIDAY_DOW; // Friday

    if (!$isWeekend) {
      $workingDays++;
    }

    $dayLogs = $logsByDay[$day] ?? [];
    $status = 'absent';
    $dayMetrics = [
      'worked_hours' => 0.0,
      'break_minutes' => 0,
      'late_minutes' => 0,
      'early_leave_minutes' => 0,
      'overtime_minutes' => 0,
    ];

    if (!empty($dayLogs)) {
      // Find first 'in' log and last 'out' log
      $firstIn = null;
      $lastOut = null;
      foreach ($dayLogs as $l) {
        if ($l['type'] === 'in' && $firstIn === null) {
          $firstIn = $l;
        }
        if ($l['type'] === 'out') {
          $lastOut = $l;
        }
      }

      if ($firstIn !== null) {
        $status = 'present';
        $presentDays++;

        // Calculate arrival time
        $arrTime = substr($firstIn['ts'], 11, 5);
        $arrivalTimes[] = $arrTime;

        // Check if late (from check-in or checkout log)
        $lateMins = 0;
        if (isset($firstIn['late_minutes']) && $firstIn['late_minutes'] !== null) {
          $lateMins = (int)$firstIn['late_minutes'];
        } elseif ($lastOut !== null && isset($lastOut['late_minutes']) && $lastOut['late_minutes'] !== null) {
          $lateMins = (int)$lastOut['late_minutes'];
        } else {
          // fallback calculation
          [$expectedInTs, $expectedOutTs, $shift] = attendance_shift_bounds(strtotime($firstIn['ts']), $firstIn['shift']);
          $expectedInWithGrace = $expectedInTs + (HR_GRACE_MINUTES * 60);
          $actualIn = strtotime($firstIn['ts']);
          if ($actualIn > $expectedInWithGrace) {
            $lateMins = (int)floor(($actualIn - $expectedInWithGrace) / 60);
          }
        }

        if ($lateMins > 0) {
          $status = 'late';
          $lateDays++;
          $dayMetrics['late_minutes'] = $lateMins;
        }

        if ($lastOut !== null) {
          $checkoutTimes[] = substr($lastOut['ts'], 11, 5);
          
          $dayMetrics['worked_hours'] = (float)($lastOut['worked_hours'] ?? 0.0);
          $dayMetrics['break_minutes'] = (int)($lastOut['break_minutes'] ?? 0);
          $dayMetrics['early_leave_minutes'] = (int)($lastOut['early_leave_minutes'] ?? 0);
          $dayMetrics['overtime_minutes'] = (int)($lastOut['overtime_minutes'] ?? 0);

          $totalHours += $dayMetrics['worked_hours'];
          $totalOvertime += $dayMetrics['overtime_minutes'];
          $totalEarlyLeave += $dayMetrics['early_leave_minutes'];
        }
      }
    } else {
      if ($isWeekend) {
        $status = 'weekend';
      } else {
        if ($day > $todayStr) {
          $status = 'future';
        } else {
          $status = 'absent';
          $absentDays++;
        }
      }
    }

    $calendar[] = [
      'date' => $day,
      'is_weekend' => $isWeekend,
      'status' => $status,
      'metrics' => $dayMetrics,
      'logs' => $dayLogs,
    ];
  }

  // Calculate averages
  $avgArrival = '';
  if (!empty($arrivalTimes)) {
    $sumMin = 0;
    foreach ($arrivalTimes as $at) {
      if (strpos($at, ':') !== false) {
        [$h, $m] = explode(':', $at);
        $sumMin += $h * 60 + $m;
      }
    }
    $avgMin = (int)round($sumMin / count($arrivalTimes));
    $avgArrival = sprintf('%02d:%02d', floor($avgMin / 60), $avgMin % 60);
  }

  $avgCheckout = '';
  if (!empty($checkoutTimes)) {
    $sumMin = 0;
    foreach ($checkoutTimes as $ct) {
      if (strpos($ct, ':') !== false) {
        [$h, $m] = explode(':', $ct);
        $sumMin += $h * 60 + $m;
      }
    }
    $avgMin = (int)round($sumMin / count($checkoutTimes));
    $avgCheckout = sprintf('%02d:%02d', floor($avgMin / 60), $avgMin % 60);
  }

  $attendancePct = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 100.0;

  return [
    'stats' => [
      'working_days' => $workingDays,
      'present_days' => $presentDays,
      'absent_days' => $absentDays,
      'late_days' => $lateDays,
      'total_hours' => round($totalHours, 2),
      'total_overtime_minutes' => $totalOvertime,
      'total_early_leave_minutes' => $totalEarlyLeave,
      'attendance_percentage' => $attendancePct,
      'avg_arrival_time' => $avgArrival,
      'avg_checkout_time' => $avgCheckout,
    ],
    'calendar' => $calendar,
  ];
}

// -----------------------------------------------------------------------------
// Routes
// -----------------------------------------------------------------------------

// GET attendance/me
if ($path === 'attendance/me' && $method === 'GET') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'غير مصرح'], 401);
  
  $month = trim((string)($_GET['month'] ?? ''));
  if ($month === '') {
    $month = date('Y-m');
  }

  [$from, $to] = _month_range($month);

  // Get logs for the selected month range
  $st = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts DESC, id DESC LIMIT 500");
  $st->execute([$uid, $from, $to]);
  $logs = $st->fetchAll(PDO::FETCH_ASSOC);

  // Compute total hours in this month range
  $hours = compute_hours_engine($pdo, $uid, $from, date('Y-m-d H:i:s', strtotime($to) + 1));
  
  $last = last_log($pdo, $uid);
  
  // Resolve status
  $currentStatus = 'out';
  $activeBreakStart = null;
  if ($last) {
    $lastType = strtolower($last['type']);
    if ($lastType === 'in' || $lastType === 'break_end') {
      $currentStatus = 'working';
    } elseif ($lastType === 'break_start') {
      $currentStatus = 'break';
      $activeBreakStart = $last['ts'];
    }
  }

  // Get active checkin log
  $checkInLog = null;
  $expectedCheckoutTime = null;
  $currentShift = null;
  if ($currentStatus !== 'out') {
    $stIn = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND type='in' AND ts <= ? ORDER BY ts DESC LIMIT 1");
    $stIn->execute([$uid, $last['ts']]);
    $checkInLog = $stIn->fetch(PDO::FETCH_ASSOC);
    if ($checkInLog) {
      $currentShift = $checkInLog['shift'];
      [$shiftStart, $shiftEnd] = attendance_shift_bounds(strtotime($checkInLog['ts']), $checkInLog['shift']);
      $expectedCheckoutTime = date('Y-m-d H:i:s', $shiftEnd);
    }
  }

  // Today's live count-up stats
  $todayStart = date('Y-m-d 00:00:00');
  $todayEnd = date('Y-m-d 23:59:59');
  $stToday = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
  $stToday->execute([$uid, $todayStart, $todayEnd]);
  $todayLogs = $stToday->fetchAll(PDO::FETCH_ASSOC);

  $todayLogsWithOut = $todayLogs;
  if ($currentStatus !== 'out') {
    $todayLogsWithOut[] = [
      'type' => 'out',
      'ts' => date('Y-m-d H:i:s'),
      'shift' => $currentShift
    ];
  }

  $todaySec = compute_seconds_for_logs($todayLogsWithOut);
  $todayWorkedSeconds = $todaySec['worked_seconds'];
  $todayBreakSeconds = $todaySec['break_seconds'];

  // Late minutes today
  $todayLateMinutes = 0;
  if ($checkInLog) {
    [$shiftStart, $shiftEnd] = attendance_shift_bounds(strtotime($checkInLog['ts']), $checkInLog['shift']);
    $expectedInWithGrace = $shiftStart + (HR_GRACE_MINUTES * 60);
    if (strtotime($checkInLog['ts']) > $expectedInWithGrace) {
      $todayLateMinutes = (int)floor((strtotime($checkInLog['ts']) - $expectedInWithGrace) / 60);
    }
  }

  // Monthly stats and calendar summary
  $summary = get_monthly_attendance_summary($pdo, $uid, $month);

  respond([
    'success' => true,
    'data' => [
      'month' => $month,
      'from' => $from,
      'to' => $to,
      'in_duty' => ($currentStatus !== 'out'),
      'current_status' => $currentStatus,
      'active_break_start' => $activeBreakStart,
      'check_in_time' => $checkInLog ? $checkInLog['ts'] : null,
      'expected_checkout_time' => $expectedCheckoutTime,
      'current_shift' => $currentShift,
      'worked_hours' => $hours, // month worked hours
      'today' => [
        'worked_seconds' => $todayWorkedSeconds,
        'break_seconds' => $todayBreakSeconds,
        'late_minutes' => $todayLateMinutes,
      ],
      'logs' => $logs,
      'stats' => $summary['stats'],
      'calendar' => $summary['calendar']
    ]
  ]);
}

// POST attendance/checkin
if ($path === 'attendance/checkin' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'غير مصرح'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $methodName = $in['method'] ?? 'biometric';
  $note = $in['note'] ?? null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');
  $shift = in_array(($in['shift'] ?? ''), ['morning','evening'], true) ? $in['shift'] : null;
  if ($shift === null) { [, , $shift] = attendance_shift_bounds(strtotime($ts)); }

  // Device metadata
  $deviceTimezone = $in['device_timezone'] ?? null;
  $devicePlatform = $in['device_platform'] ?? null;
  $deviceAppVersion = $in['device_app_version'] ?? null;
  $deviceName = $in['device_name'] ?? null;

  // Validate state (cannot check in twice or when on break)
  $last = last_log($pdo, $uid);
  $status = 'out';
  if ($last) {
    if ($last['type'] === 'in' || $last['type'] === 'break_end') {
      $status = 'working';
    } elseif ($last['type'] === 'break_start') {
      $status = 'break';
    }
  }
  if ($status !== 'out') {
    respond(['success'=>false, 'error'=>'مسجل الدخول بالفعل'], 409);
  }

  // Validate: future timestamps
  if (strtotime($ts) > time() + 300) {
    respond(['success'=>false, 'error'=>'لا يمكن تسجيل الحضور بوقت في المستقبل'], 400);
  }

  $st = $pdo->prepare("INSERT INTO attendance_logs (
    user_id, type, ts, method, shift, note,
    device_timezone, device_platform, device_app_version, device_name, server_ts
  ) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
  $st->execute([
    $uid, 'in', $ts, $methodName, $shift, $note,
    $deviceTimezone, $devicePlatform, $deviceAppVersion, $deviceName
  ]);
  $logId = (int)$pdo->lastInsertId();

  // Determine lateness immediately at checkin
  [$expectedInTs, $expectedOutTs] = attendance_shift_bounds(strtotime($ts), $shift);
  $expectedInWithGrace = $expectedInTs + (HR_GRACE_MINUTES * 60);
  $lateMins = 0;
  if (strtotime($ts) > $expectedInWithGrace) {
    $lateMins = (int)floor((strtotime($ts) - $expectedInWithGrace) / 60);
    $stUpdate = $pdo->prepare("UPDATE attendance_logs SET late_minutes = ? WHERE id = ?");
    $stUpdate->execute([$lateMins, $logId]);

    audit_log($pdo, 'attendance_late', 'attendance', $logId, null, null, [
      'late_minutes' => $lateMins,
      'ts' => $ts
    ]);
  }

  // Audit checkin
  audit_log($pdo, 'attendance_check_in', 'attendance', $logId, null, null, [
    'ts' => $ts,
    'device_timezone' => $deviceTimezone,
    'device_platform' => $devicePlatform
  ]);

  respond(['success'=>true, 'data'=>['id'=>$logId, 'ts'=>$ts]]);
}

// POST attendance/break/start
if ($path === 'attendance/break/start' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'غير مصرح'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $note = $in['note'] ?? null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');

  $deviceTimezone = $in['device_timezone'] ?? null;
  $devicePlatform = $in['device_platform'] ?? null;
  $deviceAppVersion = $in['device_app_version'] ?? null;
  $deviceName = $in['device_name'] ?? null;

  $last = last_log($pdo, $uid);
  $status = 'out';
  if ($last) {
    if ($last['type'] === 'in' || $last['type'] === 'break_end') {
      $status = 'working';
    } elseif ($last['type'] === 'break_start') {
      $status = 'break';
    }
  }

  if ($status !== 'working') {
    respond(['success'=>false, 'error'=>'يجب تسجيل الدخول للعمل أولاً قبل بدء الاستراحة'], 400);
  }

  if (strtotime($ts) > time() + 300) {
    respond(['success'=>false, 'error'=>'لا يمكن بدء الاستراحة بوقت في المستقبل'], 400);
  }

  $st = $pdo->prepare("INSERT INTO attendance_logs (
    user_id, type, ts, note,
    device_timezone, device_platform, device_app_version, device_name, server_ts
  ) VALUES (?,?,?,?,?,?,?,?,NOW())");
  $st->execute([
    $uid, 'break_start', $ts, $note,
    $deviceTimezone, $devicePlatform, $deviceAppVersion, $deviceName
  ]);
  $logId = (int)$pdo->lastInsertId();

  audit_log($pdo, 'attendance_break_started', 'attendance', $logId, null, null, [
    'ts' => $ts
  ]);

  respond(['success'=>true, 'data'=>['id'=>$logId, 'ts'=>$ts]]);
}

// POST attendance/break/end
if ($path === 'attendance/break/end' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'غير مصرح'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $note = $in['note'] ?? null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');

  $deviceTimezone = $in['device_timezone'] ?? null;
  $devicePlatform = $in['device_platform'] ?? null;
  $deviceAppVersion = $in['device_app_version'] ?? null;
  $deviceName = $in['device_name'] ?? null;

  $last = last_log($pdo, $uid);
  $status = 'out';
  if ($last) {
    if ($last['type'] === 'in' || $last['type'] === 'break_end') {
      $status = 'working';
    } elseif ($last['type'] === 'break_start') {
      $status = 'break';
    }
  }

  if ($status !== 'break') {
    respond(['success'=>false, 'error'=>'لست في استراحة حالياً لإنهاؤها'], 400);
  }

  if (strtotime($ts) > time() + 300) {
    respond(['success'=>false, 'error'=>'لا يمكن إنهاء الاستراحة بوقت في المستقبل'], 400);
  }

  $st = $pdo->prepare("INSERT INTO attendance_logs (
    user_id, type, ts, note,
    device_timezone, device_platform, device_app_version, device_name, server_ts
  ) VALUES (?,?,?,?,?,?,?,?,NOW())");
  $st->execute([
    $uid, 'break_end', $ts, $note,
    $deviceTimezone, $devicePlatform, $deviceAppVersion, $deviceName
  ]);
  $logId = (int)$pdo->lastInsertId();

  audit_log($pdo, 'attendance_break_ended', 'attendance', $logId, null, null, [
    'ts' => $ts
  ]);

  respond(['success'=>true, 'data'=>['id'=>$logId, 'ts'=>$ts]]);
}

// POST attendance/checkout
if ($path === 'attendance/checkout' && $method === 'POST') {
  $uid = (int)($auth['sub'] ?? $auth['uid'] ?? 0);
  if ($uid <= 0) respond(['success'=>false,'error'=>'غير مصرح'], 401);
  $in = json_in();
  if (!$in) $in = $_POST;
  $methodName = $in['method'] ?? 'biometric';
  $note = $in['note'] ?? null;
  $shift = in_array(($in['shift'] ?? ''), ['morning','evening'], true) ? $in['shift'] : null;
  $ts = isset($in['ts']) ? to_dt((string)$in['ts']) : date('Y-m-d H:i:s');

  $deviceTimezone = $in['device_timezone'] ?? null;
  $devicePlatform = $in['device_platform'] ?? null;
  $deviceAppVersion = $in['device_app_version'] ?? null;
  $deviceName = $in['device_name'] ?? null;

  $last = last_log($pdo, $uid);
  $status = 'out';
  if ($last) {
    if ($last['type'] === 'in' || $last['type'] === 'break_end') {
      $status = 'working';
    } elseif ($last['type'] === 'break_start') {
      $status = 'break';
    }
  }

  if ($status === 'out') {
    respond(['success'=>false, 'error'=>'غير مسجل الدخول'], 409);
  }

  if (strtotime($ts) > time() + 300) {
    respond(['success'=>false, 'error'=>'لا يمكن تسجيل الانصراف بوقت في المستقبل'], 400);
  }

  // 1. Shift Closing Validation (TASK 7)
  $today = date('Y-m-d', strtotime($ts));
  $stPay = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id=? AND (is_void=0 OR is_void IS NULL) AND DATE(created_at)=?");
  $stPay->execute([$uid, $today]);
  $payCount = (int)$stPay->fetchColumn();

  if ($payCount > 0) {
    $stShift = $pdo->prepare("SELECT COUNT(*) FROM shift_closings WHERE user_id=? AND shift_date=?");
    $stShift->execute([$uid, $today]);
    $shiftCount = (int)$stShift->fetchColumn();

    if ($shiftCount === 0) {
      audit_log($pdo, 'attendance_checkout_blocked', 'attendance', null, null, null, [
        'reason' => 'pending_shift_closing',
        'date' => $today
      ]);
      respond([
        'success' => false,
        'error' => 'shift_not_closed',
        'message' => 'يوجد لديك إجراءات لم يتم إنهاؤها قبل تسجيل الانصراف.'
      ], 400);
    }
  }

  // 2. Auto Break End if on break
  if ($status === 'break') {
    $st = $pdo->prepare("INSERT INTO attendance_logs (
      user_id, type, ts, note,
      device_timezone, device_platform, device_app_version, device_name, server_ts
    ) VALUES (?,?,?,?,?,?,?,?,NOW())");
    $st->execute([
      $uid, 'break_end', $ts, 'إنهاء تلقائي للاستراحة عند تسجيل الانصراف',
      $deviceTimezone, $devicePlatform, $deviceAppVersion, $deviceName
    ]);
    audit_log($pdo, 'attendance_break_ended', 'attendance', (int)$pdo->lastInsertId(), null, null, [
      'ts' => $ts,
      'auto_end' => true
    ]);
  }

  // 3. Find checkin log to resolve shift
  $stIn = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND type='in' AND ts <= ? ORDER BY ts DESC LIMIT 1");
  $stIn->execute([$uid, $ts]);
  $checkInLog = $stIn->fetch(PDO::FETCH_ASSOC);

  $resolvedShift = $shift;
  if ($resolvedShift === null && $checkInLog) {
    $resolvedShift = $checkInLog['shift'];
  }
  if ($resolvedShift === null) {
    [, , $resolvedShift] = attendance_shift_bounds(strtotime($ts));
  }

  // 4. Calculate stats for this day
  $todayStart = date('Y-m-d 00:00:00', strtotime($ts));
  $todayEnd = date('Y-m-d 23:59:59', strtotime($ts));
  $stToday = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
  $stToday->execute([$uid, $todayStart, $todayEnd]);
  $todayLogs = $stToday->fetchAll(PDO::FETCH_ASSOC);

  // Append checkout log dynamically to calculate correct totals
  $todayLogsWithOut = $todayLogs;
  $todayLogsWithOut[] = [
    'type' => 'out',
    'ts' => $ts,
    'shift' => $resolvedShift
  ];

  $secMetrics = compute_seconds_for_logs($todayLogsWithOut);
  $breakSeconds = $secMetrics['break_seconds'];

  $checkInTimeTs = $checkInLog ? strtotime($checkInLog['ts']) : strtotime($ts);
  $metrics = calculate_attendance_metrics($checkInTimeTs, strtotime($ts), $breakSeconds, $resolvedShift);

  // 5. Insert out record
  $st = $pdo->prepare("INSERT INTO attendance_logs (
    user_id, type, ts, method, shift, note,
    break_minutes, late_minutes, early_leave_minutes, overtime_minutes, worked_hours,
    device_timezone, device_platform, device_app_version, device_name, server_ts
  ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
  $st->execute([
    $uid, 'out', $ts, $methodName, $resolvedShift, $note,
    $metrics['break_minutes'],
    $metrics['late_minutes'],
    $metrics['early_leave_minutes'],
    $metrics['overtime_minutes'],
    $metrics['worked_hours'],
    $deviceTimezone, $devicePlatform, $deviceAppVersion, $deviceName
  ]);
  $logId = (int)$pdo->lastInsertId();

  // Auditing checkout events
  audit_log($pdo, 'attendance_check_out', 'attendance', $logId, null, null, [
    'ts' => $ts,
    'worked_hours' => $metrics['worked_hours'],
    'break_minutes' => $metrics['break_minutes']
  ]);
  if ($metrics['late_minutes'] > 0) {
    audit_log($pdo, 'attendance_late', 'attendance', $logId, null, null, [
      'late_minutes' => $metrics['late_minutes']
    ]);
  }
  if ($metrics['early_leave_minutes'] > 0) {
    audit_log($pdo, 'attendance_early_leave', 'attendance', $logId, null, null, [
      'early_leave_minutes' => $metrics['early_leave_minutes']
    ]);
  }
  if ($metrics['overtime_minutes'] > 0) {
    audit_log($pdo, 'attendance_overtime', 'attendance', $logId, null, null, [
      'overtime_minutes' => $metrics['overtime_minutes']
    ]);
  }

  respond(['success'=>true, 'data'=>['id'=>$logId, 'ts'=>$ts, 'metrics'=>$metrics]]);
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
  $st = $pdo->prepare("SELECT type, ts, shift FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
  $st->execute([$uid, $from, $to]);
  $rows = $st->fetchAll();

  // Group by date for first valid check-in per day and detect shift
  $firstInByDay = []; // Y-m-d => ['ts'=>..., 'shift'=>...]
  foreach ($rows as $r) {
    if (strtolower((string)$r['type']) !== 'in') continue;
    $ts = strtotime((string)$r['ts']);
    if (!$ts) continue;
    $day = date('Y-m-d', $ts);
    [, , $shift] = _shift_bounds_for_ts($ts, $r['shift'] ?? null);
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
  if (strtolower((string)($auth['role'] ?? '')) !== 'admin' &&
      !has_permission($pdo, $auth, 'attendance_dashboard') &&
      !has_permission($pdo, $auth, 'attendance_manage') &&
      !has_permission($pdo, $auth, 'hr')) {
    respond(['success'=>false,'error'=>'ممنوع'], 403);
  }

  $users = $pdo->query("SELECT id, username, role FROM users WHERE is_active=1 ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
  
  $todayStart = date('Y-m-d 00:00:00');
  $todayEnd = date('Y-m-d 23:59:59');

  $workingCount = 0;
  $breakCount = 0;
  $outCount = 0;
  $lateCount = 0;
  $presentCount = 0;

  $items = [];

  foreach ($users as $u) {
    $uid = (int)$u['id'];
    
    $stLogs = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC, id ASC");
    $stLogs->execute([$uid, $todayStart, $todayEnd]);
    $logs = $stLogs->fetchAll(PDO::FETCH_ASSOC);

    $last = null;
    if (!empty($logs)) {
      $last = $logs[count($logs) - 1];
    } else {
      $last = last_log($pdo, $uid);
      if ($last && $last['type'] === 'out') {
        $last = null;
      }
    }

    $status = 'out';
    $checkInTime = null;
    $checkOutTime = null;
    $breakMins = 0;
    $lateMins = 0;
    $earlyMins = 0;
    $overtimeMins = 0;
    $workedHours = 0.0;
    $deviceInfo = null;

    $firstIn = null;
    $lastOut = null;
    foreach ($logs as $l) {
      if ($l['type'] === 'in' && $firstIn === null) {
        $firstIn = $l;
      }
      if ($l['type'] === 'out') {
        $lastOut = $l;
      }
    }

    if ($last) {
      $lastType = strtolower($last['type']);
      if ($lastType === 'in' || $lastType === 'break_end') {
        $status = 'working';
        $workingCount++;
        $presentCount++;
      } elseif ($lastType === 'break_start') {
        $status = 'break';
        $breakCount++;
        $presentCount++;
      } elseif ($lastType === 'out') {
        $status = 'out';
        $outCount++;
        $presentCount++;
      }
    } else {
      $status = 'out';
      $outCount++;
    }

    if ($firstIn !== null) {
      $checkInTime = $firstIn['ts'];
      if (isset($firstIn['late_minutes']) && $firstIn['late_minutes'] !== null) {
        $lateMins = (int)$firstIn['late_minutes'];
      } else {
        [$expectedInTs] = attendance_shift_bounds(strtotime($firstIn['ts']), $firstIn['shift']);
        $expectedInWithGrace = $expectedInTs + (HR_GRACE_MINUTES * 60);
        if (strtotime($firstIn['ts']) > $expectedInWithGrace) {
          $lateMins = (int)floor((strtotime($firstIn['ts']) - $expectedInWithGrace) / 60);
        }
      }
      if ($lateMins > 0) {
        $lateCount++;
      }
    }

    if ($lastOut !== null) {
      $checkOutTime = $lastOut['ts'];
      $breakMins = (int)($lastOut['break_minutes'] ?? 0);
      $earlyMins = (int)($lastOut['early_leave_minutes'] ?? 0);
      $overtimeMins = (int)($lastOut['overtime_minutes'] ?? 0);
      $workedHours = (float)($lastOut['worked_hours'] ?? 0.0);
      $deviceInfo = [
        'timezone' => $lastOut['device_timezone'],
        'platform' => $lastOut['device_platform'],
        'app_version' => $lastOut['device_app_version'],
        'name' => $lastOut['device_name']
      ];
    } else {
      if ($status !== 'out' && $firstIn) {
        $todayLogsWithOut = $logs;
        $todayLogsWithOut[] = [
          'type' => 'out',
          'ts' => date('Y-m-d H:i:s'),
          'shift' => $firstIn['shift']
        ];
        $secMetrics = compute_seconds_for_logs($todayLogsWithOut);
        $breakMins = (int)round($secMetrics['break_seconds'] / 60);
        $checkInTimeTs = strtotime($firstIn['ts']);
        $metrics = calculate_attendance_metrics($checkInTimeTs, time(), $secMetrics['break_seconds'], $firstIn['shift'] ?? 'morning');
        $workedHours = $metrics['worked_hours'];
        $earlyMins = $metrics['early_leave_minutes'];
        $overtimeMins = $metrics['overtime_minutes'];
      }
    }

    $row = [
      'user_id' => $uid,
      'username' => $u['username'],
      'role' => $u['role'],
      'status' => $status,
      'check_in_time' => $checkInTime,
      'check_out_time' => $checkOutTime,
      'late_minutes' => $lateMins,
      'early_leave_minutes' => $earlyMins,
      'overtime_minutes' => $overtimeMins,
      'break_minutes' => $breakMins,
      'worked_hours' => $workedHours,
      'device_info' => $deviceInfo
    ];

    $include = true;
    $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
    if ($filter === 'present') {
      $include = ($firstIn !== null);
    } elseif ($filter === 'absent') {
      $include = ($firstIn === null);
    } elseif ($filter === 'late') {
      $include = ($lateMins > 0);
    } elseif ($filter === 'break') {
      $include = ($status === 'break');
    } elseif ($filter === 'working') {
      $include = ($status === 'working');
    }

    if (isset($_GET['role']) && $_GET['role'] !== '') {
      if (strtolower(trim((string)$_GET['role'])) !== strtolower(trim((string)$u['role']))) {
        $include = false;
      }
    }

    if (isset($_GET['search']) && $_GET['search'] !== '') {
      $qSearch = strtolower(trim((string)$_GET['search']));
      if (strpos(strtolower($u['username']), $qSearch) === false) {
        $include = false;
      }
    }

    if ($include) {
      $items[] = $row;
    }
  }

  respond([
    'success' => true,
    'data' => [
      'date' => date('Y-m-d'),
      'counts' => [
        'total' => count($users),
        'working' => $workingCount,
        'break' => $breakCount,
        'out' => $outCount,
        'late' => $lateCount,
        'present' => $presentCount,
        'absent' => count($users) - $presentCount
      ],
      'items' => $items
    ]
  ]);
}

// GET attendance/summary?month=YYYY-MM  (Admin)
if ($path === 'attendance/summary' && $method === 'GET') {
  if (strtolower((string)$auth['role']) !== 'admin') {
    respond(['success'=>false, 'error'=>'ممنوع'], 403);
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

respond(["success"=>false, "error"=>"غير موجود"], 404);
