<?php

$pdo = db();

if (($_GET['ajax'] ?? '') === 'customer_balance') {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int) ($_GET['customer_id'] ?? 0);
    if ($cid <= 0) {
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $map = customer_receivable_map($pdo, [$cid]);
    echo json_encode(['ok' => true, 'balance' => $map[$cid] ?? ['receivable' => 0, 'receivable_formatted' => format_currency(0)]], JSON_UNESCAPED_UNICODE);
    exit;
}

$customer_type_label = static function (string $type): string {
    return match ($type) {
        'Retail' => __('customers.retail'),
        'Wholesale' => __('customers.wholesale'),
        'Home Delivery' => __('customers.home_delivery'),
        default => $type,
    };
};
$hasType = column_exists($pdo, 'customers', 'customer_type');
$hasNotes = column_exists($pdo, 'customers', 'notes');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$deleteId = isset($_GET['delete']) ? (int) $_GET['delete'] : 0;
$detailId = isset($_GET['detail']) ? (int) $_GET['detail'] : 0;

if ($deleteId > 0) {
    $invoiceCountStmt = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE customer_id = ?');
    $invoiceCountStmt->execute([$deleteId]);
    $invoiceCount = (int) $invoiceCountStmt->fetchColumn();
    if ($invoiceCount > 0) {
        header('Location: ?module=customers&toast=' . urlencode(__('toast.customer_has_invoices')) . i18n_lang_query());
        exit;
    }
    $serviceCountStmt = $pdo->prepare('SELECT COUNT(*) FROM services WHERE customer_id = ?');
    $serviceCountStmt->execute([$deleteId]);
    $serviceCount = (int) $serviceCountStmt->fetchColumn();
    if ($serviceCount > 0) {
        header('Location: ?module=customers&toast=' . urlencode(__('toast.customer_has_services')) . i18n_lang_query());
        exit;
    }
    try {
        $del = $pdo->prepare('DELETE FROM customers WHERE id = ?');
        $del->execute([$deleteId]);
        header('Location: ?module=customers&toast=' . urlencode(__('toast.customer_deleted')) . i18n_lang_query());
    } catch (PDOException $e) {
        header('Location: ?module=customers&toast=' . urlencode(__('toast.delete_linked_records')) . i18n_lang_query());
    }
    exit;
}

