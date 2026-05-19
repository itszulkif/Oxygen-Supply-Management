<?php

declare(strict_types=1);

$supplierId = (int) ($_GET['id'] ?? $_POST['supplier_id'] ?? 0);
$langQ = i18n_lang_query();
$backUrl = '?module=suppliers' . $langQ;
$ledgerViewUrl = static function (array $extra = []) use ($supplierId, $langQ): string {
    $params = array_merge([
        'module' => 'suppliers',
        'action' => 'view_ledger',
        'id' => (string) $supplierId,
    ], $extra);
    if (i18n_locale() === 'ps') {
        $params['lang'] = 'ps';
    }
    return '?' . http_build_query($params);
};

if ($supplierId <= 0) {
    header('Location: ' . $backUrl);
    exit;
}

$renderA4Document = static function (string $title, string $bodyHtml): void {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>';
    echo '<style>
        @page { size: 80mm auto; margin: 4mm; }
        body { width: 72mm; margin: 0 auto; font-family: Arial, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.35; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 12px 0 6px; }
        .muted { color: #64748b; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 6px; margin-top: 6px; }
        .card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; text-align: start; }
        th { background: #f8fafc; }
        .text-right { text-align: end; }
    </style></head><body onload="window.print()">';
    echo $bodyHtml;
    echo '</body></html>';
};

$export = trim((string) ($_GET['export'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string) ($_POST['action'] ?? '')) === 'add_supplier_ledger_payment') {
    $paymentTarget = trim((string) ($_POST['payment_target'] ?? 'purchase'));
    $transactionId = (int) ($_POST['transaction_id'] ?? 0);
    $amount = max(0, (float) ($_POST['amount'] ?? 0));
    $paymentType = trim((string) ($_POST['payment_type'] ?? 'Cash'));
    $paymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
    $validPaymentTypes = ['Cash', 'Bank', 'Credit'];
    if (!in_array($paymentType, $validPaymentTypes, true)) {
        $paymentType = 'Cash';
    }
    if ($amount > 0) {
        $payOpening = $paymentTarget === 'opening';
        if ($payOpening) {
            $openingRemaining = supplier_opening_balance_remaining($pdo, $supplierId);
            if ($openingRemaining > 0.00001 && $amount <= $openingRemaining + 0.00001) {
                $pdo->prepare('INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, NULL, ?, ?, ?)')
                    ->execute([$supplierId, $amount, $paymentType, $paymentDate]);
                $rebuildSupplierLedger($pdo, $supplierId);
            }
        } elseif ($transactionId > 0) {
            $txStmt = $pdo->prepare('SELECT id, total_amount FROM supplier_transactions WHERE id = ? AND supplier_id = ? LIMIT 1');
            $txStmt->execute([$transactionId, $supplierId]);
            $tx = $txStmt->fetch();
            if ($tx) {
                $pdo->prepare('INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$supplierId, $transactionId, $amount, $paymentType, $paymentDate]);
                $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS paid_total FROM supplier_payments WHERE transaction_id = ?');
                $sumStmt->execute([$transactionId]);
                $paidTotal = (float) (($sumStmt->fetch()['paid_total'] ?? 0));
                $total = (float) ($tx['total_amount'] ?? 0);
                $remaining = max(0, $total - $paidTotal);
                $status = $paymentStatus($total, $paidTotal);
                $pdo->prepare('UPDATE supplier_transactions SET paid_amount = ?, remaining_amount = ?, payment_status = ? WHERE id = ?')
                    ->execute([$paidTotal, $remaining, $status, $transactionId]);
                $rebuildSupplierLedger($pdo, $supplierId);
            }
        }
    }
    header('Location: ' . $ledgerViewUrl(['msg' => 'payment_saved']));
    exit;
}

if ($export === 'supplier_detail_csv') {
    $st = $pdo->prepare('SELECT s.name, s.phone FROM suppliers s WHERE s.id = ?');
    $st->execute([$supplierId]);
    $supplierRow = $st->fetch() ?: ['name' => '', 'phone' => ''];
    $st = $pdo->prepare("SELECT t.transaction_date, t.id, t.quantity, t.total_received, t.inventory_quantity,
        t.total_amount, t.paid_amount, t.remaining_amount, t.payment_type, t.payment_status, t.notes
        FROM supplier_transactions t
        WHERE t.supplier_id = ?
        ORDER BY t.transaction_date ASC, t.id ASC");
    $st->execute([$supplierId]);
    $rows = $st->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=supplier-bills-' . $supplierId . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, [
        __('common.date'),
        __('suppliers.col_purchase_ref'),
        __('suppliers.col_supplier'),
        __('customers.col_phone'),
        __('suppliers.col_received_qty'),
        __('suppliers.col_total_price'),
        __('suppliers.col_paid'),
        __('suppliers.label_remaining_amount'),
        __('suppliers.label_payment_type'),
        __('suppliers.col_description'),
        __('common.status'),
    ]);
    foreach ($rows as $row) {
        $recvQty = supplier_transaction_received_qty($row);
        $notes = trim((string) ($row['notes'] ?? ''));
        fputcsv($out, [
            (string) ($row['transaction_date'] ?? ''),
            'SP-' . (int) ($row['id'] ?? 0),
            (string) ($supplierRow['name'] ?? ''),
            (string) ($supplierRow['phone'] ?? ''),
            (string) $recvQty,
            number_format((float) ($row['total_amount'] ?? 0), 2, '.', ''),
            number_format((float) ($row['paid_amount'] ?? 0), 2, '.', ''),
            number_format((float) ($row['remaining_amount'] ?? 0), 2, '.', ''),
            supplier_payment_type_label((string) ($row['payment_type'] ?? '')),
            $notes,
            (string) ($row['payment_status'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'supplier_detail_pdf' || $export === 'supplier_detail_print') {
    $st = $pdo->prepare('SELECT id, name, phone, contact_person, opening_balance FROM suppliers WHERE id = ?');
    $st->execute([$supplierId]);
    $detail = $st->fetch();
    if (!$detail) {
        exit(__('suppliers.err_supplier_not_found'));
    }
    $openingOwed = max(0, (float) ($detail['opening_balance'] ?? 0));
    $st = $pdo->prepare("SELECT t.id, t.transaction_date, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status,
        t.payment_type, t.notes, t.quantity, t.total_received, t.inventory_quantity
        FROM supplier_transactions t
        WHERE t.supplier_id = ?
        ORDER BY t.transaction_date DESC, t.id DESC");
    $st->execute([$supplierId]);
    $transactions = $st->fetchAll();
    $st = $pdo->prepare('SELECT payment_date, amount, transaction_id, payment_type FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC');
    $st->execute([$supplierId]);
    $payments = $st->fetchAll();
    $st = $pdo->prepare('SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE supplier_id = ? ORDER BY entry_date DESC, id DESC');
    $st->execute([$supplierId]);
    $ledgerRows = $st->fetchAll();

    $purchaseTotal = 0.0;
    $purchasePaid = 0.0;
    $purchaseRemaining = 0.0;
    foreach ($transactions as $tx) {
        $purchaseTotal += (float) ($tx['total_amount'] ?? 0);
        $purchasePaid += (float) ($tx['paid_amount'] ?? 0);
        $purchaseRemaining += (float) ($tx['remaining_amount'] ?? 0);
    }
    $totalOwed = supplier_amount_owed($openingOwed, $purchaseTotal, $purchasePaid);

    ob_start();
    ?>
    <h1><?= e(__('ledger.supplier_ledger_detail')) ?></h1>
    <div class="muted mb-10"><?= e(__('ledger.generated')) ?>: <?= e(date('Y-m-d H:i')) ?></div>
    <div class="grid mb-10">
        <div class="card"><strong><?= e(__('suppliers.col_supplier')) ?></strong><br><?= e((string) $detail['name']) ?></div>
        <div class="card"><strong><?= e(__('customers.label_phone')) ?></strong><br><?= e((string) ($detail['phone'] ?? '-')) ?></div>
        <div class="card"><strong><?= e(__('suppliers.col_contact')) ?></strong><br><?= e((string) ($detail['contact_person'] ?? '-')) ?></div>
    </div>
    <div class="grid mb-10">
        <div class="card"><strong><?= e(__('ledger.total_purchases')) ?></strong><br><?= e(format_currency($purchaseTotal)) ?></div>
        <div class="card"><strong><?= e(__('ledger.total_paid')) ?></strong><br><?= e(format_currency($purchasePaid)) ?></div>
        <div class="card"><strong><?= e(__('customers.col_outstanding')) ?></strong><br><?= e(format_currency($totalOwed)) ?></div>
        <?php if ($openingOwed > 0): ?>
        <div class="card"><strong><?= e(__('suppliers.opening_balance')) ?></strong><br><?= e(format_currency($openingOwed)) ?></div>
        <?php endif; ?>
    </div>
    <h2><?= e(__('ledger.transactions')) ?></h2>
    <table>
        <thead><tr>
            <th><?= e(__('common.date')) ?></th>
            <th><?= e(__('suppliers.col_purchase_ref')) ?></th>
            <th><?= e(__('suppliers.col_received_qty')) ?></th>
            <th><?= e(__('common.total')) ?></th>
            <th><?= e(__('common.paid')) ?></th>
            <th><?= e(__('common.remaining')) ?></th>
            <th><?= e(__('suppliers.label_payment_type')) ?></th>
            <th><?= e(__('suppliers.col_description')) ?></th>
            <th><?= e(__('common.status')) ?></th>
        </tr></thead>
        <tbody>
        <?php if (!$transactions): ?><tr><td colspan="9"><?= e(__('ledger.no_transactions')) ?></td></tr><?php endif; ?>
        <?php foreach ($transactions as $tx): ?>
            <?php
            $recvQty = supplier_transaction_received_qty($tx);
            $notes = trim((string) ($tx['notes'] ?? ''));
            $payType = supplier_payment_type_label((string) ($tx['payment_type'] ?? ''));
            ?>
            <tr>
                <td><?= e(format_date_pk((string) $tx['transaction_date'])) ?></td>
                <td>SP-<?= (int) $tx['id'] ?></td>
                <td class="text-right"><?= (int) $recvQty ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['total_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['paid_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['remaining_amount'])) ?></td>
                <td><?= e($payType) ?></td>
                <td><?= e($notes !== '' ? $notes : '—') ?></td>
                <td><?= e((string) ($tx['payment_status'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h2><?= e(__('ledger.payments')) ?></h2>
    <table>
        <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('suppliers.col_purchase_ref')) ?></th><th><?= e(__('payments.method')) ?></th><th><?= e(__('payments.amount')) ?></th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="4"><?= e(__('ledger.no_payments')) ?></td></tr><?php endif; ?>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(format_date_pk((string) $p['payment_date'])) ?></td>
                <td>SP-<?= (int) $p['transaction_id'] ?></td>
                <td><?= e((string) ($p['payment_type'] ?? '')) ?></td>
                <td class="text-right"><?= e(format_currency((float) $p['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h2><?= e(__('ledger.ledger_transactions')) ?></h2>
    <table>
        <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('ledger.col_desc')) ?></th><th><?= e(__('ledger.col_debit')) ?></th><th><?= e(__('ledger.col_credit')) ?></th><th><?= e(__('ledger.col_balance')) ?></th></tr></thead>
        <tbody>
        <?php if (!$ledgerRows): ?><tr><td colspan="5"><?= e(__('ledger.no_ledger_transactions')) ?></td></tr><?php endif; ?>
        <?php foreach ($ledgerRows as $l): ?>
            <tr>
                <td><?= e(format_date_pk((string) $l['entry_date'])) ?></td>
                <td><?= e((string) ($l['description'] ?? '—')) ?></td>
                <td class="text-right"><?= e(format_currency((float) $l['debit'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $l['credit'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $l['balance'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $body = (string) ob_get_clean();
    $renderA4Document('Supplier Ledger Detail', $body);
    exit;
}

$st = $pdo->prepare('SELECT id, name, phone, contact_person, opening_balance FROM suppliers WHERE id = ?');
$st->execute([$supplierId]);
$supplierDetail = $st->fetch() ?: null;
$supplierDetailTransactions = [];
$supplierDetailPayments = [];
$supplierDetailLedger = [];

if (!$supplierDetail) {
    header('Location: ' . $backUrl);
    exit;
}

$st = $pdo->prepare("SELECT t.id, t.transaction_date, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status,
    t.payment_type, t.notes, t.quantity, t.total_received, t.inventory_quantity
    FROM supplier_transactions t
    WHERE t.supplier_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC");
$st->execute([$supplierId]);
$supplierDetailTransactions = $st->fetchAll();
$st = $pdo->prepare('SELECT payment_date, amount, transaction_id, payment_type FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC');
$st->execute([$supplierId]);
$supplierDetailPayments = $st->fetchAll();
$st = $pdo->prepare('SELECT entry_date, description, debit, credit, balance, reference_type FROM supplier_ledger WHERE supplier_id = ? ORDER BY entry_date ASC, id ASC');
$st->execute([$supplierId]);
$supplierDetailLedger = $st->fetchAll();
$supplierLedgerBalance = 0.0;
if ($supplierDetailLedger !== []) {
    $lastLedger = $supplierDetailLedger[count($supplierDetailLedger) - 1];
    $supplierLedgerBalance = (float) ($lastLedger['balance'] ?? 0);
}
$openingBalanceOriginal = max(0.0, (float) ($supplierDetail['opening_balance'] ?? 0));
$openingBalanceRemaining = supplier_opening_balance_remaining($pdo, $supplierId);
$purchaseTotal = 0.0;
$purchasePaid = 0.0;
foreach ($supplierDetailTransactions as $txRow) {
    $purchaseTotal += (float) ($txRow['total_amount'] ?? 0);
    $purchasePaid += (float) ($txRow['paid_amount'] ?? 0);
}
$totalLiabilityOwed = max(0.0, $supplierLedgerBalance);
$flashLedgerMsg = match ((string) ($_GET['msg'] ?? '')) {
    'payment_saved' => __('suppliers.msg_payment_saved'),
    default => '',
};

$paymentsByTx = [];
foreach ($supplierDetailPayments as $payRow) {
    $txKey = (int) ($payRow['transaction_id'] ?? 0);
    if (!isset($paymentsByTx[$txKey])) {
        $paymentsByTx[$txKey] = [];
    }
    $paymentsByTx[$txKey][] = $payRow;
}

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="font-semibold text-lg"><?= e(__('ledger.supplier_view')) ?>: <?= e((string) $supplierDetail['name']) ?></h3>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-soft" href="<?= e($backUrl) ?>"><?= e(__('ledger.back_to_suppliers')) ?></a>
            <a class="btn btn-soft" href="<?= e($ledgerViewUrl(['export' => 'supplier_detail_csv'])) ?>"><?= e(__('common.export_csv')) ?></a>
            <a class="btn btn-soft" target="_blank" rel="noopener" href="<?= e($ledgerViewUrl(['export' => 'supplier_detail_print'])) ?>"><?= e(__('ledger.print')) ?></a>
            <button type="button" class="btn btn-primary" data-open-modal="supplierDuePaymentModal"><?= e(__('ledger.pay_payment')) ?></button>
        </div>
    </div>
    <?php if ($flashLedgerMsg !== ''): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 mb-4" role="status"><?= e($flashLedgerMsg) ?></div>
    <?php endif; ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800"><?= e(__('ledger.supplier_liability_total')) ?></p>
            <p class="text-xl font-bold text-amber-900 mt-1 tabular-nums"><?= e(format_currency($totalLiabilityOwed)) ?></p>
            <p class="text-xs text-amber-700/90 mt-1"><?= e(__('ledger.supplier_liability_total_sub')) ?></p>
        </div>
        <?php if ($openingBalanceOriginal > 0): ?>
        <div class="rounded-xl border border-sky-200 bg-sky-50/80 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-800"><?= e(__('suppliers.opening_balance')) ?></p>
            <p class="text-xl font-bold text-sky-900 mt-1 tabular-nums"><?= e(format_currency($openingBalanceRemaining)) ?></p>
            <p class="text-xs text-sky-700/90 mt-1"><?= e(__('ledger.opening_liability_sub', ['original' => format_currency($openingBalanceOriginal)])) ?></p>
        </div>
        <?php endif; ?>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600"><?= e(__('ledger.total_purchases')) ?></p>
            <p class="text-xl font-bold text-slate-900 mt-1 tabular-nums"><?= e(format_currency($purchaseTotal)) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600"><?= e(__('ledger.total_paid')) ?></p>
            <p class="text-xl font-bold text-emerald-800 mt-1 tabular-nums"><?= e(format_currency($purchasePaid + supplier_opening_payments_paid($pdo, $supplierId))) ?></p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm mb-4">
        <div><span class="text-slate-500"><?= e(__('customers.label_phone')) ?>:</span> <?= e((string) ($supplierDetail['phone'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('suppliers.col_contact')) ?>:</span> <?= e((string) ($supplierDetail['contact_person'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_transactions')) ?>:</span> <?= count($supplierDetailTransactions) ?></div>
        <div><span class="text-slate-500"><?= e(__('ledger.col_balance_owed_supplier')) ?>:</span> <strong class="<?= $supplierLedgerBalance > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= e(format_currency($supplierLedgerBalance)) ?></strong></div>
    </div>

    <h4 class="font-semibold mb-2"><?= e(__('ledger.ledger_transactions')) ?></h4>
    <div class="overflow-x-auto table-wrap mb-6">
        <table class="w-full text-sm min-w-[640px]">
            <thead class="bg-slate-50">
            <tr>
                <th class="text-start p-3"><?= e(__('common.date')) ?></th>
                <th class="text-start p-3"><?= e(__('ledger.col_desc')) ?></th>
                <th class="text-end p-3"><?= e(__('ledger.col_debit')) ?></th>
                <th class="text-end p-3"><?= e(__('ledger.col_credit')) ?></th>
                <th class="text-end p-3"><?= e(__('ledger.col_balance')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$supplierDetailLedger): ?>
                <tr><td colspan="5" class="p-3 text-slate-500"><?= e(__('ledger.no_ledger_transactions')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($supplierDetailLedger as $ledgerRow): ?>
                <?php $isOpeningLiability = ($ledgerRow['reference_type'] ?? '') === 'opening_balance'; ?>
                <tr class="border-t border-slate-100<?= $isOpeningLiability ? ' bg-sky-50/60' : '' ?>">
                    <td class="p-3 whitespace-nowrap"><?= e(format_date_pk((string) $ledgerRow['entry_date'])) ?></td>
                    <td class="p-3">
                        <?php if ($isOpeningLiability): ?>
                            <span class="inline-flex rounded-full bg-sky-100 text-sky-900 text-xs font-medium px-2 py-0.5 mb-1"><?= e(__('ledger.opening_liability_badge')) ?></span><br>
                        <?php endif; ?>
                        <?= e(trim((string) ($ledgerRow['description'] ?? '')) !== '' ? trim((string) $ledgerRow['description']) : '—') ?>
                    </td>
                    <td class="p-3 text-end tabular-nums"><?= e(format_currency((float) ($ledgerRow['debit'] ?? 0))) ?></td>
                    <td class="p-3 text-end tabular-nums"><?= e(format_currency((float) ($ledgerRow['credit'] ?? 0))) ?></td>
                    <td class="p-3 text-end tabular-nums font-medium"><?= e(format_currency((float) ($ledgerRow['balance'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4 class="font-semibold mb-3"><?= e(__('ledger.transactions')) ?></h4>
    <div class="space-y-4">
        <?php foreach ($supplierDetailTransactions as $tx): ?>
            <?php
            $txId = (int) ($tx['id'] ?? 0);
            $recvQty = supplier_transaction_received_qty($tx);
            $remaining = (float) ($tx['remaining_amount'] ?? 0);
            $paid = (float) ($tx['paid_amount'] ?? 0);
            $statusKey = supplier_bill_status_key($remaining, $paid);
            $statusLabel = match ($statusKey) {
                'paid' => __('status.paid'),
                'partial' => __('suppliers.status_partial'),
                default => __('status.due'),
            };
            $notes = trim((string) ($tx['notes'] ?? ''));
            $payTypeLabel = supplier_payment_type_label((string) ($tx['payment_type'] ?? ''));
            $txPayments = $paymentsByTx[$txId] ?? [];
            ?>
            <div class="border rounded-lg p-3">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <h4 class="font-semibold text-oxygenDeep"><?= e(__('ledger.bill')) ?> SP-<?= $txId ?></h4>
                        <p class="text-xs text-slate-500"><?= e(format_date_pk((string) $tx['transaction_date'])) ?></p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs <?= $statusKey === 'paid' ? 'status-paid' : ($statusKey === 'partial' ? 'status-partial' : 'status-due') ?>"><?= e($statusLabel) ?></span>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-3 gap-y-2 text-sm mb-3">
                    <div><dt class="text-slate-500 text-xs"><?= e(__('suppliers.col_received_qty')) ?></dt><dd class="font-medium tabular-nums"><?= (int) $recvQty ?></dd></div>
                    <div><dt class="text-slate-500 text-xs"><?= e(__('suppliers.col_total_price')) ?></dt><dd class="font-medium tabular-nums"><?= e(format_currency((float) $tx['total_amount'])) ?></dd></div>
                    <div><dt class="text-slate-500 text-xs"><?= e(__('common.paid')) ?></dt><dd class="font-medium tabular-nums"><?= e(format_currency($paid)) ?></dd></div>
                    <div><dt class="text-slate-500 text-xs"><?= e(__('common.remaining')) ?></dt><dd class="font-medium tabular-nums <?= $remaining > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= e(format_currency($remaining)) ?></dd></div>
                    <div class="col-span-2 sm:col-span-1"><dt class="text-slate-500 text-xs"><?= e(__('suppliers.label_payment_type')) ?></dt><dd class="font-medium"><?= e($payTypeLabel) ?></dd></div>
                </dl>
                <?php if ($notes !== ''): ?>
                    <p class="text-sm text-slate-700 border-t border-slate-100 pt-2"><span class="text-slate-500 text-xs block mb-0.5"><?= e(__('suppliers.col_description')) ?></span><?= e($notes) ?></p>
                <?php endif; ?>
                <?php if ($txPayments): ?>
                    <div class="mt-3 border-t border-slate-100 pt-2">
                        <p class="font-medium text-sm mb-1"><?= e(__('ledger.payments_on_bill')) ?></p>
                        <?php foreach ($txPayments as $paymentRow): ?>
                            <p class="text-sm text-slate-700 mb-0.5"><?= e(format_date_pk((string) $paymentRow['payment_date'])) ?> · <?= e(format_currency((float) $paymentRow['amount'])) ?> · <?= e(supplier_payment_type_label((string) ($paymentRow['payment_type'] ?? ''))) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$supplierDetailTransactions): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_transactions')) ?></p><?php endif; ?>
    </div>
</section>

<div id="supplierDuePaymentModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-modal="supplierDuePaymentModal"></div>
    <div class="relative w-full max-w-2xl bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="font-semibold"><?= e(__('ledger.pay_payment')) ?></h3>
            <button type="button" class="btn btn-soft" data-close-modal="supplierDuePaymentModal"><?= e(__('common.close')) ?></button>
        </div>
        <form method="post" action="<?= e($ledgerViewUrl()) ?>" class="grid grid-cols-1 gap-3">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="add_supplier_ledger_payment">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierDetail['id'] ?>">
            <label class="block text-xs font-medium text-slate-600"><?= e(__('ledger.pay_against')) ?></label>
            <select name="payment_target" id="supplierPaymentTarget" class="border rounded-lg px-3 py-2 text-sm w-full">
                <?php if ($openingBalanceRemaining > 0.00001): ?>
                <option value="opening"><?= e(__('ledger.pay_opening_balance', ['amount' => format_currency($openingBalanceRemaining)])) ?></option>
                <?php endif; ?>
                <?php foreach ($supplierDetailTransactions as $tx): if ((float) ($tx['remaining_amount'] ?? 0) <= 0.00001) continue; ?>
                    <option value="purchase" data-tx-id="<?= (int) $tx['id'] ?>">SP-<?= (int) $tx['id'] ?> (<?= e(__('status.due')) ?>: <?= e(format_currency((float) $tx['remaining_amount'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="transaction_id" id="supplierPaymentTxId" value="">
            <input type="number" name="amount" step="0.01" min="0.01" required class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('payments.amount')) ?>">
            <select name="payment_type" class="border rounded-lg px-3 py-2 text-sm">
                <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                <option value="Credit"><?= e(__('suppliers.pay_credit')) ?></option>
            </select>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button class="bg-primary text-white rounded-lg px-3 py-2 text-sm w-full"><?= e(__('ledger.pay_payment_btn')) ?></button>
        </form>
    </div>
</div>
<script>
(() => {
    const target = document.getElementById('supplierPaymentTarget');
    const txField = document.getElementById('supplierPaymentTxId');
    if (!target || !txField) return;
    const sync = () => {
        const opt = target.options[target.selectedIndex];
        txField.value = opt && opt.value === 'purchase' ? (opt.getAttribute('data-tx-id') || '') : '';
    };
    target.addEventListener('change', sync);
    sync();
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = __('ledger.supplier_view') . ' — ' . (string) $supplierDetail['name'];
render_layout($pageTitle, $content);
