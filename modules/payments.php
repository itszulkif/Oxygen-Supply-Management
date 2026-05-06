<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\OxygenOpsService;

$pdo = db();
$ops = new OxygenOpsService();
$invoices = $pdo->query("SELECT i.id, i.customer_id, c.name, i.total_amount, i.remaining_amount FROM invoices i INNER JOIN customers c ON c.id = i.customer_id ORDER BY i.id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceId = (int) request_value('invoice_id', '0');
    $amount = (float) request_value('amount', '0');
    $paymentDate = request_value('payment_date', date('Y-m-d'));
    if ($invoiceId > 0 && $amount > 0) {
        $ops->addPaymentWithAutomation([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ]);
    }
    header('Location: ?module=payments&toast=' . urlencode(__('payments.toast_recorded')) . i18n_lang_query());
    exit;
}

$paymentRows = $pdo->query(
    "SELECT p.payment_date, c.name AS customer_name, i.id AS order_id,
            (SELECT s2.service_type FROM services s2 WHERE s2.customer_id = c.id ORDER BY s2.date DESC, s2.id DESC LIMIT 1) AS service_type,
            p.amount, i.remaining_amount
     FROM payments p
     INNER JOIN invoices i ON i.id = p.invoice_id
     INNER JOIN customers c ON c.id = i.customer_id
     ORDER BY p.id DESC"
)->fetchAll();

ob_start();
?>
<section class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4 xl:col-span-1">
        <h3 class="font-semibold mb-3"><?= e(__('payments.record')) ?></h3>
        <form method="post" class="space-y-3" novalidate>
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <select name="invoice_id" required class="w-full border rounded-lg p-2">
                <option value=""><?= e(__('payments.select_invoice')) ?></option>
                <?php foreach ($invoices as $invoice): ?>
                    <option value="<?= (int) $invoice['id'] ?>">
                        INV-<?= (int) $invoice['id'] ?> - <?= e((string) $invoice['name']) ?> (<?= e(format_currency((float) $invoice['remaining_amount'])) ?> <?= e(__('payments.due_suffix')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input name="amount" type="number" step="0.01" min="0.01" required placeholder="<?= e(__('payments.amount')) ?>" class="w-full border rounded-lg p-2">
            <select name="method" class="w-full border rounded-lg p-2">
                <option><?= e(__('payments.method_cash')) ?></option>
                <option><?= e(__('payments.method_bank')) ?></option>
                <option><?= e(__('payments.method_credit')) ?></option>
            </select>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2">
            <textarea name="notes" class="w-full border rounded-lg p-2" placeholder="<?= e(__('payments.notes')) ?>"></textarea>
            <button class="w-full bg-success text-white rounded-lg py-2"><?= e(__('payments.save')) ?></button>
        </form>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden xl:col-span-2">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold"><?= e(__('payments.records')) ?></div>
        <div class="overflow-x-auto table-wrap">
        <table data-sortable="true" class="w-full text-sm min-w-[860px]">
            <thead class="bg-slate-50">
            <tr>
                <th data-sort class="text-start p-3 whitespace-nowrap"><?= e(__('common.date')) ?></th>
                <th data-sort class="text-start p-3 min-w-[120px]"><?= e(__('payments.col_customer')) ?></th>
                <th data-sort class="text-start p-3 whitespace-nowrap"><?= e(__('payments.col_order')) ?></th>
                <th data-sort class="text-start p-3"><?= e(__('payments.col_service')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap"><?= e(__('payments.col_amount')) ?></th>
                <th data-sort class="text-start p-3 whitespace-nowrap"><?= e(__('payments.col_method')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap"><?= e(__('payments.col_balance_after')) ?></th>
                <th data-sort class="text-start p-3"><?= e(__('payments.col_notes')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$paymentRows): ?>
                <tr><td colspan="8" class="p-4 text-slate-500"><?= e(__('payments.no_records')) ?> <?= e(__('payments.no_records_hint')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($paymentRows as $row): ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3 text-start align-middle whitespace-nowrap"><?= e(format_date_pk((string) $row['payment_date'])) ?></td>
                    <td class="p-3 text-start align-middle"><?= e((string) $row['customer_name']) ?></td>
                    <td class="p-3 text-start align-middle tabular-nums">ORD-<?= (int) $row['order_id'] ?></td>
                    <td class="p-3 text-start align-middle"><?= $row['service_type'] !== null && $row['service_type'] !== '' ? e(service_type_label((string) $row['service_type'])) : e(__('common.none')) ?></td>
                    <td data-value="<?= (float) $row['amount'] ?>" class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $row['amount'])) ?></td>
                    <td class="p-3 text-start align-middle"><?= e(__('payments.method_cash')) ?></td>
                    <td data-value="<?= (float) $row['remaining_amount'] ?>" class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
                    <td class="p-3 text-start align-middle"><?= e(__('common.none')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
render_layout(__('meta.payments'), $content);
