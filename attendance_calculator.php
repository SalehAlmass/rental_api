<?php
declare(strict_types=1);

/**
 * Attendance Calculation Engine (Phase 8.1)
 * Single source of truth for worked hours, breaks, lateness, early leave, and overtime.
 */

// Work Policy Constants (Saudi Arabian Standard)
if (!defined('HR_WEEKLY_HOLIDAY_DOW')) {
    define('HR_WEEKLY_HOLIDAY_DOW', 5); // Friday is weekly holiday
}
if (!defined('HR_MORNING_START')) {
    define('HR_MORNING_START', '06:00:00');
}
if (!defined('HR_MORNING_END')) {
    define('HR_MORNING_END', '12:00:00');
}
if (!defined('HR_EVENING_START')) {
    define('HR_EVENING_START', '16:00:00');
}
if (!defined('HR_EVENING_END')) {
    define('HR_EVENING_END', '21:00:00');
}
if (!defined('HR_GRACE_MINUTES')) {
    define('HR_GRACE_MINUTES', 15);
}
if (!defined('HR_WORKDAY_HOURS')) {
    define('HR_WORKDAY_HOURS', 11);
}

/**
 * Calculate shift bounds (start, end, shift name) for a given timestamp.
 */
function attendance_shift_bounds(int $ts, ?string $forcedShift = null): array {
    $day = date('Y-m-d', $ts);
    $shift = in_array($forcedShift, ['morning', 'evening'], true) ? $forcedShift : null;
    if ($shift === null) {
        $midday = strtotime($day . ' 12:00:00');
        $shift = $ts < $midday ? 'morning' : 'evening';
    }
    if ($shift === 'evening') {
        return [
            strtotime($day . ' ' . HR_EVENING_START),
            strtotime($day . ' ' . HR_EVENING_END),
            'evening'
        ];
    }
    return [
        strtotime($day . ' ' . HR_MORNING_START),
        strtotime($day . ' ' . HR_MORNING_END),
        'morning'
    ];
}

/**
 * Get expected check-in timestamp.
 */
function attendance_expected_in(int $dayTs, string $shift): int {
    $day = date('Y-m-d', $dayTs);
    return strtotime($day . ' ' . ($shift === 'evening' ? HR_EVENING_START : HR_MORNING_START));
}

/**
 * Get expected check-out timestamp.
 */
function attendance_expected_out(int $dayTs, string $shift): int {
    $day = date('Y-m-d', $dayTs);
    return strtotime($day . ' ' . ($shift === 'evening' ? HR_EVENING_END : HR_MORNING_END));
}

/**
 * Check if a given day timestamp is a workday (not Friday).
 */
function attendance_is_workday(int $ts): bool {
    return ((int)date('w', $ts)) !== HR_WEEKLY_HOLIDAY_DOW;
}

/**
 * Calculate attendance metrics on check-out.
 */
function calculate_attendance_metrics(int $checkInTs, int $checkOutTs, int $breakSeconds, string $shift): array {
    // 1. Shift bounds
    [$expectedInTs, $expectedOutTs] = attendance_shift_bounds($checkInTs, $shift);

    // 2. Late Minutes (with grace period)
    $lateMinutes = 0;
    $expectedInWithGrace = $expectedInTs + (HR_GRACE_MINUTES * 60);
    if ($checkInTs > $expectedInWithGrace) {
        $lateMinutes = (int)floor(($checkInTs - $expectedInWithGrace) / 60);
    }

    // 3. Early Leave Minutes
    $earlyLeaveMinutes = 0;
    if ($checkOutTs < $expectedOutTs) {
        $earlyLeaveMinutes = (int)floor(($expectedOutTs - $checkOutTs) / 60);
    }

    // 4. Worked Hours & Overtime
    $expectedDuration = $expectedOutTs - $expectedInTs; // Expected seconds
    
    // Worked seconds is total session duration minus break seconds
    $sessionDuration = $checkOutTs - $checkInTs;
    $workedSeconds = max(0, $sessionDuration - $breakSeconds);
    $workedHours = round($workedSeconds / 3600, 2);

    $overtimeSeconds = max(0, $workedSeconds - $expectedDuration);
    $overtimeMinutes = (int)round($overtimeSeconds / 60);

    return [
        'worked_hours' => $workedHours,
        'break_minutes' => (int)round($breakSeconds / 60),
        'late_minutes' => $lateMinutes,
        'early_leave_minutes' => $earlyLeaveMinutes,
        'overtime_minutes' => $overtimeMinutes,
    ];
}

/**
 * Calculate worked seconds and break seconds from daily logs (chronological order).
 * Subtracts breaks and handles clamping to shift bounds.
 */