$editCustomer = ['id' => 0, 'name' => '', 'phone' => '', 'address' => '', 'customer_type' => 'Retail', 'notes' => ''];
if ($editId > 0) {
    $es = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $es->execute([$editId]);
    $editCustomer = $es->fetch() ?: $editCustomer;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = request_value('name');
    $phone = request_value('phone');
    $address = request_value('address');
    $customerType = request_value('customer_type', 'Retail');
    $notes = request_value('notes');
    $formId = (int) request_value('id', '0');
    if ($formId > 0) {
        $setParts = ['name = ?', 'phone = ?', 'address = ?'];
        $values = [$name, $phone, $address];
        if ($hasType) {
            $setParts[] = 'customer_type = ?';
            $values[] = $customerType;
        }
        if ($hasNotes) {
            $setParts[] = 'notes = ?';
            $values[] = $notes;
        }
        $values[] = $formId;
        $stmt = $pdo->prepare('UPDATE customers SET ' . implode(', ', $setParts) . ' WHERE id = ?');
        $stmt->execute($values);
    } else {
        $columns = ['name', 'phone', 'address'];
        $placeholders = ['?', '?', '?'];
        $values = [$name, $phone, $address];
        if ($hasType) {
            $columns[] = 'customer_type';
            $placeholders[] = '?';
            $values[] = $customerType;
        }
        if ($hasNotes) {
            $columns[] = 'notes';
            $placeholders[] = '?';
            $values[] = $notes;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO customers (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($values);
        $newCustomerId = (int) $pdo->lastInsertId();
        if ($newCustomerId > 0) {
            customer_insert_opening_ledger(
                $pdo,
                $newCustomerId,
                max(0, (float) request_value('opening_balance', '0')),
                (int) request_value('opening_cylinders', '0')
            );
        }
    }
    header('Location: ?module=customers&toast=' . urlencode(__('toast.customer_saved')) . i18n_lang_query());
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$typeFilter = trim((string) ($_GET['type'] ?? ''));

$outstandingExpr = table_exists($pdo, 'ledger')
    ? '(SELECT COALESCE(l.balance, 0) FROM ledger l WHERE l.customer_id = c.id ORDER BY l.id DESC LIMIT 1)'
    : '(SELECT COALESCE(SUM(i.remaining_amount),0) FROM invoices i WHERE i.customer_id = c.id)';
$query = "SELECT c.*,
          (SELECT COUNT(*) FROM services s WHERE s.customer_id = c.id) AS total_orders,
          {$outstandingExpr} AS outstanding
          FROM customers c WHERE 1=1";
$params = [];
if ($search !== '') {
    $query .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($hasType && $typeFilter !== '') {
    $query .= ' AND c.customer_type = ?';
    $params[] = $typeFilter;
}
$query .= ' ORDER BY c.id DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$detail = null;
$detailOrders = [];
$detailPayments = [];
$detailLedger = [];
$detailInvoices = [];
if ($detailId > 0) {
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$detailId]);
    $detail = $st->fetch();
    if ($detail) {
        $o = $pdo->prepare("SELECT service_type, date, quantity, price, (quantity*price) AS amount FROM services WHERE customer_id = ? ORDER BY date DESC");
        $o->execute([$detailId]);
        $detailOrders = $o->fetchAll();
        $inv = $pdo->prepare("SELECT id, total_amount, paid_amount, remaining_amount, status, created_at FROM invoices WHERE customer_id = ? ORDER BY id DESC");
        $inv->execute([$detailId]);
        $detailInvoices = $inv->fetchAll();
        $p = $pdo->prepare("SELECT p.payment_date, p.amount, i.id AS invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? ORDER BY p.payment_date DESC");
        $p->execute([$detailId]);
        $detailPayments = $p->fetchAll();
        $l = $pdo->prepare("SELECT date, debit, credit, balance FROM ledger WHERE customer_id = ? ORDER BY date DESC");
        $l->execute([$detailId]);
        $detailLedger = $l->fetchAll();
    }
}

$detailOutstanding = $detail ? customer_receivable_balance($pdo, $detailId) : 0.0;

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="font-semibold shrink-0"><?= e(__('meta.customers')) ?></h3>
        <form method="get" class="grid grid-cols-1 sm:grid-cols-4 gap-2 w-full sm:max-w-3xl sm:ms-auto sm:flex-1">
            <input type="hidden" name="module" value="customers">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input name="search" value="<?= e($search) ?>" placeholder="<?= e(__('customers.search_ph')) ?>" class="border rounded-lg px-3 py-2 text-sm sm:col-span-2">
            <button class="bg-info text-white rounded-lg px-3 py-2 text-sm"><?= e(__('customers.search_btn')) ?></button>
            <button type="button" class="bg-primary text-white rounded-lg px-3 py-2 text-sm" data-open-modal="addCustomerModal"><?= e(__('customers.add')) ?></button>
        </form>
    </div>
    <div class="overflow-x-auto table-wrap">
        <table data-sortable="true" class="w-full text-sm min-w-[900px] table-fixed md:table-auto">
            <thead class="bg-slate-50">
            <tr>
                <th data-sort class="text-start p-3 w-24"><?= e(__('customers.col_id')) ?></th>
                <th data-sort class="text-start p-3 min-w-[140px]"><?= e(__('customers.col_name')) ?></th>
                <th data-sort class="text-start p-3 min-w-[120px]"><?= e(__('customers.col_phone')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap"><?= e(__('customers.col_orders')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap min-w-[120px]"><?= e(__('customers.col_outstanding')) ?></th>
                <th class="text-end p-3 w-[1%] whitespace-nowrap"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="p-4 text-slate-500"><?= e(__('customers.empty_list')) ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr data-row="true" class="border-t border-slate-100" data-customer-id="<?= (int) $row['id'] ?>">
                        <td class="px-3 py-2.5 text-start align-middle tabular-nums">C-<?= (int) $row['id'] ?></td>
                        <td class="px-3 py-2.5 text-start align-middle"><?= e($row['name']) ?></td>
                        <td class="px-3 py-2.5 text-start align-middle tabular-nums"><?= e($row['phone']) ?></td>
                        <td data-value="<?= (int) $row['total_orders'] ?>" class="px-3 py-2.5 text-end align-middle tabular-nums"><?= (int) $row['total_orders'] ?></td>
                        <td data-value="<?= (float) $row['outstanding'] ?>" data-customer-outstanding class="px-3 py-2.5 text-end align-middle tabular-nums <?= (float) $row['outstanding'] > 0 ? 'text-warning font-medium' : 'text-primary' ?>"><?= e(format_currency((float) $row['outstanding'])) ?></td>
                        <td class="px-3 py-2.5 text-end align-middle whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <a href="<?= e('?module=services&customer_id=' . (int) $row['id'] . i18n_lang_query()) ?>" class="inline-flex rounded bg-indigo-100 px-2 py-1 text-xs text-indigo-800"><?= e(__('customers.new_order')) ?></a>
                                <a href="<?= e('?module=customers&detail=' . (int) $row['id'] . i18n_lang_query()) ?>" class="inline-flex rounded bg-emerald-100 px-2 py-1 text-xs text-primary"><?= e(__('customers.view')) ?></a>
                                <a href="<?= e('?module=customers&edit=' . (int) $row['id'] . i18n_lang_query()) ?>" class="inline-flex rounded bg-sky-100 px-2 py-1 text-xs text-sky-700"><?= e(__('customers.edit_btn')) ?></a>
                                <a href="<?= e('?module=customers&delete=' . (int) $row['id'] . i18n_lang_query()) ?>" class="inline-flex rounded bg-rose-100 px-2 py-1 text-xs text-rose-700" data-confirm="<?= e(__('customers.confirm_delete')) ?>"><?= e(__('common.delete')) ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<div id="addCustomerModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-modal="addCustomerModal"></div>
    <div class="relative w-full max-w-3xl bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="font-semibold"><?= $editId > 0 ? e(__('customers.edit')) : e(__('customers.add')) ?></h3>
            <button type="button" class="btn btn-soft" data-close-modal="addCustomerModal"><?= e(__('common.close')) ?></button>
        </div>
        <form method="post" class="flex flex-col gap-3" novalidate>
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="id" value="<?= (int) $editCustomer['id'] ?>">
            <input name="name" required placeholder="<?= e(__('customers.name')) ?>" value="<?= e((string) $editCustomer['name']) ?>" class="w-full border rounded-lg p-2">
            <input name="phone" required placeholder="<?= e(__('customers.phone')) ?>" value="<?= e((string) $editCustomer['phone']) ?>" class="w-full border rounded-lg p-2">
            <input name="address" placeholder="<?= e(__('customers.address')) ?>" value="<?= e((string) $editCustomer['address']) ?>" class="w-full border rounded-lg p-2">
            <?php if ($editId > 0): ?>
                <select name="customer_type" class="w-full border rounded-lg p-2">
                    <?php foreach (['Retail', 'Wholesale', 'Home Delivery'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= (($editCustomer['customer_type'] ?? 'Retail') === $type) ? 'selected' : '' ?>><?= e($customer_type_label($type)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="customer_type" value="Retail">
                <input type="text" readonly value="<?= e($customer_type_label('Retail')) ?>" class="w-full border rounded-lg p-2 bg-slate-50 text-slate-500">
            <?php endif; ?>
            <input name="notes" placeholder="<?= e(__('customers.notes')) ?>" value="<?= e((string) ($editCustomer['notes'] ?? '')) ?>" class="w-full border rounded-lg p-2">
            <?php if ($editId <= 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('customers.opening_balance')) ?></span>
                    <input name="opening_balance" type="number" step="0.01" min="0" placeholder="<?= e(__('customers.ph_opening_balance')) ?>" class="w-full border rounded-lg p-2 mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('customers.opening_cylinders')) ?></span>
                    <input name="opening_cylinders" type="number" step="1" placeholder="<?= e(__('customers.ph_opening_cylinders')) ?>" class="w-full border rounded-lg p-2 mt-1">
                </label>
            </div>
            <?php endif; ?>
            <button class="w-full bg-primary text-white rounded-lg py-2 px-3"><?= $editId > 0 ? e(__('customers.update_btn')) : e(__('customers.add')) ?></button>
        </form>
    </div>
</div>
<?php if ($editId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('addCustomerModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
});
</script>
<?php endif; ?>
<?php if ($detail): ?>
<section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4"><h3 class="font-semibold"><?= e(__('customers.detail_heading', ['name' => (string) $detail['name']])) ?></h3><p class="text-sm text-warning font-semibold"><?= e(__('customers.label_outstanding')) ?>: <?= e(format_currency($detailOutstanding)) ?></p></div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm mb-4">
        <div><span class="text-slate-500"><?= e(__('customers.label_phone')) ?>:</span> <?= e((string) $detail['phone']) ?></div><div><span class="text-slate-500"><?= e(__('customers.label_address')) ?>:</span> <?= e((string) $detail['address']) ?></div><div><span class="text-slate-500"><?= e(__('customers.label_registered')) ?>:</span> <?= e(format_date_pk((string) $detail['created_at'])) ?></div><div><span class="text-slate-500"><?= e(__('customers.notes')) ?>:</span> <?= e(trim((string) ($detail['notes'] ?? '')) !== '' ? (string) $detail['notes'] : (string) __('common.none')) ?></div>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('customers.order_history')) ?></h4><?php foreach ($detailOrders as $o): ?><p class="text-sm mb-1"><?= e(format_date_pk((string) $o['date'])) ?> — <?= e(service_type_label((string) $o['service_type'])) ?> (<?= (int) $o['quantity'] ?>) <?= e(format_currency((float) $o['amount'])) ?></p><?php endforeach; ?><?php if (!$detailOrders): ?><p class="text-sm text-slate-500"><?= e(__('customers.no_orders')) ?></p><?php endif; ?></div>
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2">Invoices</h4><?php foreach ($detailInvoices as $inv): $invoiceStatus = payment_status_from_amounts((float) ($inv['total_amount'] ?? 0), (float) ($inv['paid_amount'] ?? 0)); ?><p class="text-sm mb-1">INV-<?= (int) $inv['id'] ?> <?= e(format_currency((float) ($inv['total_amount'] ?? 0))) ?> | <?= e(payment_status_label($invoiceStatus)) ?> | <?= e(format_currency((float) ($inv['remaining_amount'] ?? 0))) ?></p><?php endforeach; ?><?php if (!$detailInvoices): ?><p class="text-sm text-slate-500">No invoices.</p><?php endif; ?></div>
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('customers.payment_history')) ?></h4><?php foreach ($detailPayments as $p): ?><p class="text-sm mb-1"><?= e(format_date_pk((string) $p['payment_date'])) ?> — INV-<?= (int) $p['invoice_id'] ?> <?= e(format_currency((float) $p['amount'])) ?></p><?php endforeach; ?><?php if (!$detailPayments): ?><p class="text-sm text-slate-500"><?= e(__('customers.no_payments')) ?></p><?php endif; ?></div>
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('customers.ledger')) ?></h4><?php foreach ($detailLedger as $l): ?><p class="text-sm mb-1"><?= e(format_date_pk((string) $l['date'])) ?> D:<?= e(format_currency((float) $l['debit'])) ?> C:<?= e(format_currency((float) $l['credit'])) ?> B:<?= e(format_currency((float) $l['balance'])) ?></p><?php endforeach; ?><?php if (!$detailLedger): ?><p class="text-sm text-slate-500"><?= e(__('customers.no_ledger')) ?></p><?php endif; ?></div>
    </div>
</section>
<?php endif; ?>
<script>
(() => {
    if (!window.OxygenFinance?.onUpdated) return;
    const detailId = <?= (int) $detailId ?>;
    window.OxygenFinance.onUpdated(async (payload) => {
        const cid = Number(payload?.customerId || 0);
        if (detailId > 0 && cid === detailId) {
            window.location.reload();
            return;
        }
        if (cid <= 0) return;
        const row = document.querySelector(`tr[data-customer-id="${cid}"]`);
        const cell = row?.querySelector('[data-customer-outstanding]');
        if (!cell) return;
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'customers');
            u.searchParams.set('ajax', 'customer_balance');
            u.searchParams.set('customer_id', String(cid));
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok || !data.balance) return;
            const rec = Number(data.balance.receivable ?? 0);
            cell.textContent = data.balance.receivable_formatted || '';
            cell.dataset.value = String(rec);
            cell.classList.toggle('text-warning', rec > 0.00001);
            cell.classList.toggle('font-medium', rec > 0.00001);
            cell.classList.toggle('text-primary', rec <= 0.00001);
        } catch (_) { /* ignore */ }
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.customers'), $content);
