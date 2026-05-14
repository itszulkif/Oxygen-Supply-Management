<?php

$pdo = db();

$serviceAmountExpr = service_order_amount_expr($pdo);
$serviceOrderSalesFrom = 'FROM services s LEFT JOIN invoices i ON i.service_id = s.id';

if (table_exists($pdo, 'cylinder_stock_by_type')) {
    $inStock = (int) $pdo->query('SELECT COALESCE(SUM(available),0) FROM cylinder_stock_by_type')->fetchColumn();
} else {
    $inStock = (int) $pdo->query('SELECT COALESCE(SUM(available),0) FROM cylinders')->fetchColumn();
}

$monthlyRevenue = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())')->fetchColumn();
$pendingAmount = (float) $pdo->query('SELECT COALESCE(SUM(remaining_amount),0) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();
$dueCustomers = (int) $pdo->query('SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();
$unpaidInvoicesCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();
$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$todaySales = (float) $pdo->query("SELECT COALESCE(SUM({$serviceAmountExpr}),0) {$serviceOrderSalesFrom} WHERE s.date = CURDATE()")->fetchColumn();

$todayImmutable = new DateTimeImmutable('today');
$mondayThisWeek = $todayImmutable->modify('monday this week');
$sundayThisWeek = $mondayThisWeek->modify('+6 days');
$weekRangeStmt = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) {$serviceOrderSalesFrom} WHERE s.date >= ? AND s.date <= ?");
$weekRangeStmt->execute([$mondayThisWeek->format('Y-m-d'), $sundayThisWeek->format('Y-m-d')]);
$weekSalesTotal = (float) $weekRangeStmt->fetchColumn();

$dailyFrom = $todayImmutable->modify('-13 days');
$dailyStmt = $pdo->prepare("SELECT s.date AS order_date, COALESCE(SUM({$serviceAmountExpr}),0) AS total {$serviceOrderSalesFrom} WHERE s.date >= ? AND s.date <= ? GROUP BY s.date");
$dailyStmt->execute([$dailyFrom->format('Y-m-d'), $todayImmutable->format('Y-m-d')]);
$dailyByDate = [];
foreach ($dailyStmt->fetchAll() as $dr) {
    $dailyByDate[(string) $dr['order_date']] = (float) $dr['total'];
}
$salesDailyLabels = [];
$salesDailyValues = [];
for ($i = 0; $i < 14; $i++) {
    $d = $dailyFrom->modify('+' . $i . ' days');
    $key = $d->format('Y-m-d');
    $salesDailyLabels[] = $d->format('j M');
    $salesDailyValues[] = $dailyByDate[$key] ?? 0.0;
}

$salesWeeklyLabels = [];
$salesWeeklyValues = [];
$firstChartMonday = $mondayThisWeek->modify('-7 weeks');
$weekBucketStmt = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) {$serviceOrderSalesFrom} WHERE s.date >= ? AND s.date <= ?");
for ($w = 0; $w < 8; $w++) {
    $mon = $firstChartMonday->modify('+' . $w . ' weeks');
    $sun = $mon->modify('+6 days');
    $weekBucketStmt->execute([$mon->format('Y-m-d'), $sun->format('Y-m-d')]);
    $salesWeeklyValues[] = (float) $weekBucketStmt->fetchColumn();
    $salesWeeklyLabels[] = $mon->format('j M') . ' – ' . $sun->format('j M');
}

$salesMonthlyLabels = [];
$salesMonthlyValues = [];
$monthAnchor = new DateTimeImmutable('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $d = $monthAnchor->modify('-' . $i . ' months');
    $y = (int) $d->format('Y');
    $m = (int) $d->format('n');
    $salesMonthlyLabels[] = i18n_month_short($m);
    $mStmt = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) {$serviceOrderSalesFrom} WHERE YEAR(s.date) = ? AND MONTH(s.date) = ?");
    $mStmt->execute([$y, $m]);
    $salesMonthlyValues[] = (float) $mStmt->fetchColumn();
}

$sixMonthPaymentRows = $pdo->query(
    "SELECT YEAR(payment_date) AS y, MONTH(payment_date) AS m, COALESCE(SUM(amount),0) AS total
     FROM payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY y, m"
)->fetchAll();

$sixMonthBilledRows = $pdo->query(
    "SELECT YEAR(s.date) AS y, MONTH(s.date) AS m,
            COALESCE(SUM({$serviceAmountExpr}),0) AS total
     FROM services s
     LEFT JOIN invoices i ON i.service_id = s.id
     WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY y, m"
)->fetchAll();

