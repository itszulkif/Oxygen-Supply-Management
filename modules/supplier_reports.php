<?php

declare(strict_types=1);

$pdo = db();

$supplierId = (int) ($_GET['supplier_id'] ?? 0);
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

$suppliers = table_exists($pdo, 'suppliers') ? $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll() : [];
$kpiPurchases = 0.0;
$kpiPaid = 0.0;
$kpiOutstanding = 0.0;
$purchases = [];
$payments = [];
$ledger = [];
$chartLabels = ['—'];
$chartPaid = [0.0];
$chartPurchases = [0.0];
$monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

if (table_exists($pdo, 'supplier_transactions')) {
    $supplierFilterTx = $supplierId > 0 ? ' AND supplier_id = ?' : '';
    $supplierFilterPay = $supplierId > 0 ? ' AND supplier_id = ?' : '';
    $supplierFilterLedger = $supplierId > 0 ? ' AND supplier_id = ?' : '';

    if ($period === 'yearly') {
        $paramsYear = $supplierId > 0 ? [$year, $supplierId] : [$year];
        $st = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM supplier_transactions WHERE YEAR(transaction_date)=?{$supplierFilterTx}");
        $st->execute($paramsYear);
        $kpiPurchases = (float) $st->fetchColumn();

        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE YEAR(payment_date)=?{$supplierFilterPay}");
            $st->execute($paramsYear);
            $kpiPaid = (float) $st->fetchColumn();
        }

        $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM supplier_transactions WHERE YEAR(transaction_date)=?{$supplierFilterTx}");
        $st->execute($paramsYear);
        $kpiOutstanding = (float) $st->fetchColumn();

        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount),0) AS t FROM supplier_payments WHERE YEAR(payment_date)=?{$supplierFilterPay} GROUP BY m");
            $st->execute($paramsYear);
            $paidMap = [];
            foreach ($st->fetchAll() as $r) $paidMap[(int) $r['m']] = (float) $r['t'];
        } else {
            $paidMap = [];
        }

        $st = $pdo->prepare("SELECT MONTH(transaction_date) AS m, COALESCE(SUM(total_amount),0) AS t FROM supplier_transactions WHERE YEAR(transaction_date)=?{$supplierFilterTx} GROUP BY m");
        $st->execute($paramsYear);
        $purchaseMap = [];
        foreach ($st->fetchAll() as $r) $purchaseMap[(int) $r['m']] = (float) $r['t'];

        $chartLabels = $monthShort;
        $chartPaid = $chartPurchases = [];
        for ($m = 1; $m <= 12; $m++) {
            $chartPaid[] = $paidMap[$m] ?? 0.0;
            $chartPurchases[] = $purchaseMap[$m] ?? 0.0;
        }

        $st = $pdo->prepare("SELECT id, transaction_date, cylinder_type, sent_quantity, total_received, total_amount, paid_amount, remaining_amount, payment_status FROM supplier_transactions WHERE YEAR(transaction_date)=?{$supplierFilterTx} ORDER BY transaction_date DESC, id DESC");
        $st->execute($paramsYear);
        $purchases = $st->fetchAll();

        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT payment_date, transaction_id, amount, payment_type FROM supplier_payments WHERE YEAR(payment_date)=?{$supplierFilterPay} ORDER BY payment_date DESC, id DESC");
            $st->execute($paramsYear);
            $payments = $st->fetchAll();
        }

        if (table_exists($pdo, 'supplier_ledger')) {
            $st = $pdo->prepare("SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE YEAR(entry_date)=?{$supplierFilterLedger} ORDER BY id DESC");
            $st->execute($paramsYear);
            $ledger = $st->fetchAll();
        }
    } else {
        $paramsMonth = $supplierId > 0 ? [$periodYear, $periodMonth, $supplierId] : [$periodYear, $periodMonth];
        $st = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM supplier_transactions WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?{$supplierFilterTx}");
        $st->execute($paramsMonth);
        $kpiPurchases = (float) $st->fetchColumn();

        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE YEAR(payment_date)=? AND MONTH(payment_date)=?{$supplierFilterPay}");
            $st->execute($paramsMonth);
            $kpiPaid = (float) $st->fetchColumn();
        }

        $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM supplier_transactions WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?{$supplierFilterTx}");
        $st->execute($paramsMonth);
        $kpiOutstanding = (float) $st->fetchColumn();

        $lastDay = (int) date('t', strtotime($month . '-01'));
        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT DAY(payment_date) AS d, COALESCE(SUM(amount),0) AS t FROM supplier_payments WHERE YEAR(payment_date)=? AND MONTH(payment_date)=?{$supplierFilterPay} GROUP BY d");
            $st->execute($paramsMonth);
            $paidMap = [];
            foreach ($st->fetchAll() as $r) $paidMap[(int) $r['d']] = (float) $r['t'];
        } else {
            $paidMap = [];
        }
        $st = $pdo->prepare("SELECT DAY(transaction_date) AS d, COALESCE(SUM(total_amount),0) AS t FROM supplier_transactions WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?{$supplierFilterTx} GROUP BY d");
        $st->execute($paramsMonth);
        $purchaseMap = [];
        foreach ($st->fetchAll() as $r) $purchaseMap[(int) $r['d']] = (float) $r['t'];

        $chartLabels = $chartPaid = $chartPurchases = [];
        for ($d = 1; $d <= $lastDay; $d++) {
            $chartLabels[] = (string) $d;
            $chartPaid[] = $paidMap[$d] ?? 0.0;
            $chartPurchases[] = $purchaseMap[$d] ?? 0.0;
        }

        $st = $pdo->prepare("SELECT id, transaction_date, cylinder_type, sent_quantity, total_received, total_amount, paid_amount, remaining_amount, payment_status FROM supplier_transactions WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?{$supplierFilterTx} ORDER BY transaction_date DESC, id DESC");
        $st->execute($paramsMonth);
        $purchases = $st->fetchAll();

        if (table_exists($pdo, 'supplier_payments')) {
            $st = $pdo->prepare("SELECT payment_date, transaction_id, amount, payment_type FROM supplier_payments WHERE YEAR(payment_date)=? AND MONTH(payment_date)=?{$supplierFilterPay} ORDER BY payment_date DESC, id DESC");
            $st->execute($paramsMonth);
            $payments = $st->fetchAll();
        }

        if (table_exists($pdo, 'supplier_ledger')) {
            $st = $pdo->prepare("SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE YEAR(entry_date)=? AND MONTH(entry_date)=?{$supplierFilterLedger} ORDER BY id DESC");
            $st->execute($paramsMonth);
            $ledger = $st->fetchAll();
        }
    }
}

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
    <h3 class="font-semibold mb-3">Supplier Reports</h3>
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-2">
        <input type="hidden" name="module" value="supplier_reports">
        <select name="supplier_id" class="border rounded-lg px-3 py-2 text-sm">
            <option value="0">Select supplier</option>
            <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="period" class="border rounded-lg px-3 py-2 text-sm"><option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option></select>
        <input type="<?= $period === 'yearly' ? 'number' : 'month' ?>" name="<?= $period === 'yearly' ? 'year' : 'month' ?>" value="<?= e($period === 'yearly' ? (string) $year : $month) ?>" class="border rounded-lg px-3 py-2 text-sm">
        <button class="bg-slate-700 text-white rounded-lg px-3 py-2 text-sm">Apply</button>
    </form>
