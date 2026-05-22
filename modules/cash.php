<?php

declare(strict_types=1);

$pdo = db();
ensure_financial_expense_tables($pdo);

$langQ = i18n_lang_query();
$period = trim((string) ($_GET['period'] ?? 'month'));
if (!in_array($period, ['today', 'week', 'month'], true)) {
    $period = 'month';
}
$monthPick = trim((string) ($_GET['month'] ?? ''));
if ($period !== 'month') {
    $monthPick = '';
}
$range = financial_health_period_range($period, $monthPick !== '' ? $monthPick : null);
$summaryFrom = $range['from'];
$summaryTo = $range['to'];
$period = (string) $range['period'];
$monthPick = (string) ($range['month'] ?? '');
$isCurrentMonthView = $period === 'month' && $monthPick === '';

$buildCashUrl = static function (array $extra = []) use ($summaryFrom, $summaryTo, $period, $monthPick): string {
    $params = [
        'module' => 'cash',
        'period' => $period,
        'from' => $summaryFrom,
        'to' => $summaryTo,
    ];
    if ($monthPick !== '') {
        $params['month'] = $monthPick;
    }
    if (i18n_locale() === 'ps') {
        $params['lang'] = 'ps';
    }
    $params = array_merge($params, $extra);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
};

$monthOptions = financial_health_month_options(24);

$cashLogUrl = '?module=cash_log' . $langQ
    . '&log_from=' . rawurlencode($summaryFrom)
    . '&log_to=' . rawurlencode($summaryTo);