$payByKey = [];
foreach ($sixMonthPaymentRows as $r) {
    $payByKey[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['total'];
}
$billedByKey = [];
foreach ($sixMonthBilledRows as $r) {
    $billedByKey[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['total'];
}

$revLabels = [];
$revPayments = [];
$revBilled = [];
$anchor = new DateTimeImmutable('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $d = $anchor->modify('-' . $i . ' months');
    $y = (int) $d->format('Y');
    $m = (int) $d->format('n');
    $k = $y . '-' . $m;
    $revLabels[] = i18n_month_short($m);
    $revPayments[] = $payByKey[$k] ?? 0.0;
    $revBilled[] = $billedByKey[$k] ?? 0.0;
}

$serviceMixRows = $pdo->query(
    'SELECT service_type, COUNT(*) AS count_total
     FROM services
     GROUP BY service_type
     ORDER BY count_total DESC'
)->fetchAll();

$recentTransactions = $pdo->query(
    "SELECT s.id, s.customer_id, s.service_type, s.quantity, s.date, c.name,
            {$serviceAmountExpr} AS order_total,
            COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amount,
            i.id AS invoice_id
     FROM services s
     INNER JOIN customers c ON c.id = s.customer_id
     LEFT JOIN invoices i ON i.service_id = s.id
     ORDER BY s.id DESC
     LIMIT 10"
)->fetchAll();

ob_start();
?>
<div class="max-w-7xl mx-auto space-y-10 pb-2">
    <p class="text-sm text-slate-500 leading-relaxed max-w-3xl"><?= e(__('dashboard.page_intro')) ?></p>

    <section class="space-y-3" aria-labelledby="dash-heading-sales">
        <h2 id="dash-heading-sales" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_sales_today')) ?></h2>
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-sky-50/80 via-white to-white p-5 sm:p-6 shadow-sm border-l-4 border-l-primary flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e(__('dashboard.today_sales')) ?></p>
                <p class="text-sm text-slate-500 mt-1"><?= e(__('dashboard.today_sales_sub')) ?></p>
                <p class="text-3xl sm:text-4xl font-bold text-primary mt-3 tabular-nums tracking-tight"><?= e(format_currency($todaySales)) ?></p>
            </div>
            <div class="rounded-xl bg-white/80 border border-slate-100 px-4 py-3 sm:text-end shrink-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e(__('dashboard.week_sales_total')) ?></p>
                <p class="text-xl font-semibold text-slate-800 tabular-nums mt-1"><?= e(format_currency($weekSalesTotal)) ?></p>
            </div>
        </div>
    </section>

    <section class="space-y-3" aria-labelledby="dash-heading-kpis">
        <h2 id="dash-heading-kpis" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_key_figures')) ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-sm text-slate-500"><?= e(__('dashboard.monthly_revenue')) ?></p>
                <p class="text-2xl sm:text-3xl font-bold text-primary mt-2 tabular-nums"><?= e(format_currency($monthlyRevenue)) ?></p>
                <p class="text-xs text-slate-400 mt-2"><?= e(__('chart.payments_collected')) ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-sm text-slate-500"><?= e(__('dashboard.open_invoices')) ?></p>
                <p class="text-2xl sm:text-3xl font-bold text-slate-800 mt-2 tabular-nums"><?= $unpaidInvoicesCount ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('dashboard.open_invoices_sub', ['n' => (string) $dueCustomers])) ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-sm text-slate-500"><?= e(__('dashboard.total_customers')) ?></p>
                <p class="text-2xl sm:text-3xl font-bold text-oxygenDeep mt-2 tabular-nums"><?= $totalCustomers ?></p>
                <p class="text-xs text-slate-400 mt-2"><?= e(__('nav.customers')) ?></p>
            </div>
        </div>
    </section>

    <section class="space-y-3" aria-labelledby="dash-heading-stock">
        <h2 id="dash-heading-stock" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_stock_receivables')) ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-sm text-slate-500"><?= e(__('dashboard.stock_units')) ?></p>
                <p class="text-2xl sm:text-3xl font-bold text-primary mt-2 tabular-nums"><?= $inStock ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm border-l-4 border-l-warning">
                <p class="text-sm text-slate-500"><?= e(__('dashboard.total_remaining_balance')) ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= e(__('dashboard.total_remaining_balance_sub')) ?></p>
                <p class="text-2xl sm:text-3xl font-bold text-warning mt-3 tabular-nums"><?= e(format_currency($pendingAmount)) ?></p>
                <p class="text-xs text-slate-500 mt-2"><?= e(__('dashboard.customers_count', ['n' => (string) $dueCustomers])) ?></p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm space-y-4" aria-labelledby="dash-heading-orders-chart">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h2 id="dash-heading-orders-chart" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_order_sales')) ?></h2>
                <p class="text-sm text-slate-500 mt-1"><?= e(__('dashboard.sales_trends_hint')) ?></p>
            </div>
            <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 shrink-0" role="group" aria-label="<?= e(__('dashboard.sales_trends_title')) ?>">
                <button type="button" id="salesPeriodDaily" class="sales-period-btn px-3 py-1.5 text-sm rounded-md bg-white shadow-sm font-medium text-slate-800"><?= e(__('dashboard.period_daily')) ?></button>
                <button type="button" id="salesPeriodWeekly" class="sales-period-btn px-3 py-1.5 text-sm rounded-md text-slate-600 hover:text-slate-900"><?= e(__('dashboard.period_weekly')) ?></button>
                <button type="button" id="salesPeriodMonthly" class="sales-period-btn px-3 py-1.5 text-sm rounded-md text-slate-600 hover:text-slate-900"><?= e(__('dashboard.period_monthly')) ?></button>
            </div>
        </div>
        <div class="h-72 min-h-[12rem]"><canvas id="salesTrendChart" aria-label="<?= e(__('dashboard.sales_trends_title')) ?>"></canvas></div>
    </section>

    <section class="space-y-3" aria-labelledby="dash-heading-trends">
        <h2 id="dash-heading-trends" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_trends')) ?></h2>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm xl:col-span-2">
                <h3 class="text-sm font-semibold text-slate-700 mb-3"><?= e(__('dashboard.chart_revenue_title')) ?></h3>
                <div class="h-72 min-h-[12rem]"><canvas id="revChart"></canvas></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3"><?= e(__('dashboard.chart_orders_type')) ?></h3>
                <div class="h-72 min-h-[12rem]"><canvas id="serviceChart"></canvas></div>
            </div>
        </div>
    </section>

    <section class="space-y-3" aria-labelledby="dash-heading-reports">
        <h2 id="dash-heading-reports" class="text-base font-semibold text-slate-800"><?= e(__('dashboard.section_reports')) ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="?module=customer_reports<?= i18n_lang_query() ?>" class="group flex items-stretch justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e(__('nav.customer_reports')) ?></p>
                    <p class="text-sm text-slate-500 mt-2 leading-snug"><?= e(__('dashboard.link_customer_reports_desc')) ?></p>
                </div>
                <span class="flex items-center text-slate-300 group-hover:text-primary transition-colors shrink-0" aria-hidden="true">
                    <svg class="h-6 w-6 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>
            <a href="?module=supplier_reports<?= i18n_lang_query() ?>" class="group flex items-stretch justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e(__('nav.supplier_reports')) ?></p>
                    <p class="text-sm text-slate-500 mt-2 leading-snug"><?= e(__('dashboard.link_supplier_reports_desc')) ?></p>
                </div>
                <span class="flex items-center text-slate-300 group-hover:text-primary transition-colors shrink-0" aria-hidden="true">
                    <svg class="h-6 w-6 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>
        </div>
    </section>

    <section class="app-card overflow-hidden" aria-labelledby="dash-heading-recent">
        <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="dash-heading-recent" class="text-lg font-semibold text-slate-800"><?= e(__('dashboard.section_recent')) ?></h2>
            <div class="flex flex-wrap gap-2">
                <a href="?module=services<?= i18n_lang_query() ?>" class="btn btn-primary text-sm"><?= e(__('dashboard.new_order')) ?></a>
                <a href="?module=customers<?= i18n_lang_query() ?>" class="btn btn-soft text-sm"><?= e(__('dashboard.new_customer')) ?></a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table data-sortable="true" class="w-full text-sm min-w-[880px]">
                <thead class="bg-slate-50"><tr><th data-sort class="text-left p-3"><?= e(__('dashboard.col_customer')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_service')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_qty')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_amount')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_pay_status')) ?></th><th class="text-left p-3 whitespace-nowrap"><?= e(__('common.actions')) ?></th></tr></thead>
                <tbody>
                <?php if (!$recentTransactions): ?>
                    <tr><td colspan="6" class="p-4 text-slate-500"><?= e(__('dashboard.no_transactions')) ?></td></tr>
                <?php else: foreach ($recentTransactions as $tx): $total = (float) ($tx['order_total'] ?? 0); $paid = (float) ($tx['paid_amount'] ?? 0); $status = payment_status_from_amounts($total, $paid);
                    $cid = (int) ($tx['customer_id'] ?? 0);
                    $ledgerViewUrl = '?module=ledger&entity=customer&view_customer=' . $cid . i18n_lang_query();
                    $invId = (int) ($tx['invoice_id'] ?? 0);
                    $ledgerPayParams = 'focus=payment' . ($invId > 0 ? '&pay_invoice=' . $invId : '');
                    $ledgerPayUrl = '?module=ledger&entity=customer&view_customer=' . $cid . '&' . $ledgerPayParams . i18n_lang_query();
                    ?>
                    <tr data-row="true" class="border-t border-slate-100">
                        <td class="p-3"><?= e((string) $tx['name']) ?></td><td class="p-3"><?= e(service_type_label((string) $tx['service_type'])) ?></td><td data-value="<?= (int) $tx['quantity'] ?>" class="p-3"><?= (int) $tx['quantity'] ?></td><td data-value="<?= $total ?>" class="p-3"><?= e(format_currency($total)) ?></td><td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span></td>
                        <td class="p-3 whitespace-nowrap">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a href="<?= e($ledgerViewUrl) ?>" class="inline-flex rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-200"><?= e(__('dashboard.link_view')) ?></a>
                                <a href="<?= e($ledgerPayUrl) ?>" class="inline-flex rounded-md bg-sky-100 px-2 py-1 text-xs font-medium text-sky-900 hover:bg-sky-200"><?= e(__('dashboard.link_payment')) ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('revChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($revLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { label: <?= json_encode(__('chart.payments_collected'), JSON_UNESCAPED_UNICODE) ?>, data: <?= json_encode($revPayments, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#1D9E75' },
            { label: <?= json_encode(__('chart.orders_billed'), JSON_UNESCAPED_UNICODE) ?>, data: <?= json_encode($revBilled, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#94A3B8' },
        ],
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: false }, y: { beginAtZero: true } } },
});
new Chart(document.getElementById('serviceChart'), { type: 'doughnut', data: { labels: <?= json_encode(array_map(static fn($r) => service_type_label((string) $r['service_type']), $serviceMixRows), JSON_UNESCAPED_UNICODE) ?>, datasets: [{ data: <?= json_encode(array_map(static fn($r) => (int) $r['count_total'], $serviceMixRows), JSON_UNESCAPED_UNICODE) ?>, backgroundColor: ['#1D9E75','#185FA5','#BA7517','#A32D2D'] }] }, options: { responsive: true, maintainAspectRatio: false } });

