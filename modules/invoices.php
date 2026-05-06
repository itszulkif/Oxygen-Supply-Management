<?php

$pdo = db();
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$viewInvoiceId = (int) ($_GET['view'] ?? 0);
$remindInvoiceId = (int) ($_GET['remind'] ?? 0);

if ($remindInvoiceId > 0 && table_exists($pdo, 'reminders')) {
    $reminderFetch = $pdo->prepare(
        "SELECT i.id, i.customer_id, c.phone, i.remaining_amount
         FROM invoices i
         INNER JOIN customers c ON c.id = i.customer_id
         WHERE i.id = ?"
    );
    $reminderFetch->execute([$remindInvoiceId]);
    $reminderTarget = $reminderFetch->fetch();
    if ($reminderTarget) {
        $message = 'Friendly reminder: Invoice INV-' . (int) $reminderTarget['id'] . ' has pending balance ' . number_format((float) $reminderTarget['remaining_amount'], 2);
        $ins = $pdo->prepare('INSERT INTO reminders (invoice_id, customer_id, channel, message) VALUES (?, ?, ?, ?)');
        $ins->execute([(int) $reminderTarget['id'], (int) $reminderTarget['customer_id'], 'whatsapp', $message]);
    }
    redirect_to('invoices');
}

$sql = "SELECT i.*, c.name, c.phone
        FROM invoices i
        INNER JOIN customers c ON c.id = i.customer_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR i.id LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$selectedInvoice = null;
if ($viewInvoiceId > 0) {
    $v = $pdo->prepare("SELECT i.*, c.name, c.phone, c.address FROM invoices i INNER JOIN customers c ON c.id = i.customer_id WHERE i.id = ?");
    $v->execute([$viewInvoiceId]);
    $selectedInvoice = $v->fetch();
}

