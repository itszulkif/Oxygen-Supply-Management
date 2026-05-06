<?php

declare(strict_types=1);

$pdo = db();

$customerId = (int) ($_GET['customer_id'] ?? 0);
$period = trim((string) ($_GET['period'] ?? 'monthly'));
if (!in_array($period, ['monthly', 'yearly'], true)) {
    $period = 'monthly';
}
$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$periodYear, $periodMonth] = array_map('intval', explode('-', $month, 2));
$periodMonth = max(1, min(12, $periodMonth));
$periodYear = max(2000, min(2100, $periodYear));
$month = sprintf('%04d-%02d', $periodYear, $periodMonth);
$year = (int) ($_GET['year'] ?? date('Y'));
$year = max(2000, min(2100, $year));

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();

$hasGrand = column_exists($pdo, 'services', 'grand_total');
$hasTotalBill = column_exists($pdo, 'services', 'total_bill');
$hasInvSvc = column_exists($pdo, 'invoices', 'service_id');
$parts = [];
if ($hasGrand) $parts[] = 's.grand_total';
if ($hasTotalBill) $parts[] = 's.total_bill';
if ($hasInvSvc) $parts[] = 'i.total_amount';
$parts[] = '(s.quantity * s.price)';
$serviceAmountExpr = 'COALESCE(' . implode(', ', $parts) . ')';

$kpiBilled = 0.0;
$kpiPaid = 0.0;
$kpiOutstanding = 0.0;
$orders = [];
$payments = [];
$ledger = [];
$chartLabels = ['—'];
$chartPaid = [0.0];
$chartBilled = [0.0];
$monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$customerFilterServices = $customerId > 0 ? ' AND s.customer_id = ?' : '';
$customerFilterInvoices = $customerId > 0 ? ' AND i.customer_id = ?' : '';
$customerFilterLedger = $customerId > 0 ? ' AND customer_id = ?' : '';
$customerFilterPlainInvoices = $customerId > 0 ? ' WHERE customer_id = ?' : '';

