<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\OxygenOpsService;

$pdo = db();
$ops = new OxygenOpsService();

$period = strtolower(trim((string) ($_GET['period'] ?? 'weekly')));
if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
    $period = 'weekly';
}
$search = trim((string) ($_GET['search'] ?? ''));
$balanceFilter = strtolower(trim((string) ($_GET['balance'] ?? 'all')));
if (!in_array($balanceFilter, ['all', 'outstanding'], true)) {
    $balanceFilter = 'all';
}
$sortBy = strtolower(trim((string) ($_GET['sort'] ?? 'date')));
if (!in_array($sortBy, ['date', 'outstanding'], true)) {
    $sortBy = 'date';
}
$remainingBalanceExpr = 'COALESCE(s.remaining_balance, i.remaining_amount, 0)';
$today = new DateTimeImmutable('today');
$toDate = $today->format('Y-m-d');
$fromDate = match ($period) {
    'daily' => $toDate,
    'monthly' => $today->modify('-29 days')->format('Y-m-d'),
    default => $today->modify('-6 days')->format('Y-m-d'),
};

if (($_GET['ajax'] ?? '') === 'search_preview') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode(['ok' => true, 'orders' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $previewSql = "SELECT s.id, s.date, c.name AS customer_name, c.phone,
            {$remainingBalanceExpr} AS remaining_balance
        FROM services s
        INNER JOIN customers c ON c.id = s.customer_id
        LEFT JOIN invoices i ON i.service_id = s.id
        WHERE s.date >= ? AND s.date <= ?
          AND (c.name LIKE ? OR c.phone LIKE ?)";
    $previewParams = [$fromDate, $toDate, '%' . $q . '%', '%' . $q . '%'];
    if ($balanceFilter === 'outstanding') {
        $previewSql .= " AND {$remainingBalanceExpr} > 0.00001";
    }
    $previewSql .= " GROUP BY s.id, s.date, c.name, c.phone, s.remaining_balance, i.remaining_amount
        ORDER BY s.date DESC, s.id DESC
        LIMIT 12";
    $previewSt = $pdo->prepare($previewSql);
    $previewSt->execute($previewParams);
    $previewRows = [];
    foreach ($previewSt->fetchAll() as $row) {
        $previewRows[] = [
            'id' => (int) $row['id'],
            'customer_name' => (string) $row['customer_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'date_label' => format_date_pk((string) $row['date']),
            'remaining_label' => format_currency((float) ($row['remaining_balance'] ?? 0)),
            'search_term' => (string) $row['customer_name'],
        ];
    }
    echo json_encode(['ok' => true, 'orders' => $previewRows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) request_value('action', ''));
    if ($action === 'record_order_payment') {
        $invoiceId = (int) request_value('invoice_id', '0');
        $amount = (float) request_value('amount', '0');
        $paymentDate = request_value('payment_date', date('Y-m-d'));
        $redirectPeriod = request_value('period', $period);
        $redirectSearch = trim((string) request_value('search', ''));
        $redirectBalance = request_value('balance', $balanceFilter);
        $redirectSort = request_value('sort', $sortBy);
        $toastMsg = __('orders.toast_payment_failed');
        try {
            if ($invoiceId <= 0 || $amount <= 0) {
                throw new RuntimeException(__('orders.err_payment_invalid'));
            }
            $ops->addPaymentWithAutomation([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
            ]);
            $toastMsg = __('orders.toast_payment_saved');
        } catch (Throwable $e) {
            $toastMsg = $e->getMessage();
        }
        $qs = http_build_query(array_filter([
            'module' => 'orders',
            'period' => $redirectPeriod,
            'search' => $redirectSearch !== '' ? $redirectSearch : null,
            'balance' => $redirectBalance === 'outstanding' ? 'outstanding' : null,
            'sort' => in_array($redirectSort, ['date', 'outstanding'], true) && $redirectSort !== 'date' ? $redirectSort : null,
            'toast' => $toastMsg,
            'lang' => i18n_locale() === 'ps' ? 'ps' : null,
        ]));
        header('Location: ?' . $qs);
        exit;
    }
}

$sql = "SELECT s.id, s.date, s.service_type, s.customer_id, c.name AS customer_name, c.phone,
        COALESCE(s.grand_total, i.total_amount, (s.quantity * s.price), 0) AS total_amount,
        COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amount,
        {$remainingBalanceExpr} AS remaining_balance,
        i.id AS invoice_id,
        COALESCE(SUM(r.sent_qty), 0) AS cylinders_provided,
        COALESCE(SUM(r.received_qty), 0) AS cylinders_returned,
        COALESCE(SUM(r.baqi_qty), 0) AS cylinders_baqi
    FROM services s
    INNER JOIN customers c ON c.id = s.customer_id
    LEFT JOIN invoices i ON i.service_id = s.id
    LEFT JOIN service_cylinder_rows r ON r.service_id = s.id
    WHERE s.date >= ? AND s.date <= ?";
$params = [$fromDate, $toDate];
if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' GROUP BY s.id, s.date, s.service_type, s.customer_id, c.name, c.phone,
        s.grand_total, i.total_amount, s.quantity, s.price, s.paid_amount, i.paid_amount,
        s.remaining_balance, i.remaining_amount, i.id';
if ($balanceFilter === 'outstanding') {
    $sql .= " HAVING {$remainingBalanceExpr} > 0.00001";
}
$sql .= $sortBy === 'outstanding'
    ? " ORDER BY remaining_balance DESC, s.date DESC, s.id DESC"
    : ' ORDER BY s.date DESC, s.id DESC';
$sql .= ' LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$summary = [
    'count' => count($orders),
    'billed' => 0.0,
    'paid' => 0.0,
    'outstanding' => 0.0,
];
foreach ($orders as $row) {
    $summary['billed'] += (float) ($row['total_amount'] ?? 0);
    $summary['paid'] += (float) ($row['paid_amount'] ?? 0);
    $summary['outstanding'] += (float) ($row['remaining_balance'] ?? 0);
}

$periodLabel = match ($period) {
    'daily' => __('orders.period_daily'),
    'monthly' => __('orders.period_monthly'),
    default => __('orders.period_weekly'),
};
$dateRangeLabel = format_date_pk($fromDate) . ($fromDate !== $toDate ? ' – ' . format_date_pk($toDate) : '');

$buildOrdersUrl = static function (array $overrides = []) use ($period, $search, $balanceFilter, $sortBy): string {
    $q = ['module' => 'orders', 'period' => $period];
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($balanceFilter === 'outstanding') {
        $q['balance'] = 'outstanding';
    }
    if ($sortBy === 'outstanding') {
        $q['sort'] = 'outstanding';
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    if (($q['balance'] ?? '') === 'all') {
        unset($q['balance']);
    }
    if (($q['sort'] ?? '') === 'date') {
        unset($q['sort']);
    }
    if (i18n_locale() === 'ps') {
        $q['lang'] = 'ps';
    }
    return '?' . http_build_query($q);
};

ob_start();
?>
<style>
    .orders-period-active { background-color: #0284C7; color: #fff; border-color: #0284C7; }
    .orders-balance-active { background-color: #b45309; color: #fff; border-color: #b45309; }
    @media (max-width: 767px) {
        .orders-table-wrap { display: none; }
        .orders-cards { display: block; }
    }
    @media (min-width: 768px) {
        .orders-table-wrap { display: block; }
        .orders-cards { display: none; }
    }
</style>

<section class="app-card p-4 mb-4 fade-in">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-oxygenDeep"><?= e(__('orders.title')) ?></h2>
            <p class="text-sm text-slate-500 mt-1"><?= e(__('orders.subtitle')) ?></p>
        </div>
        <a href="?module=services<?= i18n_lang_query() ?>" class="btn btn-primary text-sm w-full sm:w-auto text-center shrink-0"><?= e(__('orders.new_order')) ?></a>
    </div>

    <form method="get" class="mt-4 space-y-3">
        <input type="hidden" name="module" value="orders">
        <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
        <input type="hidden" name="period" value="<?= e($period) ?>">
        <div class="flex flex-wrap gap-2" role="group" aria-label="<?= e(__('orders.filter_period')) ?>">
            <?php foreach (['daily', 'weekly', 'monthly'] as $p): ?>
                <a href="<?= e($buildOrdersUrl(['period' => $p])) ?>"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium transition <?= $period === $p ? 'orders-period-active' : 'bg-white text-slate-700 hover:bg-slate-50' ?>">
                    <?= e(match ($p) { 'daily' => __('orders.period_daily'), 'monthly' => __('orders.period_monthly'), default => __('orders.period_weekly') }) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2" role="group" aria-label="<?= e(__('orders.filter_balance')) ?>">
            <a href="<?= e($buildOrdersUrl(['balance' => 'all'])) ?>"
               class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium transition <?= $balanceFilter === 'all' ? 'orders-period-active' : 'bg-white text-slate-700 hover:bg-slate-50' ?>">
                <?= e(__('orders.balance_all')) ?>
            </a>
            <a href="<?= e($buildOrdersUrl(['balance' => 'outstanding', 'sort' => $sortBy === 'date' ? 'outstanding' : $sortBy])) ?>"
               class="inline-flex items-center justify-center rounded-full border border-amber-200 px-4 py-2 text-sm font-medium transition <?= $balanceFilter === 'outstanding' ? 'orders-balance-active' : 'bg-amber-50 text-amber-900 hover:bg-amber-100' ?>">
                <?= e(__('orders.balance_outstanding')) ?>
            </a>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1 min-w-0">
                <input type="search" id="ordersSearchField" name="search" value="<?= e($search) ?>" placeholder="<?= e(__('orders.search_ph')) ?>"
                       autocomplete="off" class="app-input w-full">
                <div id="ordersSearchPreview" class="hidden absolute z-30 left-0 right-0 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg text-sm" role="listbox" aria-label="<?= e(__('orders.search_preview')) ?>"></div>
            </div>
            <select name="sort" class="app-input w-full sm:w-auto sm:min-w-[200px]">
                <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>><?= e(__('orders.sort_date')) ?></option>
                <option value="outstanding" <?= $sortBy === 'outstanding' ? 'selected' : '' ?>><?= e(__('orders.sort_outstanding')) ?></option>
            </select>
            <?php if ($balanceFilter === 'outstanding'): ?>
                <input type="hidden" name="balance" value="outstanding">
            <?php endif; ?>
            <button type="submit" class="btn btn-soft w-full sm:w-auto"><?= e(__('orders.apply_search')) ?></button>
        </div>
        <p class="text-xs text-slate-500">
            <?= e($periodLabel) ?> · <?= e($dateRangeLabel) ?>
            <?php if ($balanceFilter === 'outstanding'): ?>
                · <span class="font-medium text-amber-800"><?= e(__('orders.balance_outstanding_active')) ?></span>
            <?php endif; ?>
            <?php if ($sortBy === 'outstanding'): ?>
                · <?= e(__('orders.sort_outstanding_active')) ?>
            <?php endif; ?>
        </p>
    </form>
</section>

<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
    <div class="app-card p-3 text-center sm:text-start">
        <p class="text-[11px] uppercase tracking-wide text-slate-500"><?= e(__('orders.kpi_orders')) ?></p>
        <p class="text-xl font-bold text-oxygenDeep"><?= (int) $summary['count'] ?></p>
    </div>
    <div class="app-card p-3 text-center sm:text-start">
        <p class="text-[11px] uppercase tracking-wide text-slate-500"><?= e(__('orders.kpi_outstanding')) ?></p>
        <p class="text-xl font-bold text-amber-700"><?= e(format_currency((float) $summary['outstanding'])) ?></p>
    </div>
    <div class="app-card p-3 text-center sm:text-start">
        <p class="text-[11px] uppercase tracking-wide text-slate-500"><?= e(__('orders.kpi_billed')) ?></p>
        <p class="text-xl font-bold text-slate-800"><?= e(format_currency((float) $summary['billed'])) ?></p>
    </div>
    <div class="app-card p-3 text-center sm:text-start">
        <p class="text-[11px] uppercase tracking-wide text-slate-500"><?= e(__('orders.kpi_paid')) ?></p>
        <p class="text-xl font-bold text-emerald-700"><?= e(format_currency((float) $summary['paid'])) ?></p>
    </div>
</section>

<section class="app-card overflow-hidden mb-6">
    <?php if (!$orders): ?>
        <p class="p-6 text-center text-slate-500 text-sm"><?= e(__('orders.no_orders')) ?></p>
    <?php else: ?>

    <p id="ordersNoLocalMatch" class="hidden p-6 text-center text-slate-500 text-sm border-b border-slate-100"><?= e(__('orders.js_no_local_match')) ?></p>

    <div class="orders-cards divide-y divide-slate-100">
        <?php foreach ($orders as $row):
            $total = (float) ($row['total_amount'] ?? 0);
            $paid = (float) ($row['paid_amount'] ?? 0);
            $remaining = (float) ($row['remaining_balance'] ?? 0);
            $status = payment_status_from_amounts($total, $paid);
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            $provided = (int) ($row['cylinders_provided'] ?? 0);
            $returned = (int) ($row['cylinders_returned'] ?? 0);
            $baqi = (int) ($row['cylinders_baqi'] ?? 0);
            $canPay = $invoiceId > 0 && $remaining > 0.00001;
            $orderSearchText = mb_strtolower(trim((string) $row['customer_name'] . ' ' . (string) ($row['phone'] ?? '')));
            ?>
        <article class="order-list-item p-4 space-y-3" data-order-search="<?= e($orderSearchText) ?>">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-slate-900"><?= e((string) $row['customer_name']) ?></p>
                    <p class="text-xs text-slate-500">ORD-<?= (int) $row['id'] ?> · <?= e(format_date_pk((string) $row['date'])) ?></p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs shrink-0 <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-lg bg-slate-50 p-2">
                    <p class="text-slate-500"><?= e(__('orders.col_provided')) ?></p>
                    <p class="text-lg font-semibold text-slate-900"><?= $provided ?></p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-2">
                    <p class="text-emerald-800"><?= e(__('orders.col_returned')) ?></p>
                    <p class="text-lg font-semibold text-emerald-900"><?= $returned ?></p>
                </div>
                <div class="rounded-lg bg-sky-50 p-2">
                    <p class="text-sky-800"><?= e(__('orders.col_baqi')) ?></p>
                    <p class="text-lg font-semibold text-sky-900"><?= $baqi ?></p>
                </div>
            </div>
            <p class="text-xs text-slate-600"><?= e(__('orders.cylinder_hint')) ?></p>
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="text-slate-600"><?= e(__('orders.outstanding')) ?>:</span>
                <span class="font-semibold <?= $remaining > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= e(format_currency($remaining)) ?></span>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($canPay): ?>
                    <button type="button" class="btn btn-primary text-sm flex-1 min-w-[120px] order-pay-btn"
                        data-invoice-id="<?= $invoiceId ?>"
                        data-order-id="<?= (int) $row['id'] ?>"
                        data-customer="<?= e((string) $row['customer_name']) ?>"
                        data-remaining="<?= e(number_format($remaining, 2, '.', '')) ?>"><?= e(__('orders.action_pay')) ?></button>
                <?php else: ?>
                    <span class="inline-flex flex-1 items-center justify-center rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-500"><?= e($remaining <= 0 ? __('orders.paid_in_full') : __('orders.no_invoice')) ?></span>
                <?php endif; ?>
                <a href="?module=services&print_order=<?= (int) $row['id'] ?><?= i18n_lang_query() ?>" class="btn btn-soft text-sm"><?= e(__('common.print')) ?></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <div class="orders-table-wrap overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[960px]">
            <thead class="bg-slate-50">
                <tr>
                    <th data-sort class="text-start p-3"><?= e(__('orders.col_order')) ?></th>
                    <th data-sort class="text-start p-3"><?= e(__('orders.col_date')) ?></th>
                    <th data-sort class="text-start p-3 min-w-[140px]"><?= e(__('orders.col_customer')) ?></th>
                    <th data-sort class="text-center p-3"><?= e(__('orders.col_provided')) ?></th>
                    <th data-sort class="text-center p-3"><?= e(__('orders.col_returned')) ?></th>
                    <th data-sort class="text-center p-3"><?= e(__('orders.col_baqi')) ?></th>
                    <th data-sort class="text-end p-3"><?= e(__('orders.col_total')) ?></th>
                    <th data-sort class="text-end p-3"><?= e(__('orders.col_paid')) ?></th>
                    <th data-sort class="text-end p-3"><?= e(__('orders.col_outstanding')) ?></th>
                    <th class="text-start p-3"><?= e(__('orders.col_status')) ?></th>
                    <th class="text-start p-3 whitespace-nowrap"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $row):
                $total = (float) ($row['total_amount'] ?? 0);
                $paid = (float) ($row['paid_amount'] ?? 0);
                $remaining = (float) ($row['remaining_balance'] ?? 0);
                $status = payment_status_from_amounts($total, $paid);
                $invoiceId = (int) ($row['invoice_id'] ?? 0);
                $canPay = $invoiceId > 0 && $remaining > 0.00001;
                $orderSearchText = mb_strtolower(trim((string) $row['customer_name'] . ' ' . (string) ($row['phone'] ?? '')));
                ?>
                <tr data-row="true" class="order-list-item border-t border-slate-100" data-order-search="<?= e($orderSearchText) ?>">
                    <td class="p-3 font-medium">ORD-<?= (int) $row['id'] ?></td>
                    <td class="p-3 whitespace-nowrap"><?= e(format_date_pk((string) $row['date'])) ?></td>
                    <td class="p-3">
                        <span class="font-medium text-slate-900"><?= e((string) $row['customer_name']) ?></span>
                        <?php if (!empty($row['phone'])): ?>
                            <span class="block text-xs text-slate-500"><?= e((string) $row['phone']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-value="<?= (int) ($row['cylinders_provided'] ?? 0) ?>" class="p-3 text-center font-medium"><?= (int) ($row['cylinders_provided'] ?? 0) ?></td>
                    <td data-value="<?= (int) ($row['cylinders_returned'] ?? 0) ?>" class="p-3 text-center font-medium text-emerald-700"><?= (int) ($row['cylinders_returned'] ?? 0) ?></td>
                    <td data-value="<?= (int) ($row['cylinders_baqi'] ?? 0) ?>" class="p-3 text-center font-medium text-sky-800"><?= (int) ($row['cylinders_baqi'] ?? 0) ?></td>
                    <td data-value="<?= $total ?>" class="p-3 text-end tabular-nums"><?= e(format_currency($total)) ?></td>
                    <td data-value="<?= $paid ?>" class="p-3 text-end tabular-nums"><?= e(format_currency($paid)) ?></td>
                    <td data-value="<?= $remaining ?>" class="p-3 text-end tabular-nums font-semibold <?= $remaining > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= e(format_currency($remaining)) ?></td>
                    <td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span></td>
                    <td class="p-3 whitespace-nowrap">
                        <div class="flex flex-wrap gap-1.5">
                            <?php if ($canPay): ?>
                                <button type="button" class="btn btn-primary text-xs order-pay-btn"
                                    data-invoice-id="<?= $invoiceId ?>"
                                    data-order-id="<?= (int) $row['id'] ?>"
                                    data-customer="<?= e((string) $row['customer_name']) ?>"
                                    data-remaining="<?= e(number_format($remaining, 2, '.', '')) ?>"><?= e(__('orders.action_pay')) ?></button>
                            <?php else: ?>
                                <span class="text-xs text-slate-400"><?= e($remaining <= 0 ? __('orders.paid_in_full') : __('orders.no_invoice')) ?></span>
                            <?php endif; ?>
                            <a href="?module=services&print_order=<?= (int) $row['id'] ?><?= i18n_lang_query() ?>" class="btn btn-soft text-xs"><?= e(__('common.print')) ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</section>

<div id="orderPayModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-0 sm:p-4" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/50" data-close-pay-modal></div>
    <div class="relative w-full sm:max-w-md app-card rounded-t-2xl sm:rounded-xl p-4 sm:p-5 max-h-[90vh] overflow-y-auto shadow-xl">
        <h3 class="text-lg font-semibold text-oxygenDeep mb-1"><?= e(__('orders.modal_pay_title')) ?></h3>
        <p class="text-sm text-slate-500 mb-4" id="orderPayModalSubtitle"></p>
        <form method="post" id="orderPayForm" class="space-y-3">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="record_order_payment">
            <input type="hidden" name="period" value="<?= e($period) ?>">
            <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>
            <?php if ($balanceFilter === 'outstanding'): ?><input type="hidden" name="balance" value="outstanding"><?php endif; ?>
            <?php if ($sortBy === 'outstanding'): ?><input type="hidden" name="sort" value="outstanding"><?php endif; ?>
            <input type="hidden" name="invoice_id" id="payInvoiceId" value="">
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('orders.pay_amount')) ?></span>
                <input type="number" name="amount" id="payAmount" step="0.01" min="0.01" required class="app-input w-full">
            </label>
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('orders.pay_date')) ?></span>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="app-input w-full">
            </label>
            <p class="text-xs text-slate-500"><?= e(__('orders.pay_hint')) ?></p>
            <div class="flex flex-col-reverse sm:flex-row gap-2 pt-2">
                <button type="button" class="btn btn-soft w-full" data-close-pay-modal><?= e(__('common.close')) ?></button>
                <button type="submit" class="btn btn-primary w-full"><?= e(__('orders.pay_submit')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const ordersSearchConfig = <?= json_encode([
        'period' => $period,
        'balance' => $balanceFilter,
        'sort' => $sortBy,
        'lang' => i18n_locale() === 'ps' ? 'ps' : null,
        'noResults' => __('orders.js_no_results'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    const searchField = document.getElementById('ordersSearchField');
    const searchPreview = document.getElementById('ordersSearchPreview');
    const noLocalMatch = document.getElementById('ordersNoLocalMatch');
    let searchPreviewTimer = null;

    const buildOrdersSearchUrl = (searchTerm) => {
        const q = new URLSearchParams();
        q.set('module', 'orders');
        q.set('period', ordersSearchConfig.period || 'weekly');
        const term = (searchTerm || '').trim();
        if (term !== '') {
            q.set('search', term);
        }
        if (ordersSearchConfig.balance === 'outstanding') {
            q.set('balance', 'outstanding');
        }
        if (ordersSearchConfig.sort === 'outstanding') {
            q.set('sort', 'outstanding');
        }
        if (ordersSearchConfig.lang === 'ps') {
            q.set('lang', 'ps');
        }
        return '?' + q.toString();
    };

    const hideSearchPreview = () => {
        if (!(searchPreview instanceof HTMLElement)) {
            return;
        }
        searchPreview.classList.add('hidden');
        searchPreview.replaceChildren();
    };

    const filterOrdersOnPage = (query) => {
        const norm = query.trim().toLowerCase();
        const items = document.querySelectorAll('.order-list-item');
        let visible = 0;
        items.forEach((el) => {
            const haystack = (el instanceof HTMLElement ? el.dataset.orderSearch : '') || '';
            const show = norm === '' || haystack.includes(norm);
            el.classList.toggle('hidden', !show);
            if (show) {
                visible += 1;
            }
        });
        if (noLocalMatch instanceof HTMLElement) {
            noLocalMatch.classList.toggle('hidden', norm === '' || visible > 0 || items.length === 0);
        }
    };

    const runSearchPreview = async () => {
        if (!(searchField instanceof HTMLInputElement) || !(searchPreview instanceof HTMLElement)) {
            return;
        }
        const q = searchField.value.trim();
        filterOrdersOnPage(q);
        if (q.length < 1) {
            hideSearchPreview();
            return;
        }
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'orders');
            u.searchParams.set('ajax', 'search_preview');
            u.searchParams.set('q', q);
            u.searchParams.set('period', ordersSearchConfig.period || 'weekly');
            if (ordersSearchConfig.balance === 'outstanding') {
                u.searchParams.set('balance', 'outstanding');
            }
            if (ordersSearchConfig.sort === 'outstanding') {
                u.searchParams.set('sort', 'outstanding');
            }
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const list = Array.isArray(data.orders) ? data.orders : [];
            searchPreview.replaceChildren();
            if (!list.length) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-slate-500';
                empty.textContent = ordersSearchConfig.noResults;
                searchPreview.appendChild(empty);
            } else {
                list.forEach((order) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-3 py-2.5 hover:bg-slate-50 border-b border-slate-100 last:border-0';
                    btn.setAttribute('role', 'option');
                    const title = document.createElement('p');
                    title.className = 'font-medium text-slate-900';
                    title.textContent = order.customer_name || '';
                    const meta = document.createElement('p');
                    meta.className = 'text-xs text-slate-500 mt-0.5';
                    const phone = order.phone ? ` · ${order.phone}` : '';
                    meta.textContent = `ORD-${order.id} · ${order.date_label || ''}${phone} · ${order.remaining_label || ''}`;
                    btn.appendChild(title);
                    btn.appendChild(meta);
                    btn.addEventListener('mousedown', (e) => e.preventDefault());
                    btn.addEventListener('click', () => {
                        window.location.href = buildOrdersSearchUrl(order.search_term || order.customer_name || q);
                    });
                    searchPreview.appendChild(btn);
                });
            }
            searchPreview.classList.remove('hidden');
        } catch (_) {
            hideSearchPreview();
        }
    };

    if (searchField instanceof HTMLInputElement) {
        filterOrdersOnPage(searchField.value);
        searchField.addEventListener('input', () => {
            clearTimeout(searchPreviewTimer);
            searchPreviewTimer = window.setTimeout(runSearchPreview, 200);
        });
        searchField.addEventListener('focus', () => {
            if (searchField.value.trim().length >= 1) {
                runSearchPreview();
            }
        });
        searchField.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideSearchPreview();
            }
        });
        document.addEventListener('click', (e) => {
            if (
                e.target instanceof Node &&
                !searchField.contains(e.target) &&
                !(searchPreview instanceof HTMLElement && searchPreview.contains(e.target))
            ) {
                hideSearchPreview();
            }
        });
    }

    const modal = document.getElementById('orderPayModal');
    const subtitle = document.getElementById('orderPayModalSubtitle');
    const invoiceField = document.getElementById('payInvoiceId');
    const amountField = document.getElementById('payAmount');
    const tpl = <?= json_encode(__('orders.modal_pay_subtitle'), JSON_UNESCAPED_UNICODE) ?>;

    const openModal = (btn) => {
        if (!(btn instanceof HTMLElement)) return;
        const inv = btn.dataset.invoiceId || '';
        const ord = btn.dataset.orderId || '';
        const customer = btn.dataset.customer || '';
        const remaining = btn.dataset.remaining || '0';
        if (invoiceField) invoiceField.value = inv;
        if (amountField) amountField.value = remaining;
        if (subtitle) {
            subtitle.textContent = tpl
                .replace(/\{order\}/g, ord ? `ORD-${ord}` : '')
                .replace(/\{customer\}/g, customer)
                .replace(/\{remaining\}/g, remaining);
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        amountField?.focus();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    };

    document.querySelectorAll('.order-pay-btn').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn));
    });
    document.querySelectorAll('[data-close-pay-modal]').forEach((el) => {
        el.addEventListener('click', closeModal);
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
render_layout(__('meta.orders'), $content);
