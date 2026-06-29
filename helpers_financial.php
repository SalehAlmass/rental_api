<?php
declare(strict_types=1);
/**
 * helpers_financial.php
 * =======================
 * Shared financial calculation helpers for Phase 7 reporting.
 * Single source of truth — used by all financial report APIs.
 *
 * Functions:
 *   fin_date_where()            — Build safe date-range WHERE clause
 *   calculate_total_revenue()   — Sum all non-void incoming payments
 *   calculate_rental_revenue()  — Revenue tied to rent contracts
 *   calculate_other_revenue()   — Revenue NOT tied to any rent
 *   calculate_maintenance_expenses()  — Equipment maintenance costs
 *   calculate_operational_expenses()  — Outgoing cash payments (type='out')
 *   calculate_payroll_expenses()      — Estimated payroll for the period
 *   calculate_depreciation_expenses() — Accounting depreciation entries
 *   calculate_total_expenses()        — Sum of all expenses
 *   calculate_profit_loss()           — Structured P&L object
 *   calculate_cash_flow()             — Opening/closing cash balances
 *   calculate_outstanding_amount()    — Total unpaid contract balances
 *   calculate_total_asset_value()     — Total book value of active equipment
 */

/**
 * Build a safe reusable date range condition array.
 * Returns ['conds'=>[], 'params'=>[]]
 */
function fin_date_where(string $col, ?string $from, ?string $to): array
{
    $conds = [];
    $params = [];
    if ($from !== null && $from !== '') {
        $conds[] = "$col >= ?";
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== null && $to !== '') {
        $conds[] = "$col <= ?";
        $params[] = $to . ' 23:59:59';
    }
    return ['conds' => $conds, 'params' => $params];
}

/**
 * Total incoming payments (type='in', not void) for a period.
 */
function calculate_total_revenue(PDO $pdo, ?string $from, ?string $to): float
{
    $dw = fin_date_where('p.created_at', $from, $to);
    $conds = array_merge(["p.type='in'", "p.is_void=0"], $dw['conds']);
    $where = 'WHERE ' . implode(' AND ', $conds);
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p $where");
    $st->execute($dw['params']);
    return (float)$st->fetchColumn();
}

/**
 * Revenue tied directly to rent contracts.
 */
function calculate_rental_revenue(PDO $pdo, ?string $from, ?string $to): float
{
    $dw = fin_date_where('p.created_at', $from, $to);
    $conds = array_merge(["p.type='in'", "p.is_void=0", "p.rent_id IS NOT NULL"], $dw['conds']);
    $where = 'WHERE ' . implode(' AND ', $conds);
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p $where");
    $st->execute($dw['params']);
    return (float)$st->fetchColumn();
}

/**
 * Revenue NOT tied to any rent (other income).
 */
function calculate_other_revenue(PDO $pdo, ?string $from, ?string $to): float
{
    $dw = fin_date_where('p.created_at', $from, $to);
    $conds = array_merge(["p.type='in'", "p.is_void=0", "p.rent_id IS NULL"], $dw['conds']);
    $where = 'WHERE ' . implode(' AND ', $conds);
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p $where");
    $st->execute($dw['params']);
    return (float)$st->fetchColumn();
}

/**
 * Total maintenance costs from equipment_maintenance table.
 */