if ($customerId === 0) {
    $st = $pdo->query("SELECT COALESCE(SUM({$serviceAmountExpr}),0) FROM services s LEFT JOIN invoices i ON i.service_id = s.id");
    $kpiBilled = (float) $st->fetchColumn();

    $st = $pdo->query("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id");
    $kpiPaid = (float) $st->fetchColumn();

    $st = $pdo->query("SELECT COALESCE(SUM(remaining_amount),0) FROM invoices");
    $kpiOutstanding = (float) $st->fetchColumn();

    $st = $pdo->query("SELECT YEAR(s.date) AS y, MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}),0) AS t FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY y, m");
    $billedMap = [];
    foreach ($st->fetchAll() as $r) $billedMap[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['t'];

    $st = $pdo->query("SELECT YEAR(p.payment_date) AS y, MONTH(p.payment_date) AS m, COALESCE(SUM(p.amount),0) AS t FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY y, m");
    $paidMap = [];
    foreach ($st->fetchAll() as $r) $paidMap[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['t'];

    $chartLabels = $chartPaid = $chartBilled = [];
    $anchor = new DateTimeImmutable('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $d = $anchor->modify('-' . $i . ' months');
        $y = (int) $d->format('Y');
        $m = (int) $d->format('n');
        $k = $y . '-' . $m;
        $chartLabels[] = i18n_month_short($m);
        $chartPaid[] = $paidMap[$k] ?? 0.0;
        $chartBilled[] = $billedMap[$k] ?? 0.0;
    }

    $ordersSt = $pdo->query("SELECT s.id, s.date, s.service_type, s.quantity, {$serviceAmountExpr} AS line_total, COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt, COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt FROM services s LEFT JOIN invoices i ON i.service_id = s.id ORDER BY s.date DESC, s.id DESC");
    $orders = $ordersSt->fetchAll();

    $pSt = $pdo->query("SELECT p.payment_date, p.amount, i.id AS invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id ORDER BY p.payment_date DESC");
    $payments = $pSt->fetchAll();

    $lSt = $pdo->query("SELECT date, description, debit, credit, balance FROM ledger ORDER BY id DESC");
    $ledger = $lSt->fetchAll();
} elseif ($period === 'yearly') {
    $paramsServices = $customerId > 0 ? [$year, $customerId] : [$year];
    $paramsPayments = $customerId > 0 ? [$year, $customerId] : [$year];
    $paramsLedger = $customerId > 0 ? [$year, $customerId] : [$year];

    $st = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date) = ?{$customerFilterServices}");
    $st->execute($paramsServices);
    $kpiBilled = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date) = ?{$customerFilterInvoices}");
    $st->execute($paramsPayments);
    $kpiPaid = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM invoices{$customerFilterPlainInvoices}");
    $st->execute($customerId > 0 ? [$customerId] : []);
    $kpiOutstanding = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}),0) AS t FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date)=?{$customerFilterServices} GROUP BY m");
    $st->execute($paramsServices);
    $billedMap = [];
    foreach ($st->fetchAll() as $r) $billedMap[(int) $r['m']] = (float) $r['t'];

    $st = $pdo->prepare("SELECT MONTH(p.payment_date) AS m, COALESCE(SUM(p.amount),0) AS t FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date)=?{$customerFilterInvoices} GROUP BY m");
    $st->execute($paramsPayments);
    $paidMap = [];
    foreach ($st->fetchAll() as $r) $paidMap[(int) $r['m']] = (float) $r['t'];

    $chartLabels = $monthShort;
    $chartPaid = $chartBilled = [];
    for ($m = 1; $m <= 12; $m++) {
        $chartPaid[] = $paidMap[$m] ?? 0.0;
        $chartBilled[] = $billedMap[$m] ?? 0.0;
    }

    $ordersSt = $pdo->prepare("SELECT s.id, s.date, s.service_type, s.quantity, {$serviceAmountExpr} AS line_total, COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt, COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date)=?{$customerFilterServices} ORDER BY s.date DESC, s.id DESC");
    $ordersSt->execute($paramsServices);
    $orders = $ordersSt->fetchAll();

    $pSt = $pdo->prepare("SELECT p.payment_date, p.amount, i.id AS invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date)=?{$customerFilterInvoices} ORDER BY p.payment_date DESC");
    $pSt->execute($paramsPayments);
    $payments = $pSt->fetchAll();

    $lSt = $pdo->prepare("SELECT date, description, debit, credit, balance FROM ledger WHERE YEAR(date)=?{$customerFilterLedger} ORDER BY id DESC");
    $lSt->execute($paramsLedger);
    $ledger = $lSt->fetchAll();
} else {
    $paramsServices = $customerId > 0 ? [$periodYear, $periodMonth, $customerId] : [$periodYear, $periodMonth];
    $paramsPayments = $customerId > 0 ? [$periodYear, $periodMonth, $customerId] : [$periodYear, $periodMonth];
    $paramsLedger = $customerId > 0 ? [$periodYear, $periodMonth, $customerId] : [$periodYear, $periodMonth];

    $st = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date) = ? AND MONTH(s.date)=?{$customerFilterServices}");
    $st->execute($paramsServices);
    $kpiBilled = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date) = ? AND MONTH(p.payment_date)=?{$customerFilterInvoices}");
    $st->execute($paramsPayments);
    $kpiPaid = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM invoices{$customerFilterPlainInvoices}");
    $st->execute($customerId > 0 ? [$customerId] : []);
    $kpiOutstanding = (float) $st->fetchColumn();

    $lastDay = (int) date('t', strtotime($month . '-01'));
    $st = $pdo->prepare("SELECT DAY(s.date) AS d, COALESCE(SUM({$serviceAmountExpr}),0) AS t FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date)=? AND MONTH(s.date)=?{$customerFilterServices} GROUP BY d");
    $st->execute($paramsServices);
    $billedMap = [];
    foreach ($st->fetchAll() as $r) $billedMap[(int) $r['d']] = (float) $r['t'];

    $st = $pdo->prepare("SELECT DAY(p.payment_date) AS d, COALESCE(SUM(p.amount),0) AS t FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date)=? AND MONTH(p.payment_date)=?{$customerFilterInvoices} GROUP BY d");
    $st->execute($paramsPayments);
    $paidMap = [];
    foreach ($st->fetchAll() as $r) $paidMap[(int) $r['d']] = (float) $r['t'];

    $chartLabels = $chartPaid = $chartBilled = [];
    for ($d = 1; $d <= $lastDay; $d++) {
        $chartLabels[] = (string) $d;
        $chartPaid[] = $paidMap[$d] ?? 0.0;
        $chartBilled[] = $billedMap[$d] ?? 0.0;
    }

    $ordersSt = $pdo->prepare("SELECT s.id, s.date, s.service_type, s.quantity, {$serviceAmountExpr} AS line_total, COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt, COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE YEAR(s.date)=? AND MONTH(s.date)=?{$customerFilterServices} ORDER BY s.date DESC, s.id DESC");
    $ordersSt->execute($paramsServices);
    $orders = $ordersSt->fetchAll();

    $pSt = $pdo->prepare("SELECT p.payment_date, p.amount, i.id AS invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE YEAR(p.payment_date)=? AND MONTH(p.payment_date)=?{$customerFilterInvoices} ORDER BY p.payment_date DESC");
    $pSt->execute($paramsPayments);
    $payments = $pSt->fetchAll();

    $lSt = $pdo->prepare("SELECT date, description, debit, credit, balance FROM ledger WHERE YEAR(date)=? AND MONTH(date)=?{$customerFilterLedger} ORDER BY id DESC");
    $lSt->execute($paramsLedger);
    $ledger = $lSt->fetchAll();
}