</section>
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Purchases</p><p class="text-2xl font-bold text-oxygenDeep"><?= e(format_currency($kpiPurchases)) ?></p></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Paid</p><p class="text-2xl font-bold text-success"><?= e(format_currency($kpiPaid)) ?></p></div>
    <div class="bg-white border border-slate-200 rounded-xl p-4"><p class="text-sm text-slate-500">Outstanding</p><p class="text-2xl font-bold text-warning"><?= e(format_currency($kpiOutstanding)) ?></p></div>
</section>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5"><div class="h-72"><canvas id="supplierReportChart"></canvas></div></section>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Purchases</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[1080px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Purchase</th><th class="text-left p-3">Type</th><th class="text-left p-3">Sent</th><th class="text-left p-3">Received</th><th class="text-left p-3">Total</th><th class="text-left p-3">Paid</th><th class="text-left p-3">Remaining</th><th class="text-left p-3">Status</th></tr></thead><tbody><?php if (!$purchases): ?><tr><td colspan="9" class="p-4 text-slate-500">No purchases.</td></tr><?php endif; ?><?php foreach ($purchases as $r): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $r['transaction_date'])) ?></td><td class="p-3">SP-<?= (int) $r['id'] ?></td><td class="p-3"><?= e((string) $r['cylinder_type']) ?></td><td class="p-3"><?= (int) $r['sent_quantity'] ?></td><td class="p-3"><?= (int) $r['total_received'] ?></td><td class="p-3"><?= e(format_currency((float) $r['total_amount'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['paid_amount'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['remaining_amount'])) ?></td><td class="p-3"><?= e((string) $r['payment_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Payments</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[680px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Purchase</th><th class="text-left p-3">Type</th><th class="text-left p-3">Amount</th></tr></thead><tbody><?php if (!$payments): ?><tr><td colspan="4" class="p-4 text-slate-500">No payments.</td></tr><?php endif; ?><?php foreach ($payments as $p): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $p['payment_date'])) ?></td><td class="p-3">SP-<?= (int) $p['transaction_id'] ?></td><td class="p-3"><?= e((string) $p['payment_type']) ?></td><td class="p-3"><?= e(format_currency((float) $p['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden"><div class="px-4 py-3 border-b border-slate-200 font-semibold">Ledger</div><div class="overflow-x-auto"><table data-sortable="true" class="w-full text-sm min-w-[760px]"><thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Description</th><th class="text-left p-3">Debit</th><th class="text-left p-3">Credit</th><th class="text-left p-3">Balance</th></tr></thead><tbody><?php if (!$ledger): ?><tr><td colspan="5" class="p-4 text-slate-500">No ledger rows.</td></tr><?php endif; ?><?php foreach ($ledger as $l): ?><tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $l['entry_date'])) ?></td><td class="p-3"><?= e((string) ($l['description'] ?? '—')) ?></td><td class="p-3"><?= e(format_currency((float) $l['debit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['credit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['balance'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('supplierReportChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { label: 'Paid', data: <?= json_encode($chartPaid, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#0EA5E9' },
            { label: 'Purchases', data: <?= json_encode($chartPurchases, JSON_UNESCAPED_UNICODE) ?>, backgroundColor: '#94A3B8' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.supplier_reports'), $content);