const salesTrendSeries = {
    daily: { labels: <?= json_encode($salesDailyLabels, JSON_UNESCAPED_UNICODE) ?>, values: <?= json_encode($salesDailyValues, JSON_UNESCAPED_UNICODE) ?> },
    weekly: { labels: <?= json_encode($salesWeeklyLabels, JSON_UNESCAPED_UNICODE) ?>, values: <?= json_encode($salesWeeklyValues, JSON_UNESCAPED_UNICODE) ?> },
    monthly: { labels: <?= json_encode($salesMonthlyLabels, JSON_UNESCAPED_UNICODE) ?>, values: <?= json_encode($salesMonthlyValues, JSON_UNESCAPED_UNICODE) ?> },
};
const currencySym = <?= json_encode(__('currency.symbol'), JSON_UNESCAPED_UNICODE) ?>;
function formatChartMoney(value) {
    const n = Number(value) || 0;
    return (currencySym ? currencySym + ' ' : '') + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
const salesTrendChart = new Chart(document.getElementById('salesTrendChart'), {
    type: 'bar',
    data: {
        labels: salesTrendSeries.daily.labels,
        datasets: [{
            label: <?= json_encode(__('chart.orders_billed'), JSON_UNESCAPED_UNICODE) ?>,
            data: salesTrendSeries.daily.values,
            backgroundColor: '#185FA5',
            borderRadius: 4,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { x: { ticks: { maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true } },
        plugins: {
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const v = ctx.parsed.y;
                        return ctx.dataset.label + ': ' + formatChartMoney(v);
                    },
                },
            },
        },
    },
});
const salesPeriodBtns = {
    daily: document.getElementById('salesPeriodDaily'),
    weekly: document.getElementById('salesPeriodWeekly'),
    monthly: document.getElementById('salesPeriodMonthly'),
};
function setActiveSalesPeriod(period) {
    const series = salesTrendSeries[period];
    if (!series) {
        return;
    }
    salesTrendChart.data.labels = series.labels;
    salesTrendChart.data.datasets[0].data = series.values;
    salesTrendChart.update();
    Object.keys(salesPeriodBtns).forEach((key) => {
        const btn = salesPeriodBtns[key];
        if (!btn) {
            return;
        }
        if (key === period) {
            btn.classList.add('bg-white', 'shadow-sm', 'font-medium', 'text-slate-800');
            btn.classList.remove('text-slate-600');
        } else {
            btn.classList.remove('bg-white', 'shadow-sm', 'font-medium', 'text-slate-800');
            btn.classList.add('text-slate-600');
        }
    });
}
salesPeriodBtns.daily?.addEventListener('click', () => setActiveSalesPeriod('daily'));
salesPeriodBtns.weekly?.addEventListener('click', () => setActiveSalesPeriod('weekly'));
salesPeriodBtns.monthly?.addEventListener('click', () => setActiveSalesPeriod('monthly'));
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.dashboard'), $content);
