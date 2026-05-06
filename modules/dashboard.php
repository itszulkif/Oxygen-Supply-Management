<?php

$pdo = db();

$hasGrand = column_exists($pdo, 'services', 'grand_total');
$hasTotalBill = column_exists($pdo, 'services', 'total_bill');
$hasInvSvc = column_exists($pdo, 'invoices', 'service_id');
$parts = [];
if ($hasGrand) {
    $parts[] = 's.grand_total';
}
if ($hasTotalBill) {
    $parts[] = 's.total_bill';
}
if ($hasInvSvc) {
    $parts[] = 'i.total_amount';
}
$parts[] = '(s.quantity * s.price)';
$serviceAmountExpr = 'COALESCE(' . implode(', ', $parts) . ')';

$refillSent = (int) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM services WHERE service_type = 'refill'")->fetchColumn();
$otherOut = (int) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM services WHERE service_type IN ('rental','delivery','wholesale')")->fetchColumn();

if (table_exists($pdo, 'cylinder_stock_by_type')) {
    $inStock = (int) $pdo->query('SELECT COALESCE(SUM(available),0) FROM cylinder_stock_by_type')->fetchColumn();
} else {
    $inStock = (int) $pdo->query('SELECT COALESCE(SUM(available),0) FROM cylinders')->fetchColumn();
}

$monthlyRevenue = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())')->fetchColumn();
$pendingAmount = (float) $pdo->query('SELECT COALESCE(SUM(remaining_amount),0) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();
$dueCustomers = (int) $pdo->query('SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();

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
    "SELECT s.id, s.service_type, s.quantity, s.date, c.name,
            {$serviceAmountExpr} AS order_total,
            COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amount
     FROM services s
     INNER JOIN customers c ON c.id = s.customer_id
     LEFT JOIN invoices i ON i.service_id = s.id
     ORDER BY s.id DESC
     LIMIT 10"
)->fetchAll();

ob_start();
?>
<section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl border border-slate-200"><p class="text-sm text-slate-500"><?= e(__('dashboard.refill_sent')) ?></p><p class="text-3xl font-bold text-info"><?= $refillSent ?></p></div>
    <div class="bg-white p-4 rounded-xl border border-slate-200"><p class="text-sm text-slate-500"><?= e(__('dashboard.other_qty')) ?></p><p class="text-3xl font-bold text-slate-700"><?= $otherOut ?></p></div>
    <div class="bg-white p-4 rounded-xl border border-slate-200"><p class="text-sm text-slate-500"><?= e(__('dashboard.monthly_revenue')) ?></p><p class="text-3xl font-bold text-primary"><?= e(format_currency($monthlyRevenue)) ?></p></div>
    <div class="bg-white p-4 rounded-xl border border-slate-200"><p class="text-sm text-slate-500"><?= e(__('dashboard.pending_dues')) ?></p><p class="text-3xl font-bold text-warning"><?= e(format_currency($pendingAmount)) ?></p><p class="text-xs text-slate-500 mt-1"><?= e(__('dashboard.customers_count', ['n' => (string) $dueCustomers])) ?></p></div>
</section>
<section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl border border-slate-200"><p class="text-sm text-slate-500"><?= e(__('dashboard.stock_units')) ?></p><p class="text-2xl font-bold text-primary"><?= $inStock ?></p></div>
</section>
<section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <a href="?module=customer_reports<?= i18n_lang_query() ?>" class="bg-white border border-slate-200 rounded-xl p-4 hover:bg-slate-50 transition">
        <p class="text-sm text-slate-500">Reports</p>
        <p class="text-xl font-bold text-oxygenDeep">Customer Reports</p>
    </a>
    <a href="?module=supplier_reports<?= i18n_lang_query() ?>" class="bg-white border border-slate-200 rounded-xl p-4 hover:bg-slate-50 transition">
        <p class="text-sm text-slate-500">Reports</p>
        <p class="text-xl font-bold text-oxygenDeep">Supplier Reports</p>
    </a>
</section>
<section class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl p-4 xl:col-span-2"><h3 class="font-semibold mb-3"><?= e(__('dashboard.chart_revenue_title')) ?></h3><div class="h-72"><canvas id="revChart"></canvas></div></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><h3 class="font-semibold mb-3"><?= e(__('dashboard.chart_orders_type')) ?></h3><div class="h-72"><canvas id="serviceChart"></canvas></div></div>
</section>
<section class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold text-slate-700"><?= e(__('dashboard.recent_tx')) ?></h3>
        <div class="flex gap-2">
            <a href="?module=services<?= i18n_lang_query() ?>" class="rounded-lg bg-primary text-white px-3 py-2 text-sm"><?= e(__('dashboard.new_order')) ?></a>
            <a href="?module=customers<?= i18n_lang_query() ?>" class="rounded-lg bg-info text-white px-3 py-2 text-sm"><?= e(__('dashboard.new_customer')) ?></a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[760px]">
            <thead class="bg-slate-50"><tr><th data-sort class="text-left p-3"><?= e(__('dashboard.col_customer')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_service')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_qty')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_amount')) ?></th><th data-sort class="text-left p-3"><?= e(__('dashboard.col_pay_status')) ?></th></tr></thead>
            <tbody>
            <?php if (!$recentTransactions): ?>
                <tr><td colspan="5" class="p-4 text-slate-500"><?= e(__('dashboard.no_transactions')) ?></td></tr>
            <?php else: foreach ($recentTransactions as $tx): $total = (float) ($tx['order_total'] ?? 0); $paid = (float) ($tx['paid_amount'] ?? 0); $status = payment_status_from_amounts($total, $paid); ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3"><?= e((string) $tx['name']) ?></td><td class="p-3"><?= e(service_type_label((string) $tx['service_type'])) ?></td><td data-value="<?= (int) $tx['quantity'] ?>" class="p-3"><?= (int) $tx['quantity'] ?></td><td data-value="<?= $total ?>" class="p-3"><?= e(format_currency($total)) ?></td><td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
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
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.dashboard'), $content);