function calculate_maintenance_expenses(PDO $pdo, ?string $from, ?string $to): float
{
    try {
        $dw = fin_date_where('created_at', $from, $to);
        $where = count($dw['conds']) ? ('WHERE ' . implode(' AND ', $dw['conds'])) : '';
        $st = $pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM equipment_maintenance $where");
        $st->execute($dw['params']);
        return (float)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Outgoing payments (type='out', not void) — operational expenses.
 */
function calculate_operational_expenses(PDO $pdo, ?string $from, ?string $to): float
{
    $dw = fin_date_where('p.created_at', $from, $to);
    $conds = array_merge(["p.type='out'", "p.is_void=0"], $dw['conds']);
    $where = 'WHERE ' . implode(' AND ', $conds);
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p $where");
    $st->execute($dw['params']);
    return (float)$st->fetchColumn();
}

/**
 * Estimated payroll expense for the period.
 * Uses attendance_logs + users salary settings (same logic as payroll.php).
 */
function calculate_payroll_expenses(PDO $pdo, ?string $from, ?string $to): float
{
    try {
        $fromDt = $from ? ($from . ' 00:00:00') : date('Y-m-01 00:00:00');
        $toDt   = $to   ? ($to   . ' 23:59:59') : date('Y-m-t 23:59:59');

        $users = $pdo->query("SELECT id, salary_type, hourly_rate, monthly_salary FROM users WHERE COALESCE(is_active,1)=1")->fetchAll(PDO::FETCH_ASSOC);
        $total = 0.0;

        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $salaryType = $u['salary_type'] ?? null;
            $hourlyRate = (float)($u['hourly_rate'] ?? 0);
            $monthlySalary = (float)($u['monthly_salary'] ?? 0);

            if ($salaryType === 'monthly' && $monthlySalary > 0) {
                // Pro-rate based on period vs calendar month span
                $fromTs = strtotime($fromDt);
                $toTs   = strtotime($toDt);
                $periodDays = max(1, (int)ceil(($toTs - $fromTs) / 86400));
                $total += round(($monthlySalary / 30.0) * $periodDays, 2);
            } elseif ($salaryType === 'hourly' && $hourlyRate > 0) {
                // Sum actual logged hours
                $st = $pdo->prepare("SELECT type, ts FROM attendance_logs WHERE user_id=? AND ts>=? AND ts<=? ORDER BY ts ASC");
                $st->execute([$uid, $fromDt, $toDt]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                $totalSec = 0;
                $openIn = null;
                foreach ($rows as $r) {
                    $t = strtolower($r['type']);
                    $ts = strtotime($r['ts']);
                    if (!$ts) continue;
                    if ($t === 'in') { $openIn = $ts; }
                    elseif ($t === 'out' && $openIn !== null && $ts > $openIn) {
                        $totalSec += ($ts - $openIn);
                        $openIn = null;
                    }
                }
                $hours = round($totalSec / 3600, 2);
                $total += round($hours * $hourlyRate, 2);
            }
        }
        return $total;
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Accounting depreciation expenses for the period (from depreciation entries).
 */
function calculate_depreciation_expenses(PDO $pdo, ?string $from, ?string $to): float
{
    try {
        // Convert from/to (YYYY-MM-DD) to months (YYYY-MM) for comparison
        $fromMonth = $from ? substr($from, 0, 7) : null;
        $toMonth   = $to   ? substr($to, 0, 7)   : null;

        $conds = ["depreciation_type='accounting'"];
        $params = [];
        if ($fromMonth) { $conds[] = "depreciation_month >= ?"; $params[] = $fromMonth; }
        if ($toMonth)   { $conds[] = "depreciation_month <= ?"; $params[] = $toMonth; }
        $where = 'WHERE ' . implode(' AND ', $conds);

        $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM equipment_depreciation_entries $where");
        $st->execute($params);
        return (float)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Sum of all expense categories.
 */
function calculate_total_expenses(PDO $pdo, ?string $from, ?string $to): array
{
    $maintenance  = calculate_maintenance_expenses($pdo, $from, $to);
    $operational  = calculate_operational_expenses($pdo, $from, $to);
    $payroll      = calculate_payroll_expenses($pdo, $from, $to);
    $depreciation = calculate_depreciation_expenses($pdo, $from, $to);
    $total = $maintenance + $operational + $payroll + $depreciation;

    return [
        'maintenance'  => round($maintenance, 2),
        'operational'  => round($operational, 2),
        'payroll'      => round($payroll, 2),
        'depreciation' => round($depreciation, 2),
        'total'        => round($total, 2),
    ];
}

/**
 * Build complete Profit & Loss structure.
 *
 * Returns:
 * [
 *   'income'   => [ 'rental_revenue', 'other_revenue', 'total_revenue' ],
 *   'costs'    => [ 'maintenance', 'depreciation', 'total_cost_of_revenue' ],
 *   'gross_profit' => …,
 *   'operating_expenses' => [ 'payroll', 'other_expenses', 'total_operating' ],
 *   'net_profit' => …,
 * ]
 */
function calculate_profit_loss(PDO $pdo, ?string $from, ?string $to): array
{
    $rentalRev  = calculate_rental_revenue($pdo, $from, $to);
    $otherRev   = calculate_other_revenue($pdo, $from, $to);
    $totalRev   = $rentalRev + $otherRev;

    $maintenance  = calculate_maintenance_expenses($pdo, $from, $to);
    $depreciation = calculate_depreciation_expenses($pdo, $from, $to);
    $totalCost    = $maintenance + $depreciation;

    $grossProfit = $totalRev - $totalCost;

    $payroll      = calculate_payroll_expenses($pdo, $from, $to);
    $operational  = calculate_operational_expenses($pdo, $from, $to);
    $totalOpex    = $payroll + $operational;

    $netProfit = $grossProfit - $totalOpex;

    return [
        'income' => [
            'rental_revenue' => round($rentalRev, 2),
            'other_revenue'  => round($otherRev, 2),
            'total_revenue'  => round($totalRev, 2),
        ],
        'cost_of_revenue' => [
            'maintenance'        => round($maintenance, 2),
            'depreciation'       => round($depreciation, 2),
            'total_cost'         => round($totalCost, 2),
        ],
        'gross_profit' => round($grossProfit, 2),
        'operating_expenses' => [
            'payroll'            => round($payroll, 2),
            'other_expenses'     => round($operational, 2),
            'total_operating'    => round($totalOpex, 2),
        ],
        'net_profit' => round($netProfit, 2),
    ];
}

/**
 * Cash Flow calculation.
 *
 * Opening balance = all cash movements BEFORE from_date.
 * Cash in  = sum of incoming payments in period.
 * Cash out = sum of outgoing payments + maintenance in period.
 * Closing  = opening + cash_in - cash_out
 */
function calculate_cash_flow(PDO $pdo, ?string $from, ?string $to): array
{
    // Opening balance: all transactions before $from
    $openingIn = 0.0;
    $openingOut = 0.0;
    if ($from) {
        $stOpen = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN type='in' THEN amount ELSE 0 END),0) as total_in,
            COALESCE(SUM(CASE WHEN type='out' THEN amount ELSE 0 END),0) as total_out
            FROM payments WHERE is_void=0 AND created_at < ?");
        $stOpen->execute([$from . ' 00:00:00']);
        $row = $stOpen->fetch(PDO::FETCH_ASSOC);
        $openingIn  = (float)($row['total_in'] ?? 0);
        $openingOut = (float)($row['total_out'] ?? 0);
    }
    $openingBalance = $openingIn - $openingOut;

    // Period cash in: by method
    $dwIn = fin_date_where('created_at', $from, $to);
    $condsCash = array_merge(["type='in'", "is_void=0"], $dwIn['conds']);
    $whereCash = 'WHERE ' . implode(' AND ', $condsCash);
    $stIn = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN method='cash' THEN amount ELSE 0 END),0) AS cash_in,
        COALESCE(SUM(CASE WHEN method='transfer' THEN amount ELSE 0 END),0) AS transfer_in,
        COALESCE(SUM(amount),0) AS total_in
        FROM payments $whereCash");
    $stIn->execute($dwIn['params']);
    $inRow = $stIn->fetch(PDO::FETCH_ASSOC);
    $cashIn      = (float)($inRow['cash_in'] ?? 0);
    $transferIn  = (float)($inRow['transfer_in'] ?? 0);
    $totalCashIn = (float)($inRow['total_in'] ?? 0);

    // Period cash out: payments
    $dwOut = fin_date_where('created_at', $from, $to);
    $condsOut = array_merge(["type='out'", "is_void=0"], $dwOut['conds']);
    $whereOut = 'WHERE ' . implode(' AND ', $condsOut);
    $stOut = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN method='cash' THEN amount ELSE 0 END),0) AS cash_out,
        COALESCE(SUM(CASE WHEN method='transfer' THEN amount ELSE 0 END),0) AS transfer_out,
        COALESCE(SUM(amount),0) AS total_out
        FROM payments $whereOut");
    $stOut->execute($dwOut['params']);
    $outRow = $stOut->fetch(PDO::FETCH_ASSOC);
    $cashOut      = (float)($outRow['cash_out'] ?? 0);
    $transferOut  = (float)($outRow['transfer_out'] ?? 0);
    $totalCashOut = (float)($outRow['total_out'] ?? 0);

    // Maintenance cash out
    $maintenanceCash = calculate_maintenance_expenses($pdo, $from, $to);
    $totalCashOut += $maintenanceCash;

    $netMovement    = $totalCashIn - $totalCashOut;
    $closingBalance = $openingBalance + $netMovement;

    return [
        'opening_balance' => round($openingBalance, 2),
        'cash_in' => [
            'cash'     => round($cashIn, 2),
            'transfer' => round($transferIn, 2),
            'total'    => round($totalCashIn, 2),
        ],
        'cash_out' => [
            'cash'        => round($cashOut, 2),
            'transfer'    => round($transferOut, 2),
            'maintenance' => round($maintenanceCash, 2),
            'total'       => round($totalCashOut, 2),
        ],
        'net_movement'    => round($netMovement, 2),
        'closing_balance' => round($closingBalance, 2),
    ];
}

/**
 * Total outstanding amount across all open contracts.
 */
function calculate_outstanding_amount(PDO $pdo): float
{
    try {
        $st = $pdo->query("SELECT COALESCE(SUM(remaining_amount),0) FROM rents WHERE status='open' AND remaining_amount > 0");
        return (float)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Total current book value of all active equipment.
 */
function calculate_total_asset_value(PDO $pdo): float
{
    try {
        $st = $pdo->query("SELECT COALESCE(SUM(book_value),0) FROM equipment WHERE COALESCE(is_active,1)=1");
        return (float)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}
