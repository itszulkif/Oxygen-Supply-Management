<?php

declare(strict_types=1);

$pdo = db();
ensure_financial_expense_tables($pdo);

$langQ = i18n_lang_query();
$defaultRange = financial_overview_period_range('daily');
$defaultFrom = $defaultRange['from']->format('Y-m-d');
$defaultTo = $defaultRange['to']->format('Y-m-d');

$logFrom = trim((string) ($_GET['log_from'] ?? ''));
$logTo = trim((string) ($_GET['log_to'] ?? ''));
if ($logFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logFrom)) {
    $logFrom = $defaultFrom;
}
if ($logTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logTo)) {
    $logTo = $defaultTo;
}
if ($logFrom > $logTo) {
    [$logFrom, $logTo] = [$logTo, $logFrom];
}

$logType = trim((string) ($_GET['log_type'] ?? ''));
$allowedLogTypes = [
    'inflow_order',
    'inflow_customer_opening',
    'customer_opening_recorded',
    'inflow_opening',
    'inflow_cash',
    'outflow_cash',
    'outflow_supplier_purchase',
    'outflow_supplier',
    'outflow_expense',
    'outflow_salary',
];
if ($logType !== '' && !in_array($logType, $allowedLogTypes, true)) {
    $logType = '';
}
$logDirection = trim((string) ($_GET['log_direction'] ?? 'all'));
if (!in_array($logDirection, ['all', 'in', 'out'], true)) {
    $logDirection = 'all';
}
$logQ = trim((string) ($_GET['log_q'] ?? ''));
$logPage = max(1, (int) ($_GET['log_page'] ?? 1));
$logPerPage = (int) ($_GET['log_per_page'] ?? 25);
if (!in_array($logPerPage, [10, 25, 50, 100], true)) {
    $logPerPage = 25;
}

$logFilterParams = static function (array $extra = []) use ($logFrom, $logTo, $logType, $logDirection, $logQ, $logPage, $logPerPage): array {
    $base = [
        'module' => 'cash',
        'log_from' => $logFrom,
        'log_to' => $logTo,
        'log_direction' => $logDirection,
        'log_per_page' => (string) $logPerPage,
    ];
    if ($logType !== '') {
        $base['log_type'] = $logType;
    }
    if ($logQ !== '') {
        $base['log_q'] = $logQ;
    }
    if ($logPage > 1) {
        $base['log_page'] = (string) $logPage;
    }
    if (i18n_locale() === 'ps') {
        $base['lang'] = 'ps';
    }
    return array_merge($base, $extra);
};

$buildLogUrl = static function (array $extra = []) use ($logFilterParams): string {
    $params = array_merge($logFilterParams(), $extra);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
};

