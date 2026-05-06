<?php

declare(strict_types=1);

$pdo = db();
$search = trim((string) ($_GET['q'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$supplierId = (int) ($_GET['supplier_id'] ?? 0);

$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();

$clauses = [];
$params = [];
if ($search !== '') {
    $clauses[] = '(s.name LIKE ? OR s.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '') {
    $clauses[] = 't.transaction_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $clauses[] = 't.transaction_date <= ?';
    $params[] = $to;
}
if ($supplierId > 0) {
    $clauses[] = 't.supplier_id = ?';
    $params[] = $supplierId;
}

$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';
$sql = "SELECT t.id, t.transaction_date, t.cylinder_type, t.sent_quantity, t.total_received, t.inventory_quantity,
        t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status, s.name AS supplier_name
    FROM supplier_transactions t
    INNER JOIN suppliers s ON s.id = t.supplier_id
    $where
    ORDER BY t.transaction_date DESC, t.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

ob_start();
?>
<section class="app-card p-4 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-oxygenDeep">Purchase Records</h3>
        <a href="?module=suppliers<?= i18n_lang_query() ?>" class="btn btn-soft">Back to Supplier Command Center</a>
    </div>
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-3">
        <input type="hidden" name="module" value="supplier_purchases">
        <input type="search" name="q" class="app-input md:col-span-2" value="<?= e($search) ?>" placeholder="<?= e(__('suppliers.search_placeholder')) ?>">
        <input type="date" name="from" class="app-input" value="<?= e($from) ?>">
        <input type="date" name="to" class="app-input" value="<?= e($to) ?>">
        <select name="supplier_id" class="app-input">
            <option value="0"><?= e(__('suppliers.all_suppliers')) ?></option>
            <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary md:col-span-5"><?= e(__('suppliers.apply_filter')) ?></button>
    </form>
</section>
<section class="app-card overflow-hidden">
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[1060px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.col_date')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_supplier')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_type')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_sent')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_received')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_inventory_qty')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_remaining')) ?></th>
                <th class="text-start p-3"><?= e(__('common.status')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="p-4 text-slate-500"><?= e(__('suppliers.no_purchases_filter')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3"><?= e((string) $row['transaction_date']) ?></td>
                    <td class="p-3"><?= e((string) $row['supplier_name']) ?></td>
                    <td class="p-3"><?= e(cylinder_size_label((string) $row['cylinder_type'])) ?></td>
                    <td class="p-3"><?= (int) $row['sent_quantity'] ?></td>
                    <td class="p-3"><?= (int) $row['total_received'] ?></td>
                    <td class="p-3"><?= e(number_format((float) $row['inventory_quantity'], 2)) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['total_amount'])) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['paid_amount'])) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
                    <td class="p-3"><?= e((string) $row['payment_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$content = ob_get_clean();
render_layout('Purchase Records', $content);