function compute_seconds_for_logs(array $rows): array {
    $totalWorkedSec = 0;
    $totalBreakSec = 0;
    $openIn = null;
    $openShift = null;
    $currentBreaks = [];

    foreach ($rows as $r) {
        $t = strtolower(trim((string)$r['type']));
        $ts = strtotime((string)$r['ts']);
        if (!$ts) continue;

        if ($t === 'in') {
            $openIn = $ts;
            $openShift = in_array(($r['shift'] ?? ''), ['morning','evening'], true) ? $r['shift'] : null;
            $currentBreaks = [];
        } elseif ($t === 'break_start') {
            if ($openIn !== null) {
                $currentBreaks[] = ['start' => $ts, 'end' => null];
            }
        } elseif ($t === 'break_end') {
            if ($openIn !== null && !empty($currentBreaks)) {
                for ($i = count($currentBreaks) - 1; $i >= 0; $i--) {
                    if ($currentBreaks[$i]['end'] === null) {
                        $currentBreaks[$i]['end'] = $ts;
                        break;
                    }
                }
            }
        } elseif ($t === 'out') {
            if ($openIn !== null && $ts > $openIn) {
                // Close any open breaks
                for ($i = count($currentBreaks) - 1; $i >= 0; $i--) {
                    if ($currentBreaks[$i]['end'] === null) {
                        $currentBreaks[$i]['end'] = $ts;
                    }
                }

                [$shiftStart, $shiftEnd] = attendance_shift_bounds($openIn, $openShift);
                $startClamped = max($openIn, $shiftStart);
                $endClamped = min($ts, $shiftEnd);

                if ($endClamped > $startClamped) {
                    $sessionWorkedSec = $endClamped - $startClamped;
                    $sessionBreakSec = 0;

                    foreach ($currentBreaks as $b) {
                        $bStart = $b['start'];
                        $bEnd = $b['end'] ?? $ts;
                        
                        $bStartClamped = max($bStart, $startClamped);
                        $bEndClamped = min($bEnd, $endClamped);

                        if ($bEndClamped > $bStartClamped) {
                            $sessionBreakSec += ($bEndClamped - $bStartClamped);
                        }
                    }

                    $totalWorkedSec += max(0, $sessionWorkedSec - $sessionBreakSec);
                    $totalBreakSec += $sessionBreakSec;
                }
            }
            $openIn = null;
            $openShift = null;
            $currentBreaks = [];
        }
    }

    return [
        'worked_seconds' => $totalWorkedSec,
        'break_seconds' => $totalBreakSec
    ];
}

/**
 * Fetch and compute total worked hours for a user in a range.
 * Highly optimized, supports backward compatibility with pre-computed worked_hours.
 */
function compute_hours_engine(PDO $pdo, int $uid, string $from, string $to): float {
    $st = $pdo->prepare("SELECT type, ts, shift, worked_hours FROM attendance_logs
                         WHERE user_id=? AND ts>=? AND ts<?
                         ORDER BY ts ASC, id ASC");
    $st->execute([$uid, $from, $to]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $totalSec = 0;
    $openIn = null;
    $openShift = null;
    $currentBreaks = [];

    foreach ($rows as $r) {
        $t = strtolower(trim((string)$r['type']));
        $ts = strtotime((string)$r['ts']);
        if (!$ts) continue;

        if ($t === 'in') {
            $openIn = $ts;
            $openShift = in_array(($r['shift'] ?? ''), ['morning','evening'], true) ? $r['shift'] : null;
            $currentBreaks = [];
        } elseif ($t === 'break_start') {
            if ($openIn !== null) {
                $currentBreaks[] = ['start' => $ts, 'end' => null];
            }
        } elseif ($t === 'break_end') {
            if ($openIn !== null && !empty($currentBreaks)) {
                for ($i = count($currentBreaks) - 1; $i >= 0; $i--) {
                    if ($currentBreaks[$i]['end'] === null) {
                        $currentBreaks[$i]['end'] = $ts;
                        break;
                    }
                }
            }
        } elseif ($t === 'out') {
            if ($openIn !== null && $ts > $openIn) {
                // If pre-computed worked_hours is present in DB, use it directly (backward compatible)
                if (isset($r['worked_hours']) && $r['worked_hours'] !== null) {
                    $totalSec += (float)$r['worked_hours'] * 3600;
                } else {
                    for ($i = count($currentBreaks) - 1; $i >= 0; $i--) {
                        if ($currentBreaks[$i]['end'] === null) {
                            $currentBreaks[$i]['end'] = $ts;
                        }
                    }
                    [$shiftStart, $shiftEnd] = attendance_shift_bounds($openIn, $openShift);
                    $startClamped = max($openIn, $shiftStart);
                    $endClamped = min($ts, $shiftEnd);
                    if ($endClamped > $startClamped) {
                        $sessionWorkedSec = $endClamped - $startClamped;
                        $sessionBreakSec = 0;
                        foreach ($currentBreaks as $b) {
                            $bStart = $b['start'];
                            $bEnd = $b['end'] ?? $ts;
                            $bStartClamped = max($bStart, $startClamped);
                            $bEndClamped = min($bEnd, $endClamped);
                            if ($bEndClamped > $bStartClamped) {
                                $sessionBreakSec += ($bEndClamped - $bStartClamped);
                            }
                        }
                        $totalSec += max(0, $sessionWorkedSec - $sessionBreakSec);
                    }
                }
            }
            $openIn = null;
            $openShift = null;
            $currentBreaks = [];
        }
    }
    return round($totalSec / 3600, 2);
}