if (($_GET['ajax'] ?? '') === 'tx_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $txType = trim((string) ($_GET['type'] ?? ''));
    $txId = (int) ($_GET['id'] ?? 0);
    $detail = financial_transaction_detail($pdo, $txType, $txId);
    if (!$detail) {
        echo json_encode(['ok' => false, 'message' => __('dashboard.detail_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'detail' => $detail], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'export_cash_log_pdf') {
    $allLogTx = financial_overview_transactions($pdo, $logFrom, $logTo);
    $filteredLogTx = financial_filter_transactions($allLogTx, [
        'type' => $logType,
        'direction' => $logDirection,
        'q' => $logQ,
    ]);
    $summary = [];
    if ($logType !== '') {
        $summary[] = __('dashboard.filter_type') . ': ' . match ($logType) {
            'inflow_order' => __('dashboard.tx_type_order_payment'),
            'inflow_customer_opening' => __('dashboard.tx_type_customer_opening_payment'),
            'customer_opening_recorded' => __('dashboard.tx_type_customer_opening_recorded'),
            'inflow_opening' => __('dashboard.tx_type_opening'),
            'inflow_cash' => __('dashboard.tx_type_cash_in'),
            'outflow_cash' => __('dashboard.tx_type_cash_out'),
            'outflow_supplier_purchase' => __('dashboard.tx_type_supplier_purchase'),
            'outflow_supplier' => __('dashboard.tx_type_supplier'),
            'outflow_expense' => __('dashboard.tx_type_expense'),
            'outflow_salary' => __('dashboard.tx_type_salary'),
            default => $logType,
        };
    }
    if ($logDirection !== 'all') {
        $summary[] = __('dashboard.filter_direction') . ': ' . ($logDirection === 'in' ? __('dashboard.filter_direction_in') : __('dashboard.filter_direction_out'));
    }
    if ($logQ !== '') {
        $summary[] = __('dashboard.filter_search') . ': ' . $logQ;
    }
    financial_render_cash_log_pdf($pdo, $filteredLogTx, $logFrom, $logTo, $summary);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['cash_action'] ?? ''));
    $redirectExtra = ['log_page' => '1'];
    foreach (['log_from', 'log_to', 'log_type', 'log_direction', 'log_q', 'log_per_page'] as $logKey) {
        if (isset($_POST[$logKey]) && (string) $_POST[$logKey] !== '') {
            $redirectExtra[$logKey] = (string) $_POST[$logKey];
        }
    }
    $redirect = $buildLogUrl($redirectExtra);

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
    if ($action === 'delete_expense') {
        $id = (int) ($_POST['expense_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM general_expenses WHERE id = ?')->execute([$id]);
        }
        header('Location: ' . $redirect . '&msg=expense_deleted');
        exit;
    }
    if ($action === 'delete_salary') {
        $id = (int) ($_POST['salary_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM employee_salaries WHERE id = ?')->execute([$id]);
        }
        header('Location: ' . $redirect . '&msg=salary_deleted');
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

$totalInflow = financial_net_received_for_range($pdo, $logFrom, $logTo);
$supplierOut = table_exists($pdo, 'supplier_payments')
    ? array_sum(financial_sum_by_date($pdo, 'supplier_payments', 'payment_date', 'amount', $logFrom, $logTo))
    : 0.0;
$expenseOut = array_sum(financial_sum_by_date($pdo, 'general_expenses', 'expense_date', 'amount', $logFrom, $logTo));
$salaryOut = array_sum(financial_sum_by_date($pdo, 'employee_salaries', 'salary_date', 'amount', $logFrom, $logTo));

$allLogTransactions = financial_overview_transactions($pdo, $logFrom, $logTo);
$filteredLogTransactions = financial_filter_transactions($allLogTransactions, [
    'type' => $logType,
    'direction' => $logDirection,
    'q' => $logQ,
]);
$logPagination = financial_paginate_transactions($filteredLogTransactions, $logPage, $logPerPage);
$logPageItems = $logPagination['items'];
$logDailySummary = financial_group_transactions_for_log($filteredLogTransactions, 'day');
$logFilteredIn = 0.0;
$logFilteredOut = 0.0;
foreach ($filteredLogTransactions as $txSum) {
    $logFilteredIn += (float) ($txSum['inflow'] ?? 0);
    $logFilteredOut += (float) ($txSum['outflow'] ?? 0);
}

$logDayMap = [];
foreach ($logDailySummary as $daySum) {
    $logDayMap[(string) $daySum['group_key']] = $daySum;
}

$flashMsg = match ((string) ($_GET['msg'] ?? '')) {
    'expense_added' => __('dashboard.msg_expense_added'),
    'salary_added' => __('dashboard.msg_salary_added'),
    'expense_deleted' => __('dashboard.msg_expense_deleted'),
    'salary_deleted' => __('dashboard.msg_salary_deleted'),
    'opening_cash_set' => __('cash.msg_opening_set'),
    default => '',
};
$flashErr = match ((string) ($_GET['err'] ?? '')) {
    'expense_invalid', 'salary_invalid' => __('dashboard.err_invalid_entry'),
    'opening_cash_invalid' => __('cash.err_opening_invalid'),
    default => '',
};

$logRangeText = format_date_pk($logFrom) . ' – ' . format_date_pk($logTo);
$txDetailBaseUrl = $buildLogUrl(['ajax' => 'tx_detail', 'log_page' => null]);

$logHiddenFields = static function () use ($logFrom, $logTo, $logType, $logDirection, $logQ, $logPerPage): void {
    ?>
    <input type="hidden" name="log_from" value="<?= e($logFrom) ?>">
    <input type="hidden" name="log_to" value="<?= e($logTo) ?>">
    <input type="hidden" name="log_direction" value="<?= e($logDirection) ?>">
    <input type="hidden" name="log_per_page" value="<?= (int) $logPerPage ?>">
    <?php if ($logType !== ''): ?><input type="hidden" name="log_type" value="<?= e($logType) ?>"><?php endif; ?>
    <?php if ($logQ !== ''): ?><input type="hidden" name="log_q" value="<?= e($logQ) ?>"><?php endif; ?>
    <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
    <?php
};

$renderTxTypeBadge = static function (string $type): string {
    return match ($type) {
        'inflow_order' => 'bg-emerald-100 text-emerald-800',
        'inflow_customer_opening' => 'bg-emerald-100 text-emerald-900',
        'customer_opening_recorded' => 'bg-sky-50 text-sky-900',
        'inflow_opening' => 'bg-sky-100 text-sky-800',
        'inflow_cash' => 'bg-teal-100 text-teal-800',
        'outflow_cash' => 'bg-orange-100 text-orange-800',
        'outflow_supplier_purchase' => 'bg-violet-100 text-violet-900',
        'outflow_supplier' => 'bg-indigo-100 text-indigo-800',
        'outflow_salary' => 'bg-amber-100 text-amber-900',
        default => 'bg-slate-100 text-slate-700',
    };
};

ob_start();
?>
<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 pb-8 px-1 sm:px-0">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900"><?= e(__('cash.page_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl"><?= e(__('cash.page_intro')) ?></p>
            <p class="text-xs text-slate-400 mt-2"><?= e(__('cash.summary_range')) ?>: <span class="font-medium text-slate-600"><?= e($logRangeText) ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <button type="button" class="btn btn-primary text-sm w-full sm:w-auto" data-open-modal="openingCashModal">+ <?= e(__('cash.btn_set_opening')) ?></button>
            <a href="<?= e($buildLogUrl(['action' => 'export_cash_log_pdf', 'log_page' => null])) ?>" target="_blank" rel="noopener" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('dashboard.btn_export_pdf')) ?></a>
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

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4" aria-label="<?= e(__('dashboard.section_cash_summary')) ?>">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700"><?= e(__('dashboard.kpi_received')) ?></p>
            <p class="text-xl sm:text-2xl font-bold text-emerald-800 mt-2 tabular-nums break-all"><?= e(format_currency($totalInflow)) ?></p>
            <p class="text-xs text-emerald-600/90 mt-2"><?= e(__('dashboard.kpi_received_net_sub')) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('dashboard.kpi_supplier_out')) ?></p>
            <p class="text-xl sm:text-2xl font-bold text-rose-600 mt-2 tabular-nums break-all"><?= e(format_currency($supplierOut)) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('cash.kpi_expenses')) ?></p>
            <p class="text-xl sm:text-2xl font-bold text-amber-700 mt-2 tabular-nums break-all"><?= e(format_currency($expenseOut)) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(__('cash.kpi_salaries')) ?></p>
            <p class="text-xl sm:text-2xl font-bold text-amber-800 mt-2 tabular-nums break-all"><?= e(format_currency($salaryOut)) ?></p>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800 mb-3"><?= e(__('dashboard.add_expense_title')) ?></h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="cash_action" value="add_expense">
                <?php $logHiddenFields(); ?>
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
                <?php $logHiddenFields(); ?>
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

    <section class="app-card overflow-hidden" aria-labelledby="cash-tx-log">
        <div class="px-3 sm:px-6 py-4 border-b border-slate-100">
            <h2 id="cash-tx-log" class="text-lg font-semibold text-slate-800"><?= e(__('dashboard.section_daily_log')) ?></h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= e(__('dashboard.section_daily_log_hint')) ?></p>

            <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mt-4 p-3 rounded-xl bg-slate-50 border border-slate-100">
                <input type="hidden" name="module" value="cash">
                <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_from')) ?></span>
                    <input type="date" name="log_from" value="<?= e($logFrom) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_to')) ?></span>
                    <input type="date" name="log_to" value="<?= e($logTo) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_type')) ?></span>
                    <select name="log_type" class="app-input mt-1">
                        <option value=""><?= e(__('dashboard.filter_all_types')) ?></option>
                        <option value="inflow_order" <?= $logType === 'inflow_order' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_order_payment')) ?></option>
                        <option value="inflow_customer_opening" <?= $logType === 'inflow_customer_opening' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_customer_opening_payment')) ?></option>
                        <option value="customer_opening_recorded" <?= $logType === 'customer_opening_recorded' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_customer_opening_recorded')) ?></option>
                        <option value="inflow_opening" <?= $logType === 'inflow_opening' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_opening')) ?></option>
                        <option value="outflow_supplier_purchase" <?= $logType === 'outflow_supplier_purchase' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_supplier_purchase')) ?></option>
                        <option value="outflow_supplier" <?= $logType === 'outflow_supplier' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_supplier')) ?></option>
                        <option value="outflow_expense" <?= $logType === 'outflow_expense' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_expense')) ?></option>
                        <option value="outflow_salary" <?= $logType === 'outflow_salary' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_salary')) ?></option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_direction')) ?></span>
                    <select name="log_direction" class="app-input mt-1">
                        <option value="all" <?= $logDirection === 'all' ? 'selected' : '' ?>><?= e(__('dashboard.filter_all_directions')) ?></option>
                        <option value="in" <?= $logDirection === 'in' ? 'selected' : '' ?>><?= e(__('dashboard.filter_direction_in')) ?></option>
                        <option value="out" <?= $logDirection === 'out' ? 'selected' : '' ?>><?= e(__('dashboard.filter_direction_out')) ?></option>
                    </select>
                </label>
                <label class="block sm:col-span-2 xl:col-span-1">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_search')) ?></span>
                    <input type="search" name="log_q" value="<?= e($logQ) ?>" placeholder="<?= e(__('dashboard.filter_search_ph')) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_per_page')) ?></span>
                    <select name="log_per_page" class="app-input mt-1">
                        <?php foreach ([10, 25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $logPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2 sm:col-span-2 xl:col-span-2">
                    <button type="submit" class="btn btn-primary flex-1"><?= e(__('dashboard.btn_apply_filters')) ?></button>
                    <a href="<?= e($buildLogUrl(['log_from' => $defaultFrom, 'log_to' => $defaultTo, 'log_direction' => 'all', 'log_type' => null, 'log_q' => null, 'log_page' => null])) ?>" class="btn btn-soft text-center"><?= e(__('dashboard.btn_reset_filters')) ?></a>
                </div>
            </form>

            <?php if ($logDailySummary): ?>
            <div class="flex gap-2 overflow-x-auto pb-2 mt-3 -mx-1 px-1">
                <?php foreach (array_slice($logDailySummary, 0, 14) as $daySum): ?>
                    <div class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs min-w-[110px] sm:min-w-[120px]">
                        <p class="font-semibold text-slate-800 truncate"><?= e((string) $daySum['group_label']) ?></p>
                        <p class="text-emerald-700 tabular-nums">+ <?= e(format_currency((float) $daySum['inflow'])) ?></p>
                        <p class="text-rose-600 tabular-nums">- <?= e(format_currency((float) $daySum['outflow'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="text-xs text-slate-500 mt-3">
                <?= e(__('dashboard.log_results_summary', [
                    'total' => (string) $logPagination['total'],
                    'in' => format_currency($logFilteredIn),
                    'out' => format_currency($logFilteredOut),
                    'net' => format_currency($logFilteredIn - $logFilteredOut),
                ])) ?>
            </p>
        </div>

        <?php if (!$logPageItems): ?>
            <p class="p-6 text-slate-500 text-sm"><?= e(__('dashboard.no_cash_movements')) ?></p>
        <?php else: ?>
            <div class="md:hidden divide-y divide-slate-100 border-t border-slate-100">
                <?php
                $lastLogDayKeyMobile = null;
                foreach ($logPageItems as $tx):
                    $dayKey = financial_bucket_key((string) $tx['date'], 'day');
                    if ($dayKey !== $lastLogDayKeyMobile):
                        $lastLogDayKeyMobile = $dayKey;
                        $dayMeta = $logDayMap[$dayKey] ?? null;
                        ?>
                <div class="px-4 py-2 bg-slate-100/80 text-xs font-semibold text-slate-800">
                    <?= e($dayMeta ? (string) $dayMeta['group_label'] : format_date_pk((string) $tx['date'])) ?>
                    <?php if ($dayMeta): ?>
                    <span class="block font-normal text-slate-600 mt-0.5 tabular-nums">
                        + <?= e(format_currency((float) $dayMeta['inflow'])) ?> · - <?= e(format_currency((float) $dayMeta['outflow'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                        <?php
                    endif;
                    ?>
                <article class="p-4 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?= e($renderTxTypeBadge((string) $tx['type'])) ?>"><?= e((string) $tx['type_label']) ?></span>
                        <span class="text-xs text-slate-500"><?= e(format_date_pk((string) $tx['date'])) ?></span>
                    </div>
                    <p class="text-sm text-slate-800"><?= e((string) $tx['description']) ?></p>
                    <div class="flex flex-wrap gap-3 text-sm tabular-nums">
                        <?php if ((float) $tx['inflow'] > 0): ?>
                            <span class="text-emerald-700 font-medium">+ <?= e(format_currency((float) $tx['inflow'])) ?></span>
                        <?php endif; ?>
                        <?php if ((float) $tx['outflow'] > 0): ?>
                            <span class="text-rose-600 font-medium">- <?= e(format_currency((float) $tx['outflow'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" class="btn btn-soft text-xs py-1 px-2 tx-view-btn"
                            data-tx-type="<?= e((string) $tx['type']) ?>"
                            data-tx-id="<?= (int) $tx['ref_id'] ?>">
                            <?= e(__('dashboard.link_view')) ?>
                        </button>
                        <?php if ($tx['type'] === 'outflow_expense'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('<?= e(__('dashboard.confirm_delete')) ?>');">
                                <input type="hidden" name="cash_action" value="delete_expense">
                                <input type="hidden" name="expense_id" value="<?= (int) $tx['ref_id'] ?>">
                                <?php $logHiddenFields(); ?>
                                <button type="submit" class="text-xs text-rose-600 hover:underline"><?= e(__('common.delete')) ?></button>
                            </form>
                        <?php elseif ($tx['type'] === 'outflow_salary'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('<?= e(__('dashboard.confirm_delete')) ?>');">
                                <input type="hidden" name="cash_action" value="delete_salary">
                                <input type="hidden" name="salary_id" value="<?= (int) $tx['ref_id'] ?>">
                                <?php $logHiddenFields(); ?>
                                <button type="submit" class="text-xs text-rose-600 hover:underline"><?= e(__('common.delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead class="bg-slate-50 border-t border-slate-100">
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                            <th class="p-3"><?= e(__('dashboard.col_date')) ?></th>
                            <th class="p-3"><?= e(__('dashboard.col_type')) ?></th>
                            <th class="p-3"><?= e(__('dashboard.col_description')) ?></th>
                            <th class="p-3 text-end"><?= e(__('dashboard.col_in')) ?></th>
                            <th class="p-3 text-end"><?= e(__('dashboard.col_out')) ?></th>
                            <th class="p-3 text-end"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $lastLogDayKey = null;
                    foreach ($logPageItems as $tx):
                        $dayKey = financial_bucket_key((string) $tx['date'], 'day');
                        if ($dayKey !== $lastLogDayKey):
                            $lastLogDayKey = $dayKey;
                            $dayMeta = $logDayMap[$dayKey] ?? null;
                            ?>
                        <tr class="bg-slate-100/80 border-t border-slate-200">
                            <td colspan="6" class="px-4 py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs sm:text-sm">
                                    <span class="font-semibold text-slate-800"><?= e($dayMeta ? (string) $dayMeta['group_label'] : format_date_pk((string) $tx['date'])) ?></span>
                                    <?php if ($dayMeta): ?>
                                    <span class="tabular-nums text-slate-600">
                                        <span class="text-emerald-700">+ <?= e(format_currency((float) $dayMeta['inflow'])) ?></span>
                                        <span class="mx-2 text-slate-300">|</span>
                                        <span class="text-rose-600">- <?= e(format_currency((float) $dayMeta['outflow'])) ?></span>
                                        <span class="mx-2 text-slate-300">|</span>
                                        <span class="font-medium <?= (float) $dayMeta['net'] >= 0 ? 'text-sky-800' : 'text-rose-800' ?>"><?= e(__('dashboard.net')) ?>: <?= e(format_currency((float) $dayMeta['net'])) ?></span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                            <?php
                        endif;
                        ?>
                        <tr class="border-t border-slate-50 hover:bg-slate-50/80">
                            <td class="p-3 whitespace-nowrap text-slate-600"><?= e(format_date_pk((string) $tx['date'])) ?></td>
                            <td class="p-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?= e($renderTxTypeBadge((string) $tx['type'])) ?>"><?= e((string) $tx['type_label']) ?></span>
                            </td>
                            <td class="p-3 text-slate-800 max-w-xs truncate" title="<?= e((string) $tx['description']) ?>"><?= e((string) $tx['description']) ?></td>
                            <td class="p-3 text-end tabular-nums text-emerald-700 font-medium"><?= (float) $tx['inflow'] > 0 ? e(format_currency((float) $tx['inflow'])) : '—' ?></td>
                            <td class="p-3 text-end tabular-nums text-rose-600 font-medium"><?= (float) $tx['outflow'] > 0 ? e(format_currency((float) $tx['outflow'])) : '—' ?></td>
                            <td class="p-3 text-end whitespace-nowrap">
                                <button type="button" class="btn btn-soft text-xs py-1 px-2 tx-view-btn"
                                    data-tx-type="<?= e((string) $tx['type']) ?>"
                                    data-tx-id="<?= (int) $tx['ref_id'] ?>">
                                    <?= e(__('dashboard.link_view')) ?>
                                </button>
                                <?php if ($tx['type'] === 'outflow_expense'): ?>
                                    <form method="post" class="inline ms-1" onsubmit="return confirm('<?= e(__('dashboard.confirm_delete')) ?>');">
                                        <input type="hidden" name="cash_action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= (int) $tx['ref_id'] ?>">
                                        <?php $logHiddenFields(); ?>
                                        <button type="submit" class="text-xs text-rose-600 hover:underline"><?= e(__('common.delete')) ?></button>
                                    </form>
                                <?php elseif ($tx['type'] === 'outflow_salary'): ?>
                                    <form method="post" class="inline ms-1" onsubmit="return confirm('<?= e(__('dashboard.confirm_delete')) ?>');">
                                        <input type="hidden" name="cash_action" value="delete_salary">
                                        <input type="hidden" name="salary_id" value="<?= (int) $tx['ref_id'] ?>">
                                        <?php $logHiddenFields(); ?>
                                        <button type="submit" class="text-xs text-rose-600 hover:underline"><?= e(__('common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($logPagination['total_pages'] > 1): ?>
            <nav class="px-3 sm:px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row flex-wrap items-center justify-between gap-3" aria-label="<?= e(__('dashboard.pagination_label')) ?>">
                <p class="text-sm text-slate-600 text-center sm:text-start">
                    <?= e(__('dashboard.pagination_page', [
                        'page' => (string) $logPagination['page'],
                        'pages' => (string) $logPagination['total_pages'],
                        'total' => (string) $logPagination['total'],
                    ])) ?>
                </p>
                <div class="flex flex-wrap gap-2 justify-center">
                    <?php if ($logPagination['page'] > 1): ?>
                        <a href="<?= e($buildLogUrl(['log_page' => (string) ($logPagination['page'] - 1)])) ?>" class="btn btn-soft text-sm"><?= e(__('dashboard.pagination_prev')) ?></a>
                    <?php endif; ?>
                    <?php if ($logPagination['page'] < $logPagination['total_pages']): ?>
                        <a href="<?= e($buildLogUrl(['log_page' => (string) ($logPagination['page'] + 1)])) ?>" class="btn btn-soft text-sm"><?= e(__('dashboard.pagination_next')) ?></a>
                    <?php endif; ?>
                </div>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
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
                <?php $logHiddenFields(); ?>
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

    <div id="txDetailModal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-tx-modal-close></div>
        <div class="absolute inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg max-h-[90vh] sm:max-h-[85vh] overflow-hidden pointer-events-auto flex flex-col" role="dialog" aria-modal="true" aria-labelledby="txDetailTitle">
                <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 shrink-0">
                    <h3 id="txDetailTitle" class="text-lg font-semibold text-slate-900"><?= e(__('dashboard.detail_title')) ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-2xl leading-none p-1" data-tx-modal-close aria-label="<?= e(__('common.close')) ?>">&times;</button>
                </div>
                <div id="txDetailBody" class="p-4 sm:p-5 overflow-y-auto text-sm space-y-3 flex-1 min-h-0"></div>
                <div class="px-4 sm:px-5 py-3 border-t border-slate-100 flex justify-end shrink-0">
                    <button type="button" class="btn btn-soft w-full sm:w-auto" data-tx-modal-close><?= e(__('common.close')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('txDetailModal');
    const body = document.getElementById('txDetailBody');
    if (!modal || !body) return;

    const detailBase = <?= json_encode($txDetailBaseUrl, JSON_UNESCAPED_UNICODE) ?>;

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        body.innerHTML = '';
    };

    modal.querySelectorAll('[data-tx-modal-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.tx-view-btn');
        if (!btn) return;

        const type = btn.getAttribute('data-tx-type') || '';
        const id = btn.getAttribute('data-tx-id') || '';
        if (!type || !id) return;

        body.innerHTML = '<p class="text-slate-500"><?= e(__('dashboard.detail_loading')) ?></p>';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');

        try {
            const sep = detailBase.includes('?') ? '&' : '?';
            const res = await fetch(
                detailBase + sep + 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id),
                { headers: { 'Accept': 'application/json' } }
            );
            const data = await res.json();
            if (!data.ok || !data.detail) {
                body.innerHTML = '<p class="text-rose-600">' + escapeHtml(data.message || <?= json_encode(__('dashboard.detail_not_found'), JSON_UNESCAPED_UNICODE) ?>) + '</p>';
                return;
            }
            body.innerHTML = Object.entries(data.detail).map(([label, value]) =>
                '<div class="flex flex-col gap-1 border-b border-slate-100 pb-3 last:border-0">' +
                '<span class="text-slate-500 font-medium text-xs uppercase tracking-wide">' + escapeHtml(label) + '</span>' +
                '<span class="text-slate-900 break-words">' + escapeHtml(value ?? '') + '</span></div>'
            ).join('');
        } catch {
            body.innerHTML = '<p class="text-rose-600"><?= e(__('dashboard.detail_error')) ?></p>';
        }
    });
})();
</script>
<script>
(() => {
    if (!window.OxygenFinance?.onUpdated) return;
    window.OxygenFinance.onUpdated(() => {
        if (document.visibilityState === 'visible') {
            window.location.reload();
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.cash'), $content);