// Ensure selected customer always shows their full dynamic report data.
if ($customerId > 0) {
    $st = $pdo->prepare("SELECT COALESCE(SUM({$serviceAmountExpr}),0) FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE s.customer_id = ?");
    $st->execute([$customerId]);
    $kpiBilled = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ?");
    $st->execute([$customerId]);
    $kpiPaid = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM invoices WHERE customer_id = ?");
    $st->execute([$customerId]);
    $kpiOutstanding = (float) $st->fetchColumn();

    $st = $pdo->prepare("SELECT YEAR(s.date) AS y, MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}),0) AS t
        FROM services s LEFT JOIN invoices i ON i.service_id = s.id
        WHERE s.customer_id = ? AND s.date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY y, m");
    $st->execute([$customerId]);
    $billedMap = [];
    foreach ($st->fetchAll() as $r) $billedMap[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['t'];

    $st = $pdo->prepare("SELECT YEAR(p.payment_date) AS y, MONTH(p.payment_date) AS m, COALESCE(SUM(p.amount),0) AS t
        FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id
        WHERE i.customer_id = ? AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY y, m");
    $st->execute([$customerId]);
    $paidMap = [];
    foreach ($st->fetchAll() as $r) $paidMap[(int) $r['y'] . '-' . (int) $r['m']] = (float) $r['t'];

    $chartLabels = $chartPaid = $chartBilled = [];
    $anchor = new DateTimeImmutable('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $d = $anchor->modify('-' . $i . ' months');
        $y = (int) $d->format('Y');
        $m = (int) $d->format('n');
        $k = $y . '-' . $m;
        $chartLabels[] = i18n_month_short($m);
        $chartPaid[] = $paidMap[$k] ?? 0.0;
        $chartBilled[] = $billedMap[$k] ?? 0.0;
    }

    $ordersSt = $pdo->prepare("SELECT s.id, s.date, s.service_type, s.quantity, {$serviceAmountExpr} AS line_total,
        COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt, COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt
        FROM services s LEFT JOIN invoices i ON i.service_id = s.id
        WHERE s.customer_id = ?
        ORDER BY s.date DESC, s.id DESC");
    $ordersSt->execute([$customerId]);
    $orders = $ordersSt->fetchAll();

    $pSt = $pdo->prepare("SELECT p.payment_date, p.amount, i.id AS invoice_id
        FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id
        WHERE i.customer_id = ?
        ORDER BY p.payment_date DESC");
    $pSt->execute([$customerId]);
    $payments = $pSt->fetchAll();

    $lSt = $pdo->prepare("SELECT date, description, debit, credit, balance
        FROM ledger
        WHERE customer_id = ?
        ORDER BY id DESC");
    $lSt->execute([$customerId]);
    $ledger = $lSt->fetchAll();
}

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
    <h3 class="font-semibold mb-3">Customer Reports</h3>
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-2">
        <input type="hidden" name="module" value="customer_reports">
        <select name="customer_id" class="border rounded-lg px-3 py-2 text-sm">
            <option value="0">Select customer</option>
            <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $customerId === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="period" class="border rounded-lg px-3 py-2 text-sm"><option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option></select>
        <input type="<?= $period === 'yearly' ? 'number' : 'month' ?>" name="<?= $period === 'yearly' ? 'year' : 'month' ?>" value="<?= e($period === 'yearly' ? (string) $year : $month) ?>" class="border rounded-lg px-3 py-2 text-sm">
        <button class="bg-slate-700 text-white rounded-lg px-3 py-2 text-sm">Apply</button>
    </form>
</section>
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Billed</p><p class="text-2xl font-bold text-oxygenDeep"><?= e(format_currency($kpiBilled)) ?></p></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Paid</p><p class="text-2xl font-bold text-success"><?= e(format_currency($kpiPaid)) ?></p></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Outstanding</p><p class="text-2xl font-bold text-warning"><?= e(format_currency($kpiOutstanding)) ?></p></div>
</section>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5"><div class="h-72"><canvas id="customerReportChart"></canvas></div></section>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Orders</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[940px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Order</th><th class="text-left p-3">Service</th><th class="text-left p-3">Qty</th><th class="text-left p-3">Line total</th><th class="text-left p-3">Paid</th><th class="text-left p-3">Remaining</th></tr></thead><tbody><?php if (!$orders): ?><tr><td colspan="7" class="p-4 text-slate-500">No orders.</td></tr><?php endif; ?><?php foreach ($orders as $r): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $r['date'])) ?></td><td class="p-3">ORD-<?= (int) $r['id'] ?></td><td class="p-3"><?= e(service_type_label((string) $r['service_type'])) ?></td><td class="p-3"><?= (int) $r['quantity'] ?></td><td class="p-3"><?= e(format_currency((float) $r['line_total'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['paid_amt'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['rem_amt'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Payments</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[620px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Invoice</th><th class="text-left p-3">Amount</th></tr></thead><tbody><?php if (!$payments): ?><tr><td colspan="3" class="p-4 text-slate-500">No payments.</td></tr><?php endif; ?><?php foreach ($payments as $p): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $p['payment_date'])) ?></td><td class="p-3">INV-<?= (int) $p['invoice_id'] ?></td><td class="p-3"><?= e(format_currency((float) $p['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Ledger</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[760px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Description</th><th class="text-left p-3">Debit</th><th class="text-left p-3">Credit</th><th class="text-left p-3">Balance</th></tr></thead><tbody><?php if (!$ledger): ?><tr><td colspan="5" class="p-4 text-slate-500">No ledger rows.</td></tr><?php endif; ?><?php foreach ($ledger as $l): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $l['date'])) ?></td><td class="p-3"><?= e((string) ($l['description'] ?? '—')) ?></td><td class="p-3"><?= e(format_currency((float) $l['debit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['credit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['balance'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('customerReportChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { label: 'Paid', data: <?= json_encode($chartPaid, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#0EA5E9' },
            { label: 'Billed', data: <?= json_encode($chartBilled, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#94A3B8' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.customer_reports'), $content);

