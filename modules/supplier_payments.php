<?php

declare(strict_types=1);

$pdo = db();
$period = trim((string) ($_GET['period'] ?? 'weekly'));
if (!in_array($period, ['weekly', 'monthly'], true)) {
    $period = 'weekly';
}
$supplierId = (int) ($_GET['supplier_id'] ?? 0);

$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();

$days = $period === 'monthly' ? 30 : 7;
$params = [date('Y-m-d', strtotime('-' . $days . ' days'))];
$where = ['sp.payment_date >= ?'];
if ($supplierId > 0) {
    $where[] = 'sp.supplier_id = ?';
    $params[] = $supplierId;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$summarySt = $pdo->prepare("SELECT COALESCE(SUM(sp.amount),0) AS paid
    FROM supplier_payments sp
    $whereSql");
$summarySt->execute($params);
$paid = (float) ($summarySt->fetchColumn() ?: 0);

$outParams = [];
$outWhere = [];
if ($supplierId > 0) {
    $outWhere[] = 'supplier_id = ?';
    $outParams[] = $supplierId;
}
$outWhereSql = $outWhere ? ('WHERE ' . implode(' AND ', $outWhere)) : '';
$outSt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM supplier_transactions $outWhereSql");
$outSt->execute($outParams);
$outstanding = (float) ($outSt->fetchColumn() ?: 0);

$rowsSt = $pdo->prepare("SELECT sp.id, sp.payment_date, sp.amount, sp.transaction_id, s.name AS supplier_name,
        st.transaction_date, st.total_amount, st.paid_amount, st.remaining_amount
    FROM supplier_payments sp
    INNER JOIN suppliers s ON s.id = sp.supplier_id
    LEFT JOIN supplier_transactions st ON st.id = sp.transaction_id
    $whereSql
    ORDER BY sp.payment_date DESC, sp.id DESC");
$rowsSt->execute($params);
$rows = $rowsSt->fetchAll();

ob_start();
?>
<section class="app-card p-4 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.payment_history_title')) ?></h3>
        <a href="?module=suppliers<?= i18n_lang_query() ?>" class="btn btn-soft">Back to Supplier Command Center</a>
    </div>
    <div class="flex flex-wrap gap-2 text-xs mt-3">
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800"><?= e(__('suppliers.badge_paid_period')) ?>: <strong><?= e(format_currency($paid)) ?></strong></span>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800"><?= e(__('suppliers.badge_outstanding')) ?>: <strong><?= e(format_currency($outstanding)) ?></strong></span>
    </div>
    <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
        <input type="hidden" name="module" value="supplier_payments">
        <select name="period" class="app-input">
            <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>><?= e(__('suppliers.period_weekly')) ?></option>
            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>><?= e(__('suppliers.period_monthly')) ?></option>
        </select>
        <select name="supplier_id" class="app-input">
            <option value="0"><?= e(__('suppliers.all_suppliers')) ?></option>
            <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><?= e(__('suppliers.apply_payment_filter')) ?></button>
    </form>
</section>

<section class="app-card overflow-hidden">
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[1100px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.ph_last_payment')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_supplier')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchase_ref')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchase_date')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total_due')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_outstanding_balance')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="p-4 text-slate-500"><?= e(__('suppliers.no_payment_records')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3"><?= e((string) $row['payment_date']) ?></td>
                    <td class="p-3"><?= e((string) $row['supplier_name']) ?></td>
                    <td class="p-3">SP-<?= (int) $row['transaction_id'] ?></td>
                    <td class="p-3"><?= e((string) ($row['transaction_date'] ?? '-')) ?></td>
                    <td class="p-3"><?= e(format_currency((float) ($row['total_amount'] ?? 0))) ?></td>
                    <td class="p-3"><?= e(format_currency((float) ($row['paid_amount'] ?? 0))) ?></td>
                    <td class="p-3"><?= e(format_currency((float) ($row['remaining_amount'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();
render_layout('Supplier Payment History', $content);

