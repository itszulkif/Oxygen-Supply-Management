<?php

declare(strict_types=1);

$pdo = db();
$search = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $search . '%';

$sql = "SELECT s.id, s.name, s.contact_person, s.phone, s.opening_balance,
        COALESCE((SELECT SUM(t.total_amount) FROM supplier_transactions t WHERE t.supplier_id = s.id), 0) AS purchases,
        COALESCE((SELECT SUM(p.amount) FROM supplier_payments p WHERE p.supplier_id = s.id), 0) AS paid
    FROM suppliers s
    WHERE (? = '' OR s.name LIKE ? OR s.phone LIKE ? OR s.contact_person LIKE ?)
    ORDER BY s.name ASC";
$st = $pdo->prepare($sql);
$st->execute([$search, $like, $like, $like]);
$rows = $st->fetchAll();

ob_start();
?>
<section class="app-card p-4 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-oxygenDeep">Supplier List</h3>
        <a href="?module=suppliers<?= i18n_lang_query() ?>" class="btn btn-soft">Back to Supplier Command Center</a>
    </div>
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
        <input type="hidden" name="module" value="suppliers_list">
        <input type="search" name="q" class="app-input md:col-span-3" value="<?= e($search) ?>" placeholder="<?= e(__('suppliers.search_placeholder')) ?>">
        <button class="btn btn-primary"><?= e(__('common.search')) ?></button>
    </form>
</section>

<section class="app-card overflow-hidden">
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[980px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.col_company')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_contact')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_phone')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchases')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_balance')) ?></th>
                <th class="text-start p-3"><?= e(__('common.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="p-4 text-slate-500"><?= e(__('suppliers.no_suppliers')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): $balance = supplier_outstanding_balance($pdo, (int) ($row['id'] ?? 0)); ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3"><?= e((string) $row['name']) ?></td>
                    <td class="p-3"><?= e((string) ($row['contact_person'] ?? '-')) ?></td>
                    <td class="p-3"><?= e((string) ($row['phone'] ?? '-')) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['purchases'])) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['paid'])) ?></td>
                    <td class="p-3 <?= $balance > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600' ?>"><?= e(format_currency($balance)) ?></td>
                    <td class="p-3"><a class="btn btn-soft" href="?module=suppliers&action=view_ledger&id=<?= (int) $row['id'] ?><?= i18n_lang_query() ?>"><?= e(__('suppliers.btn_view_ledger')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
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
render_layout('Supplier List', $content);