$pendingReminders = $pdo->query(
    "SELECT i.id, c.name, c.phone, i.remaining_amount
     FROM invoices i
     INNER JOIN customers c ON c.id = i.customer_id
     WHERE i.remaining_amount > 0
     ORDER BY i.id DESC
     LIMIT 10"
)->fetchAll();

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
    <div class="p-4 border-b border-slate-200">
        <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
            <input type="hidden" name="module" value="invoices">
            <input name="search" value="<?= e($search) ?>" placeholder="Search customer / phone / invoice ID" class="border rounded-lg px-3 py-2 text-sm">
            <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
            </select>
            <button class="bg-sky-600 text-white rounded-lg px-3 py-2 text-sm w-full">Filter</button>
            <a href="?module=invoices" class="bg-slate-100 text-slate-700 rounded-lg px-3 py-2 text-sm text-center">Reset</a>
        </form>
    </div>
    <div class="overflow-x-auto">
    <table data-sortable="true" class="w-full text-sm min-w-[820px] lg:min-w-[980px]">
        <thead class="bg-slate-50">
            <tr>
                <th data-sort class="text-left p-3">Invoice #</th>
                <th data-sort class="text-left p-3">Customer</th>
                <th data-sort class="text-left p-3">Total</th>
                <th data-sort class="text-left p-3">Paid</th>
                <th data-sort class="text-left p-3">Balance Due</th>
                <th data-sort class="text-left p-3">Status</th>
                <th class="text-left p-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-slate-500">No invoices found.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $invoiceLabel = 'AOS-' . date('Y') . '-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
                $whatsAppText = rawurlencode(
                    'Invoice ' . $invoiceLabel .
                    ' | Total: ' . number_format((float) $row['total_amount'], 2) .
                    ' | Remaining: ' . number_format((float) $row['remaining_amount'], 2)
                );
                $waLink = 'https://wa.me/' . preg_replace('/\D+/', '', (string) $row['phone']) . '?text=' . $whatsAppText;
                ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3"><?= e($invoiceLabel) ?></td>
                    <td class="p-3"><?= e((string) $row['name']) ?></td>
                    <td data-value="<?= (float) $row['total_amount'] ?>" class="p-3"><?= e(format_currency((float) $row['total_amount'])) ?></td>
                    <td data-value="<?= (float) $row['paid_amount'] ?>" class="p-3"><?= e(format_currency((float) $row['paid_amount'])) ?></td>
                    <td data-value="<?= (float) $row['remaining_amount'] ?>" class="p-3"><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
                    <td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= strtolower((string) $row['status']) === 'paid' ? 'status-paid' : 'status-due' ?>"><?= e((string) $row['status']) ?></span></td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-2">
                            <a href="?module=invoices&view=<?= (int) $row['id'] ?>" class="inline-flex rounded-lg bg-sky-100 px-3 py-1 text-sky-700 text-xs font-medium">View</a>
                            <button type="button" data-invoice="<?= (int) $row['id'] ?>" data-total="<?= (float) $row['total_amount'] ?>" data-customer="<?= e((string) $row['name']) ?>" class="download-pdf inline-flex rounded-lg bg-emerald-100 px-3 py-1 text-emerald-700 text-xs font-medium">Download PDF</button>
                            <a target="_blank" href="<?= e($waLink) ?>" class="inline-flex rounded-lg bg-green-100 px-3 py-1 text-green-700 text-xs font-medium">WhatsApp</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <?php if ($selectedInvoice): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-2">Invoice Preview: AOS-<?= date('Y') ?>-<?= str_pad((string) $selectedInvoice['id'], 3, '0', STR_PAD_LEFT) ?></h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            <p><span class="text-slate-500">Customer:</span> <?= e((string) $selectedInvoice['name']) ?></p>
            <p><span class="text-slate-500">Phone:</span> <?= e((string) $selectedInvoice['phone']) ?></p>
            <p><span class="text-slate-500">Status:</span> <?= e((string) $selectedInvoice['status']) ?></p>
            <p><span class="text-slate-500">Date:</span> <?= e(format_date_pk((string) $selectedInvoice['created_at'])) ?></p>
            <p><span class="text-slate-500">Subtotal:</span> <?= e(format_currency((float) $selectedInvoice['total_amount'])) ?></p>
            <p><span class="text-slate-500">Amount Paid:</span> <?= e(format_currency((float) $selectedInvoice['paid_amount'])) ?></p>
            <p><span class="text-slate-500">Balance Due:</span> <?= e(format_currency((float) $selectedInvoice['remaining_amount'])) ?></p>
        </div>
        <div class="mt-3 flex gap-2"><button onclick="window.print()" class="rounded-lg bg-primary px-3 py-2 text-sm text-white">Print</button></div>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Payment Reminder Queue</div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[520px]">
            <thead class="bg-slate-50">
                <tr><th class="text-left p-3">Invoice</th><th class="text-left p-3">Customer</th><th class="text-left p-3">Remaining</th><th class="text-left p-3">Reminder</th></tr>
            </thead>
            <tbody>
                <?php if (!$pendingReminders): ?><tr><td colspan="4" class="p-4 text-slate-500">No reminders pending.</td></tr><?php endif; ?>
                <?php foreach ($pendingReminders as $r): ?>
                    <?php
                    $msg = rawurlencode('Friendly reminder: Invoice INV-' . (int) $r['id'] . ' has pending balance ' . number_format((float) $r['remaining_amount'], 2));
                    $reminderLink = 'https://wa.me/' . preg_replace('/\D+/', '', (string) $r['phone']) . '?text=' . $msg;
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="p-3">INV-<?= (int) $r['id'] ?></td>
                        <td class="p-3"><?= e((string) $r['name']) ?></td>
                        <td class="p-3"><?= number_format((float) $r['remaining_amount'], 2) ?></td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <a target="_blank" href="<?= e($reminderLink) ?>" class="inline-flex rounded-lg bg-amber-100 px-3 py-1 text-amber-700 text-xs font-medium">Send Reminder</a>
                                <?php if (table_exists($pdo, 'reminders')): ?>
                                    <a href="?module=invoices&remind=<?= (int) $r['id'] ?>" class="inline-flex rounded-lg bg-slate-100 px-3 py-1 text-slate-700 text-xs font-medium">Log</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
document.querySelectorAll('.download-pdf').forEach((btn) => {
    btn.addEventListener('click', () => {
        const currencySym = <?= json_encode(__('currency.symbol'), JSON_UNESCAPED_UNICODE) ?>;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const id = btn.dataset.invoice;
        doc.text('Afghan Oxygen Supply', 14, 15);
        doc.text('Invoice: AOS-' + new Date().getFullYear() + '-' + String(id).padStart(3, '0'), 14, 24);
        doc.text('Customer: ' + btn.dataset.customer, 14, 33);
        doc.text('Grand Total: ' + currencySym + ' ' + Number(btn.dataset.total || 0).toFixed(2), 14, 42);
        doc.save('invoice-' + id + '.pdf');
    });
});
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.invoices'), $content);
