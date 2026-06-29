<?php
/**
 * test_financial_reports.php
 * ==========================
 * Verification test script for Phase 7 Financial Reports.
 * Run: php test_financial_reports.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers_financial.php';

$passed = 0;
$failed = 0;

function assert_true(string $label, bool $condition, $actual = null): void {
  global $passed, $failed;
  if ($condition) {
    echo "  ✅ PASS: $label\n";
    $passed++;
  } else {
    echo "  ❌ FAIL: $label" . ($actual !== null ? " (got: $actual)" : '') . "\n";
    $failed++;
  }
}

function assert_gte(string $label, float $actual, float $min): void {
  assert_true($label, $actual >= $min, "expected >= $min, got $actual");
}

function assert_eq_approx(string $label, float $a, float $b, float $tolerance = 0.01): void {
  assert_true($label, abs($a - $b) <= $tolerance, "expected ~$b, got $a");
}

echo "\n===== Phase 7 Financial Reports Test Suite =====\n\n";

$pdo = db();

// ─────────────────────────────────────────────
// REVENUE TESTS
// ─────────────────────────────────────────────
echo "1. Revenue Tests\n";

$totalRevenue = calculate_total_revenue($pdo, null, null);
assert_gte('Total revenue is non-negative', $totalRevenue, 0.0);

$rentalRevenue = calculate_rental_revenue($pdo, null, null);
$otherRevenue  = calculate_other_revenue($pdo, null, null);
assert_true(
  'Rental + Other revenue = Total revenue',
  abs(($rentalRevenue + $otherRevenue) - $totalRevenue) < 0.05
);

// Verify void payments excluded
$rawTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='in'")->fetchColumn();
assert_true('Non-void payments < raw total (void payments excluded)', $totalRevenue <= $rawTotal);

echo "\n2. Expense Tests\n";

$expenses = calculate_total_expenses($pdo, null, null);
assert_true('Total expenses array has all keys', isset($expenses['maintenance'], $expenses['payroll'], $expenses['depreciation'], $expenses['operational'], $expenses['total']));
assert_gte('Maintenance >= 0', $expenses['maintenance'], 0);
assert_gte('Payroll >= 0', $expenses['payroll'], 0);
assert_gte('Depreciation >= 0', $expenses['depreciation'], 0);
assert_gte('Operational >= 0', $expenses['operational'], 0);

$sumComponents = $expenses['maintenance'] + $expenses['payroll'] + $expenses['depreciation'] + $expenses['operational'];
assert_eq_approx('Sum of expense components = total', $sumComponents, $expenses['total']);

echo "\n3. Cash Flow Tests\n";

$cf = calculate_cash_flow($pdo, null, null);
assert_true('Cash flow has all keys', isset($cf['opening_balance'], $cf['cash_in'], $cf['cash_out'], $cf['net_movement'], $cf['closing_balance']));

$expectedClosing = $cf['opening_balance'] + $cf['net_movement'];
assert_eq_approx('Opening + Movement = Closing', $expectedClosing, $cf['closing_balance']);

$expectedNet = $cf['cash_in']['total'] - $cf['cash_out']['total'];
assert_eq_approx('CashIn - CashOut = Net', $expectedNet, $cf['net_movement']);

echo "\n4. P&L Structure Tests\n";

$pl = calculate_profit_loss($pdo, null, null);
assert_true('P&L has income section', isset($pl['income']['total_revenue']));
assert_true('P&L has cost_of_revenue section', isset($pl['cost_of_revenue']['total_cost']));
assert_true('P&L has operating_expenses section', isset($pl['operating_expenses']['total_operating']));
assert_true('P&L has gross_profit', isset($pl['gross_profit']));
assert_true('P&L has net_profit', isset($pl['net_profit']));

$expectedGross = $pl['income']['total_revenue'] - $pl['cost_of_revenue']['total_cost'];
assert_eq_approx('Revenue - Cost = Gross Profit', $expectedGross, $pl['gross_profit']);

$expectedNet = $pl['gross_profit'] - $pl['operating_expenses']['total_operating'];
assert_eq_approx('Gross - OpEx = Net Profit', $expectedNet, $pl['net_profit']);

echo "\n5. Permission Tests\n";

// Admin should have financial_reports permission (built-in)
$adminPerms = normalize_user_permissions(null, 'admin');
assert_true('Admin has financial_reports=true', $adminPerms['screen_permissions']['financial_reports'] === true);

// Manager should have financial_reports permission
$managerPerms = normalize_user_permissions(null, 'manager');
assert_true('Manager has financial_reports=true', $managerPerms['screen_permissions']['financial_reports'] === true);

// Employee should NOT have financial_reports permission
$empPerms = normalize_user_permissions(null, 'employee');
assert_true('Employee has financial_reports=false', $empPerms['screen_permissions']['financial_reports'] === false);

echo "\n6. Data Integrity Tests\n";

// Outstanding amount should be >= 0
$outstanding = calculate_outstanding_amount($pdo);
assert_gte('Outstanding amount >= 0', $outstanding, 0);

// Asset value should be >= 0
$assetValue = calculate_total_asset_value($pdo);
assert_gte('Asset value >= 0', $assetValue, 0);

// Date-filtered revenue should be <= total revenue
$monthRevenue = calculate_total_revenue($pdo, date('Y-m-01'), date('Y-m-d'));
assert_true('Monthly revenue <= total revenue', $monthRevenue <= $totalRevenue + 0.01);

echo "\n7. Index Verification\n";

// Check that key indexes exist
$indexChecks = [
  ['payments', 'idx_payments_type'],
  ['payments', 'idx_payments_method'],
  ['payments', 'idx_payments_is_void'],
  ['rents', 'idx_rents_status_created'],
];

foreach ($indexChecks as [$table, $indexName]) {
  $st = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
  $st->execute([$indexName]);
  $found = $st->rowCount() > 0;
  assert_true("Index $indexName exists on $table", $found);
}

echo "\n8. Financial Summary KPIs\n";

$profitMargin = $totalRevenue > 0 ? (($totalRevenue - $expenses['total']) / $totalRevenue) * 100 : 0;
echo "  ℹ️  Total Revenue:    " . number_format($totalRevenue, 2) . " SAR\n";
echo "  ℹ️  Total Expenses:   " . number_format($expenses['total'], 2) . " SAR\n";
echo "  ℹ️  Net Profit:       " . number_format($totalRevenue - $expenses['total'], 2) . " SAR\n";
echo "  ℹ️  Profit Margin:    " . number_format($profitMargin, 2) . "%\n";
echo "  ℹ️  Outstanding:      " . number_format($outstanding, 2) . " SAR\n";
echo "  ℹ️  Asset Value:      " . number_format($assetValue, 2) . " SAR\n";
echo "  ℹ️  Opening Balance:  " . number_format($cf['opening_balance'], 2) . " SAR\n";
echo "  ℹ️  Closing Balance:  " . number_format($cf['closing_balance'], 2) . " SAR\n";

echo "\n════════════════════════════════\n";
echo "  Results: $passed passed, $failed failed\n";
echo "════════════════════════════════\n\n";

exit($failed > 0 ? 1 : 0);