if (($_GET['ajax'] ?? '') === 'health_overview') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxPeriod = trim((string) ($_GET['period'] ?? 'month'));
    if (!in_array($ajaxPeriod, ['today', 'week', 'month'], true)) {
        $ajaxPeriod = 'month';
    }
    $ajaxMonth = trim((string) ($_GET['month'] ?? ''));
    if ($ajaxPeriod !== 'month') {
        $ajaxMonth = '';
    }
    $ajaxRange = financial_health_period_range($ajaxPeriod, $ajaxMonth !== '' ? $ajaxMonth : null);
    $ajaxFrom = $ajaxRange['from'];
    $ajaxTo = $ajaxRange['to'];
    $ajaxPeriod = (string) $ajaxRange['period'];
    $ajaxMonth = (string) ($ajaxRange['month'] ?? '');
    $ajaxHealth = financial_health_summary($pdo, $ajaxFrom, $ajaxTo);
    $ajaxChart = financial_health_chart_data($pdo, $ajaxFrom, $ajaxTo);
    $ajaxLogUrl = '?module=cash_log' . $langQ
        . '&log_from=' . rawurlencode($ajaxFrom)
        . '&log_to=' . rawurlencode($ajaxTo);
    echo json_encode([
        'ok' => true,
        'period' => $ajaxPeriod,
        'month' => $ajaxMonth,
        'from' => $ajaxFrom,
        'to' => $ajaxTo,
        'range_text' => format_date_pk($ajaxFrom) . ' – ' . format_date_pk($ajaxTo),
        'is_current_month_view' => $ajaxPeriod === 'month' && $ajaxMonth === '',
        'health' => $ajaxHealth,
        'chart' => $ajaxChart,
        'cash_log_url' => $ajaxLogUrl,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['cash_action'] ?? ''));
    $redirect = $buildCashUrl();

    if ($action === 'add_expense') {
        $desc = trim((string) ($_POST['description'] ?? ''));
        $amount = max(0, (float) ($_POST['amount'] ?? 0));
        $expenseDate = trim((string) ($_POST['expense_date'] ?? date('Y-m-d')));
        $category = trim((string) ($_POST['category'] ?? 'General')) ?: 'General';
        $paymentType = in_array((string) ($_POST['payment_type'] ?? ''), ['Cash', 'Bank'], true) ? (string) $_POST['payment_type'] : 'Cash';
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($desc !== '' && $amount > 0) {
            $st = $pdo->prepare('INSERT INTO general_expenses (description, category, amount, expense_date, payment_type, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $st->execute([$desc, $category, $amount, $expenseDate, $paymentType, $notes !== '' ? $notes : null]);
            header('Location: ' . $redirect . '&msg=expense_added');
            exit;
        }
        header('Location: ' . $redirect . '&err=expense_invalid');
        exit;
    }
    if ($action === 'add_salary') {
        $name = trim((string) ($_POST['employee_name'] ?? ''));
        $amount = max(0, (float) ($_POST['amount'] ?? 0));
        $salaryDate = trim((string) ($_POST['salary_date'] ?? date('Y-m-d')));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($name !== '' && $amount > 0) {
            $st = $pdo->prepare('INSERT INTO employee_salaries (employee_name, amount, salary_date, notes) VALUES (?, ?, ?, ?)');
            $st->execute([$name, $amount, $salaryDate, $notes !== '' ? $notes : null]);
            header('Location: ' . $redirect . '&msg=salary_added');
            exit;
        }
        header('Location: ' . $redirect . '&err=salary_invalid');
        exit;
    }
    if ($action === 'set_opening_cash') {
        $amount = max(0, (float) ($_POST['opening_amount'] ?? 0));
        $txDate = trim((string) ($_POST['opening_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            $txDate = date('Y-m-d');
        }
        if ($amount > 0 && table_exists($pdo, 'cash_transactions')) {
            $category = __('cash.opening_category');
            $description = __('cash.opening_description');
            $existing = $pdo->query('SELECT id FROM cash_transactions WHERE is_opening = 1 ORDER BY id ASC LIMIT 1')->fetch();
            if ($existing) {
                $st = $pdo->prepare(
                    'UPDATE cash_transactions SET category = ?, description = ?, inflow = ?, outflow = 0, transaction_date = ? WHERE id = ?'
                );
                $st->execute([$category, $description, $amount, $txDate, (int) $existing['id']]);
            } else {
                $st = $pdo->prepare(
                    'INSERT INTO cash_transactions (category, description, inflow, outflow, transaction_date, is_opening) VALUES (?, ?, ?, 0, ?, 1)'
                );
                $st->execute([$category, $description, $amount, $txDate]);
            }
            header('Location: ' . $redirect . '&msg=opening_cash_set');
            exit;
        }
        header('Location: ' . $redirect . '&err=opening_cash_invalid');
        exit;
    }
}

$openingCashRow = null;
if (table_exists($pdo, 'cash_transactions')) {
    $openingCashRow = $pdo->query(
        'SELECT id, inflow, transaction_date FROM cash_transactions WHERE is_opening = 1 ORDER BY id ASC LIMIT 1'
    )->fetch() ?: null;
}

$health = financial_health_summary($pdo, $summaryFrom, $summaryTo);
$chartData = financial_health_chart_data($pdo, $summaryFrom, $summaryTo);

$flashMsg = match ((string) ($_GET['msg'] ?? '')) {
    'expense_added' => __('dashboard.msg_expense_added'),
    'salary_added' => __('dashboard.msg_salary_added'),
    'opening_cash_set' => __('cash.msg_opening_set'),
    default => '',
};
$flashErr = match ((string) ($_GET['err'] ?? '')) {
    'expense_invalid', 'salary_invalid' => __('dashboard.err_invalid_entry'),
    'opening_cash_invalid' => __('cash.err_opening_invalid'),
    default => '',
};

$summaryRangeText = format_date_pk($summaryFrom) . ' – ' . format_date_pk($summaryTo);
$netTone = (string) ($health['net_tone'] ?? 'emerald');
$netBorder = $netTone === 'rose' ? 'border-rose-200 bg-gradient-to-br from-rose-50 to-white' : 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white';
$netText = $netTone === 'rose' ? 'text-rose-800' : 'text-emerald-800';
$netLabel = $netTone === 'rose' ? 'text-rose-700' : 'text-emerald-700';

$summaryHiddenFields = static function () use ($summaryFrom, $summaryTo, $period, $monthPick): void {
    ?>
    <input type="hidden" name="from" id="cashFilterFrom" value="<?= e($summaryFrom) ?>">
    <input type="hidden" name="to" id="cashFilterTo" value="<?= e($summaryTo) ?>">
    <input type="hidden" name="period" id="cashFilterPeriod" value="<?= e($period) ?>">
    <input type="hidden" name="month" id="cashFilterMonth" value="<?= e($monthPick) ?>">
    <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
    <?php
};

$periodBtnClass = static function (string $btnPeriod, string $activePeriod, bool $active): string {
    $base = 'px-4 py-2 rounded-lg text-sm font-medium transition';
    if ($active) {
        return $base . ' bg-primary text-white shadow-sm';
    }
    return $base . ' bg-white border border-slate-200 text-slate-700 hover:bg-slate-50';
};

ob_start();
?>
<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 pb-8 px-1 sm:px-0">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900"><?= e(__('cash.page_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl"><?= e(__('cash.page_intro')) ?></p>
            <p class="text-xs text-slate-400 mt-2"><?= e(__('cash.summary_range')) ?>: <span id="cashSummaryRange" class="font-medium text-slate-600"><?= e($summaryRangeText) ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <button type="button" class="btn btn-primary text-sm w-full sm:w-auto" data-open-modal="openingCashModal">+ <?= e(__('cash.btn_set_opening')) ?></button>
            <a id="cashTxLogLinkTop" href="<?= e($cashLogUrl) ?>" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('cash.link_transaction_log')) ?></a>
            <a href="?module=orders<?= $langQ ?>" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('nav.orders')) ?></a>
            <a href="?module=suppliers<?= $langQ ?>" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('nav.suppliers')) ?></a>
        </div>
    </div>

    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= e($flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= e($flashErr) ?></div>
    <?php endif; ?>

    <section id="cashHealthSection" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm space-y-4" aria-label="<?= e(__('cash.health_title')) ?>">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-800"><?= e(__('cash.health_title')) ?></h2>
                <p class="text-sm text-slate-500 mt-0.5"><?= e(__('cash.health_intro')) ?></p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-end gap-3 w-full lg:w-auto">
                <nav id="cashPeriodNav" class="inline-flex flex-wrap gap-1 p-1 rounded-xl bg-slate-100" aria-label="<?= e(__('dashboard.period_filter_label')) ?>">
                    <button type="button" data-cash-period="today" class="<?= e($periodBtnClass('today', $period, $period === 'today')) ?>"><?= e(__('cash.period_today')) ?></button>
                    <button type="button" data-cash-period="week" class="<?= e($periodBtnClass('week', $period, $period === 'week')) ?>"><?= e(__('cash.period_week')) ?></button>
                    <button type="button" data-cash-period="month" data-cash-month="" class="<?= e($periodBtnClass('month', $period, $isCurrentMonthView)) ?>"><?= e(__('cash.period_this_month')) ?></button>
                </nav>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="block min-w-[10rem]">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('cash.filter_month_label')) ?></span>
                        <select id="cashMonthPick" class="app-input mt-1 text-sm">
                            <option value=""><?= e(__('cash.filter_month_current')) ?></option>
                            <?php foreach ($monthOptions as $opt): ?>
                                <option value="<?= e($opt['value']) ?>" <?= $monthPick === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </div>

        <div id="cashHealthKpis" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 transition-opacity duration-200">
            <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700"><?= e(__('cash.kpi_revenue')) ?></p>
                <p id="cashKpiRevenue" class="text-xl sm:text-2xl font-bold text-emerald-800 mt-2 tabular-nums break-all"><?= e($health['revenue_formatted']) ?></p>
                <p class="text-xs text-emerald-600/90 mt-2"><?= e(__('cash.kpi_revenue_sub')) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('cash.kpi_purchases')) ?></p>
                <p id="cashKpiPurchases" class="text-xl sm:text-2xl font-bold text-sky-800 mt-2 tabular-nums break-all"><?= e($health['purchases_formatted']) ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('cash.kpi_purchases_sub')) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('cash.kpi_expenses')) ?></p>
                <p id="cashKpiExpenses" class="text-xl sm:text-2xl font-bold text-amber-700 mt-2 tabular-nums break-all"><?= e($health['expenses_formatted']) ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('cash.kpi_expenses_sub')) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('cash.kpi_salaries')) ?></p>
                <p id="cashKpiSalaries" class="text-xl sm:text-2xl font-bold text-amber-800 mt-2 tabular-nums break-all"><?= e($health['salaries_formatted']) ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('cash.kpi_salaries_sub')) ?></p>
            </div>
            <div id="cashKpiNetCard" class="rounded-2xl border <?= e($netBorder) ?> p-4 sm:p-5 shadow-sm sm:col-span-2 lg:col-span-1">
                <p id="cashKpiNetLabel" class="text-xs font-semibold uppercase tracking-wide <?= e($netLabel) ?>"><?= e(__('cash.kpi_net')) ?></p>
                <p id="cashKpiNet" class="text-xl sm:text-2xl font-bold <?= e($netText) ?> mt-2 tabular-nums break-all"><?= e($health['net_formatted']) ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('cash.kpi_net_sub')) ?></p>
            </div>
        </div>

        <div id="cashChartWrap" class="pt-2 border-t border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800"><?= e(__('cash.chart_health_title')) ?></h3>
            <p class="text-xs text-slate-500 mt-0.5 mb-3"><?= e(__('cash.chart_health_hint')) ?></p>
            <div class="h-64 sm:h-72 relative">
                <div id="cashChartEmpty" class="hidden absolute inset-0 flex items-center justify-center text-sm text-slate-400"><?= e(__('cash.chart_no_data')) ?></div>
                <canvas id="cashHealthChart" aria-hidden="true"></canvas>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50/80 to-white p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_daily_log')) ?></h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= e(__('cash.transaction_log_teaser')) ?></p>
        </div>
        <a id="cashTxLogLink" href="<?= e($cashLogUrl) ?>" class="btn btn-primary shrink-0 w-full sm:w-auto text-center"><?= e(__('cash.link_transaction_log')) ?></a>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800 mb-3"><?= e(__('dashboard.add_expense_title')) ?></h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="cash_action" value="add_expense">
                <?php $summaryHiddenFields(); ?>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_description')) ?></span>
                    <input name="description" required class="app-input mt-1" placeholder="<?= e(__('dashboard.ph_expense')) ?>">
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_amount')) ?></span>
                        <input name="amount" type="number" step="0.01" min="0.01" required class="app-input mt-1">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_date')) ?></span>
                        <input name="expense_date" type="date" value="<?= e(date('Y-m-d')) ?>" required class="app-input mt-1">
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_category')) ?></span>
                        <input name="category" class="app-input mt-1" value="General">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_payment_type')) ?></span>
                        <select name="payment_type" class="app-input mt-1">
                            <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                            <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto"><?= e(__('dashboard.btn_add_expense')) ?></button>
            </form>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800 mb-3"><?= e(__('dashboard.add_salary_title')) ?></h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="cash_action" value="add_salary">
                <?php $summaryHiddenFields(); ?>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_employee')) ?></span>
                    <input name="employee_name" required class="app-input mt-1" placeholder="<?= e(__('dashboard.ph_employee')) ?>">
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_amount')) ?></span>
                        <input name="amount" type="number" step="0.01" min="0.01" required class="app-input mt-1">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_date')) ?></span>
                        <input name="salary_date" type="date" value="<?= e(date('Y-m-d')) ?>" required class="app-input mt-1">
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto"><?= e(__('dashboard.btn_add_salary')) ?></button>
            </form>
        </div>
    </section>

    <div id="openingCashModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal="openingCashModal"></div>
        <div class="relative w-full max-w-md bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-xl">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-semibold text-slate-900"><?= e(__('cash.opening_modal_title')) ?></h3>
                <button type="button" class="btn btn-soft text-sm" data-close-modal="openingCashModal"><?= e(__('common.close')) ?></button>
            </div>
            <?php if ($openingCashRow): ?>
                <p class="text-sm text-slate-600 mb-3"><?= e(__('cash.opening_current', [
                    'amount' => format_currency((float) ($openingCashRow['inflow'] ?? 0)),
                    'date' => format_date_pk((string) ($openingCashRow['transaction_date'] ?? '')),
                ])) ?></p>
            <?php endif; ?>
            <form method="post" class="space-y-3">
                <input type="hidden" name="cash_action" value="set_opening_cash">
                <?php $summaryHiddenFields(); ?>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('cash.opening_amount')) ?></span>
                    <input name="opening_amount" type="number" step="0.01" min="0.01" required class="app-input mt-1"
                        value="<?= $openingCashRow ? e((string) ($openingCashRow['inflow'] ?? '')) : '' ?>">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.field_date')) ?></span>
                    <input name="opening_date" type="date" required class="app-input mt-1"
                        value="<?= e((string) ($openingCashRow['transaction_date'] ?? date('Y-m-d'))) ?>">
                </label>
                <p class="text-xs text-slate-500"><?= e(__('cash.opening_hint')) ?></p>
                <button type="submit" class="btn btn-primary w-full"><?= e(__('cash.btn_save_opening')) ?></button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const periodNav = document.getElementById('cashPeriodNav');
    const monthPick = document.getElementById('cashMonthPick');
    const kpiWrap = document.getElementById('cashHealthKpis');
    const rangeEl = document.getElementById('cashSummaryRange');
    const netCard = document.getElementById('cashKpiNetCard');
    const netLabel = document.getElementById('cashKpiNetLabel');
    const netValue = document.getElementById('cashKpiNet');
    const logLinks = [document.getElementById('cashTxLogLink'), document.getElementById('cashTxLogLinkTop')].filter(Boolean);
    const filterFrom = document.getElementById('cashFilterFrom');
    const filterTo = document.getElementById('cashFilterTo');
    const filterPeriod = document.getElementById('cashFilterPeriod');
    const filterMonth = document.getElementById('cashFilterMonth');
    const chartEmpty = document.getElementById('cashChartEmpty');
    const canvas = document.getElementById('cashHealthChart');

    const labelRevenue = <?= json_encode(__('cash.chart_revenue'), JSON_UNESCAPED_UNICODE) ?>;
    const labelOutflow = <?= json_encode(__('cash.chart_outflow'), JSON_UNESCAPED_UNICODE) ?>;
    const btnActive = 'px-4 py-2 rounded-lg text-sm font-medium transition bg-primary text-white shadow-sm';
    const btnIdle = 'px-4 py-2 rounded-lg text-sm font-medium transition bg-white border border-slate-200 text-slate-700 hover:bg-slate-50';

    let state = {
        period: <?= json_encode($period, JSON_UNESCAPED_UNICODE) ?>,
        month: <?= json_encode($monthPick, JSON_UNESCAPED_UNICODE) ?>,
    };
    let chartInstance = null;
    let loadSeq = 0;

    const resolveFilter = () => {
        const month = (monthPick?.value || '').trim();
        if (month !== '') {
            return { period: 'month', month };
        }
        return { period: state.period === 'month' && state.month ? 'month' : state.period, month: '' };
    };

    const setPeriodButtons = (period, month, isCurrentMonthView) => {
        if (!periodNav) return;
        periodNav.querySelectorAll('[data-cash-period]').forEach((btn) => {
            const p = btn.getAttribute('data-cash-period');
            const m = btn.getAttribute('data-cash-month') ?? '';
            const active = month === ''
                ? (p === 'today' && period === 'today')
                || (p === 'week' && period === 'week')
                || (p === 'month' && period === 'month' && isCurrentMonthView)
                : false;
            btn.className = active ? btnActive : btnIdle;
        });
    };

    const applyNetTone = (tone) => {
        if (!netCard || !netLabel || !netValue) return;
        const isRose = tone === 'rose';
        netCard.className = 'rounded-2xl border p-4 sm:p-5 shadow-sm sm:col-span-2 lg:col-span-1 '
            + (isRose
                ? 'border-rose-200 bg-gradient-to-br from-rose-50 to-white'
                : 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white');
        netLabel.className = 'text-xs font-semibold uppercase tracking-wide '
            + (isRose ? 'text-rose-700' : 'text-emerald-700');
        netValue.className = 'text-xl sm:text-2xl font-bold mt-2 tabular-nums break-all '
            + (isRose ? 'text-rose-800' : 'text-emerald-800');
    };

    const renderChart = (rows) => {
        if (!canvas || typeof Chart === 'undefined') return;
        const labels = (rows || []).map((r) => r.label);
        const revenue = (rows || []).map((r) => r.revenue);
        const outflow = (rows || []).map((r) => r.outflow);
        const hasData = labels.length > 0 && (revenue.some((v) => v > 0) || outflow.some((v) => v > 0));
        if (chartEmpty) {
            chartEmpty.classList.toggle('hidden', hasData);
        }
        canvas.style.visibility = hasData ? 'visible' : 'hidden';
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        if (!hasData) return;
        chartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: labelRevenue, data: revenue, backgroundColor: 'rgba(16, 185, 129, 0.75)' },
                    { label: labelOutflow, data: outflow, backgroundColor: 'rgba(244, 63, 94, 0.65)' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom' } },
            },
        });
    };

    const syncUrl = (period, month, from, to) => {
        const u = new URL(window.location.href);
        u.searchParams.set('module', 'cash');
        u.searchParams.set('period', period);
        if (month) {
            u.searchParams.set('month', month);
        } else {
            u.searchParams.delete('month');
        }
        if (from) u.searchParams.set('from', from);
        if (to) u.searchParams.set('to', to);
        window.history.replaceState({}, '', u.toString());
    };

    const updateHiddenFilters = (data) => {
        if (filterFrom) filterFrom.value = data.from || '';
        if (filterTo) filterTo.value = data.to || '';
        if (filterPeriod) filterPeriod.value = data.period || '';
        if (filterMonth) filterMonth.value = data.month || '';
    };

    const applyPayload = (data) => {
        const h = data.health || {};
        state.period = data.period;
        state.month = data.month || '';
        if (rangeEl && data.range_text) rangeEl.textContent = data.range_text;
        const rev = document.getElementById('cashKpiRevenue');
        const pur = document.getElementById('cashKpiPurchases');
        const exp = document.getElementById('cashKpiExpenses');
        const sal = document.getElementById('cashKpiSalaries');
        if (rev) rev.textContent = h.revenue_formatted || '';
        if (pur) pur.textContent = h.purchases_formatted || '';
        if (exp) exp.textContent = h.expenses_formatted || '';
        if (sal) sal.textContent = h.salaries_formatted || '';
        if (netValue) netValue.textContent = h.net_formatted || '';
        applyNetTone(h.net_tone || 'emerald');
        if (monthPick && monthPick.value !== (data.month || '')) {
            monthPick.value = data.month || '';
        }
        setPeriodButtons(data.period, data.month || '', !!data.is_current_month_view);
        logLinks.forEach((a) => {
            if (data.cash_log_url) a.href = data.cash_log_url;
        });
        updateHiddenFilters(data);
        renderChart(data.chart || []);
        syncUrl(data.period, data.month || '', data.from, data.to);
    };

    const loadHealth = async (period, month) => {
        const seq = ++loadSeq;
        if (kpiWrap) kpiWrap.classList.add('opacity-60');
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'cash');
            u.searchParams.set('ajax', 'health_overview');
            u.searchParams.set('period', period);
            if (month) {
                u.searchParams.set('month', month);
            } else {
                u.searchParams.delete('month');
            }
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (seq !== loadSeq || !data.ok) return;
            applyPayload(data);
        } catch (_) { /* ignore */ }
        finally {
            if (seq === loadSeq && kpiWrap) kpiWrap.classList.remove('opacity-60');
        }
    };

    const refresh = () => {
        const f = resolveFilter();
        state.period = f.period;
        state.month = f.month;
        loadHealth(f.period, f.month);
    };

    periodNav?.querySelectorAll('[data-cash-period]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const p = btn.getAttribute('data-cash-period');
            if (!p) return;
            if (monthPick) monthPick.value = '';
            state.period = p;
            state.month = '';
            loadHealth(p, '');
        });
    });

    monthPick?.addEventListener('change', () => {
        const month = (monthPick.value || '').trim();
        if (month !== '') {
            state.period = 'month';
            state.month = month;
            loadHealth('month', month);
        } else {
            state.period = 'month';
            state.month = '';
            loadHealth('month', '');
        }
    });

    renderChart(<?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>);

    if (window.OxygenFinance?.onUpdated) {
        window.OxygenFinance.onUpdated(() => {
            if (document.visibilityState === 'visible') refresh();
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.cash'), $content);
