<?php

require_once __DIR__ . '/../app/bootstrap.php';
use App\Services\OxygenOpsService;

$pdo = db();

// Balance JSON for services.php (expects customer_id)
if (($_GET['ajax'] ?? '') === 'balance') {
    $cid = (int) ($_GET['customer_id'] ?? 0);
    $balance = 0.0;
    if ($cid > 0) {
        $st = $pdo->prepare('SELECT debit, credit FROM ledger WHERE customer_id = ?');
        $st->execute([$cid]);
        foreach ($st->fetchAll() as $r) {
            $balance += (float) $r['debit'] - (float) $r['credit'];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'balance' => $balance], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['ajax'] ?? '') === 'search_customers') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode(['ok' => true, 'customers' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $like = '%' . $q . '%';
    $st = $pdo->prepare('SELECT id, name, phone FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC LIMIT 20');
    $st->execute([$like, $like]);
    echo json_encode(['ok' => true, 'customers' => $st->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

$customerSearch = trim((string) ($_GET['q'] ?? ''));
$supplierSearch = trim((string) ($_GET['supplier_q'] ?? ''));
$entity = trim((string) ($_GET['entity'] ?? 'customer'));
$export = trim((string) ($_GET['export'] ?? ''));
$viewCustomerId = (int) ($_GET['view_customer'] ?? 0);
$viewSupplierId = (int) ($_GET['view_supplier'] ?? 0);
if (!in_array($entity, ['customer', 'supplier'], true)) {
    $entity = 'customer';
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
        th, td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; text-align: left; }
        th { background: #f8fafc; }
        .text-right { text-align: right; }
        .mb-10 { margin-bottom: 10px; }
    </style></head><body>';
    echo $bodyHtml;
    echo '</body></html>';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) request_value('action'));
    if ($action === 'add_customer_due_payment') {
        $invoiceId = (int) request_value('invoice_id', '0');
        $amount = (float) request_value('amount', '0');
        $paymentDate = request_value('payment_date', date('Y-m-d'));
        if ($invoiceId > 0 && $amount > 0) {
            $ops = new OxygenOpsService();
            $ops->addPaymentWithAutomation([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
            ]);
        }
        $redirectCustomerId = (int) request_value('customer_id', '0');
        header('Location: ?module=ledger&entity=customer&view_customer=' . $redirectCustomerId . i18n_lang_query());
        exit;
    }
    if ($action === 'add_supplier_due_payment') {
        $supplierId = (int) request_value('supplier_id', '0');
        $transactionId = (int) request_value('transaction_id', '0');
        $amount = (float) request_value('amount', '0');
        $paymentType = request_value('payment_type', 'Cash');
        $paymentDate = request_value('payment_date', date('Y-m-d'));
        if ($supplierId > 0 && $transactionId > 0 && $amount > 0) {
            $pdo->prepare("INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, ?, ?, ?, ?)")
                ->execute([$supplierId, $transactionId, $amount, $paymentType, $paymentDate]);
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE transaction_id = ?");
            $sumStmt->execute([$transactionId]);
            $paidTotal = (float) $sumStmt->fetchColumn();
            $txStmt = $pdo->prepare("SELECT total_amount FROM supplier_transactions WHERE id = ?");
            $txStmt->execute([$transactionId]);
            $total = (float) $txStmt->fetchColumn();
            $remaining = max(0, $total - $paidTotal);
            $status = $remaining <= 0.00001 ? 'PAID' : ($paidTotal > 0 ? 'PARTIAL' : 'DUE');
            $pdo->prepare("UPDATE supplier_transactions SET paid_amount = ?, remaining_amount = ?, payment_status = ? WHERE id = ?")
                ->execute([$paidTotal, $remaining, $status, $transactionId]);

            $balStmt = $pdo->prepare("SELECT balance FROM supplier_ledger WHERE supplier_id = ? ORDER BY id DESC LIMIT 1");
            $balStmt->execute([$supplierId]);
            $lastBalance = (float) ($balStmt->fetchColumn() ?: 0);
            $newBalance = $lastBalance - $amount;
            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, debit, credit, balance, reference_type, reference_id, description, entry_date) VALUES (?, 0, ?, ?, 'payment', ?, ?, ?)")
                ->execute([$supplierId, $amount, $newBalance, $transactionId, 'Payment received via ledger view', $paymentDate]);
        }
        header('Location: ?module=ledger&entity=supplier&view_supplier=' . $supplierId . i18n_lang_query());
        exit;
    }
}

$customerLedgerRows = [];
$supplierLedgerRows = [];
$customerSummary = ['debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0, 'due' => 0.0, 'remaining' => 0.0];
$supplierSummary = ['debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0, 'due' => 0.0, 'remaining' => 0.0];
$customerDueMap = [];
$supplierDueMap = [];
$matchedCustomerCount = 0;
$matchedSupplierCount = 0;

$customerIds = [];
if ($entity === 'customer') {
    if ($customerSearch !== '') {
        $like = '%' . $customerSearch . '%';
        $idsSt = $pdo->prepare('SELECT id FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC');
        $idsSt->execute([$like, $like]);
    } else {
        $idsSt = $pdo->query('SELECT id FROM customers ORDER BY name ASC');
    }
    $customerIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, array_column($idsSt->fetchAll(), 'id'))));
    $ids = $customerIds;
    $matchedCustomerCount = count($ids);
    if ($ids !== []) {
        $invoiceTotalExpr = column_exists($pdo, 'invoices', 'total_amount') ? 'COALESCE(total_amount, 0)' : 'COALESCE(grand_total, 0)';
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $dueSt = $pdo->prepare("SELECT customer_id, COALESCE(SUM({$invoiceTotalExpr}),0) AS due_payment, COALESCE(SUM(remaining_amount),0) AS remaining_balance FROM invoices WHERE customer_id IN ($ph) GROUP BY customer_id");
        $dueSt->execute($ids);
        foreach ($dueSt->fetchAll() as $row) {
            $cid = (int) $row['customer_id'];
            $customerDueMap[$cid] = [
                'due_payment' => (float) $row['due_payment'],
                'remaining_balance' => (float) $row['remaining_balance'],
            ];
            $customerSummary['due'] += (float) $row['due_payment'];
            $customerSummary['remaining'] += (float) $row['remaining_balance'];
        }

        if ($customerSearch !== '') {
            $ledgerCols = 'l.customer_id, l.date, l.debit, l.credit, l.balance';
            if (column_exists($pdo, 'ledger', 'description')) {
                $ledgerCols .= ', l.description';
            }
            if (column_exists($pdo, 'ledger', 'cylinders_sent')) {
                $ledgerCols .= ', l.cylinders_sent, l.cylinders_received, l.cylinders_baqi';
            }
            $sql = "SELECT $ledgerCols, c.name AS customer_name, c.phone AS customer_phone
                    FROM ledger l
                    INNER JOIN customers c ON c.id = l.customer_id
                    WHERE l.customer_id IN ($ph)
                    ORDER BY l.date ASC, l.id ASC";
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $customerLedgerRows = $st->fetchAll();
            foreach ($customerLedgerRows as $row) {
                $customerSummary['debit'] += (float) $row['debit'];
                $customerSummary['credit'] += (float) $row['credit'];
            }
            $customerSummary['balance'] = $customerSummary['debit'] - $customerSummary['credit'];
        } else {
            $sql = "SELECT c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                    COALESCE((SELECT l2.date FROM ledger l2 WHERE l2.customer_id = c.id ORDER BY l2.id DESC LIMIT 1), '') AS date,
                    COALESCE((SELECT SUM(l2.debit) FROM ledger l2 WHERE l2.customer_id = c.id), 0) AS debit,
                    COALESCE((SELECT SUM(l2.credit) FROM ledger l2 WHERE l2.customer_id = c.id), 0) AS credit,
                    COALESCE((SELECT l2.balance FROM ledger l2 WHERE l2.customer_id = c.id ORDER BY l2.id DESC LIMIT 1), 0) AS balance,
                    COALESCE((SELECT l2.description FROM ledger l2 WHERE l2.customer_id = c.id ORDER BY l2.id DESC LIMIT 1), '') AS description,
                    0 AS cylinders_sent, 0 AS cylinders_received, 0 AS cylinders_baqi
                FROM customers c
                WHERE c.id IN ($ph)
                ORDER BY c.name ASC";
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $customerLedgerRows = $st->fetchAll();
            foreach ($customerLedgerRows as $row) {
                $customerSummary['debit'] += (float) ($row['debit'] ?? 0);
                $customerSummary['credit'] += (float) ($row['credit'] ?? 0);
            }
            $customerSummary['balance'] = $customerSummary['debit'] - $customerSummary['credit'];
        }
    }
}

$supplierIds = [];
if ($entity === 'supplier') {
    if ($supplierSearch !== '') {
        $like = '%' . $supplierSearch . '%';
        $idsSt = $pdo->prepare('SELECT id FROM suppliers WHERE name LIKE ? OR phone LIKE ? OR contact_person LIKE ? ORDER BY name ASC');
        $idsSt->execute([$like, $like, $like]);
    } else {
        $idsSt = $pdo->query('SELECT id FROM suppliers ORDER BY name ASC');
    }
    $supplierIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, array_column($idsSt->fetchAll(), 'id'))));
    $ids = $supplierIds;
    $matchedSupplierCount = count($ids);
    if ($ids !== []) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $dueSt = $pdo->prepare("SELECT supplier_id, COALESCE(SUM(total_amount),0) AS due_payment, COALESCE(SUM(remaining_amount),0) AS remaining_balance FROM supplier_transactions WHERE supplier_id IN ($ph) GROUP BY supplier_id");
        $dueSt->execute($ids);
        foreach ($dueSt->fetchAll() as $row) {
            $sid = (int) $row['supplier_id'];
            $supplierDueMap[$sid] = [
                'due_payment' => (float) $row['due_payment'],
                'remaining_balance' => (float) $row['remaining_balance'],
            ];
            $supplierSummary['due'] += (float) $row['due_payment'];
            $supplierSummary['remaining'] += (float) $row['remaining_balance'];
        }

        $st = $pdo->prepare("SELECT s.id AS supplier_id, s.name AS supplier_name, s.phone AS supplier_phone,
                COALESCE(MAX(t.transaction_date), '') AS last_transaction_date,
                COALESCE(SUM(t.total_amount), 0) AS total_due,
                COALESCE(SUM(t.paid_amount), 0) AS total_paid,
                COALESCE(SUM(t.remaining_amount), 0) AS remaining_balance
            FROM suppliers s
            LEFT JOIN supplier_transactions t ON t.supplier_id = s.id
            WHERE s.id IN ($ph)
            GROUP BY s.id, s.name, s.phone
            ORDER BY s.name ASC");
        $st->execute($ids);
        $supplierLedgerRows = $st->fetchAll();
        foreach ($supplierLedgerRows as $row) {
            $supplierSummary['debit'] += (float) ($row['total_due'] ?? 0);
            $supplierSummary['credit'] += (float) ($row['total_paid'] ?? 0);
        }
        $supplierSummary['balance'] = $supplierSummary['debit'] - $supplierSummary['credit'];
    }
}

if ($export === 'customer_csv') {
    $invoiceTotalExpr = column_exists($pdo, 'invoices', 'total_amount') ? 'COALESCE(total_amount, 0)' : 'COALESCE(grand_total, 0)';
    $dueMap = [];
    $dueSt = $pdo->query("SELECT customer_id, COALESCE(SUM({$invoiceTotalExpr}),0) AS due_payment, COALESCE(SUM(remaining_amount),0) AS remaining_balance FROM invoices GROUP BY customer_id");
    foreach ($dueSt->fetchAll() as $row) {
        $dueMap[(int) $row['customer_id']] = [
            'due_payment' => (float) $row['due_payment'],
            'remaining_balance' => (float) $row['remaining_balance'],
        ];
    }

    $ledgerCols = 'l.customer_id, l.date, l.debit, l.credit, l.balance';
    if (column_exists($pdo, 'ledger', 'description')) {
        $ledgerCols .= ', l.description';
    }
    if (column_exists($pdo, 'ledger', 'cylinders_sent')) {
        $ledgerCols .= ', l.cylinders_sent, l.cylinders_received, l.cylinders_baqi';
    }
    $rows = $pdo->query("SELECT $ledgerCols, c.name AS customer_name, c.phone AS customer_phone
        FROM ledger l
        INNER JOIN customers c ON c.id = l.customer_id
        ORDER BY c.name ASC, l.date ASC, l.id ASC")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer-ledger-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Date', 'Customer', 'Phone', 'Description', 'Cylinders (S/R/B)', 'Debit', 'Credit', 'Balance', 'Due Payment', 'Remaining Balance']);
    foreach ($rows as $row) {
        $due = $dueMap[(int) ($row['customer_id'] ?? 0)] ?? ['due_payment' => 0.0, 'remaining_balance' => 0.0];
        $desc = trim((string) ($row['description'] ?? ''));
        fputcsv($out, [
            (string) ($row['date'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_phone'] ?? ''),
            $desc !== '' ? $desc : (string) __('common.none'),
            (string) (($row['cylinders_sent'] ?? 0) . '/' . ($row['cylinders_received'] ?? 0) . '/' . ($row['cylinders_baqi'] ?? 0)),
            number_format((float) ($row['debit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['credit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['balance'] ?? 0), 2, '.', ''),
            number_format((float) ($due['due_payment'] ?? 0), 2, '.', ''),
            number_format((float) ($due['remaining_balance'] ?? 0), 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'customer_detail_csv' && $viewCustomerId > 0) {
    $st = $pdo->prepare("SELECT c.name, c.phone, l.date, l.debit, l.credit, l.balance, l.description
        FROM ledger l INNER JOIN customers c ON c.id = l.customer_id
        WHERE l.customer_id = ? ORDER BY l.date ASC, l.id ASC");
    $st->execute([$viewCustomerId]);
    $rows = $st->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer-ledger-detail-' . $viewCustomerId . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Date', 'Customer', 'Phone', 'Description', 'Debit', 'Credit', 'Balance']);
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) ($row['date'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['description'] ?? ''),
            number_format((float) ($row['debit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['credit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['balance'] ?? 0), 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'supplier_detail_csv' && $viewSupplierId > 0) {
    $st = $pdo->prepare("SELECT s.name, s.phone, l.entry_date, l.debit, l.credit, l.balance, l.description
        FROM supplier_ledger l INNER JOIN suppliers s ON s.id = l.supplier_id
        WHERE l.supplier_id = ? ORDER BY l.entry_date ASC, l.id ASC");
    $st->execute([$viewSupplierId]);
    $rows = $st->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=supplier-ledger-detail-' . $viewSupplierId . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Date', 'Supplier', 'Phone', 'Description', 'Debit', 'Credit', 'Balance']);
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) ($row['entry_date'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['description'] ?? ''),
            number_format((float) ($row['debit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['credit'] ?? 0), 2, '.', ''),
            number_format((float) ($row['balance'] ?? 0), 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

if (($export === 'customer_detail_pdf' || $export === 'customer_detail_print') && $viewCustomerId > 0) {
    $st = $pdo->prepare('SELECT id, name, phone, address FROM customers WHERE id = ?');
    $st->execute([$viewCustomerId]);
    $detail = $st->fetch();
    if (!$detail) {
        exit('Customer not found.');
    }
    $st = $pdo->prepare('SELECT id, total_amount, paid_amount, remaining_amount FROM invoices WHERE customer_id = ? ORDER BY id DESC');
    $st->execute([$viewCustomerId]);
    $invoices = $st->fetchAll();
    $st = $pdo->prepare('SELECT p.payment_date, p.amount, p.invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? ORDER BY p.payment_date DESC, p.id DESC');
    $st->execute([$viewCustomerId]);
    $payments = $st->fetchAll();
    $st = $pdo->prepare('SELECT date, description, debit, credit, balance FROM ledger WHERE customer_id = ? ORDER BY date DESC, id DESC');
    $st->execute([$viewCustomerId]);
    $ledgerRows = $st->fetchAll();

    $invoiceTotal = 0.0; $invoicePaid = 0.0; $invoiceRemaining = 0.0;
    foreach ($invoices as $inv) {
        $invoiceTotal += (float) ($inv['total_amount'] ?? 0);
        $invoicePaid += (float) ($inv['paid_amount'] ?? 0);
        $invoiceRemaining += (float) ($inv['remaining_amount'] ?? 0);
    }

    ob_start();
    ?>
    <h1><?= e(__('ledger.customer_ledger_detail')) ?></h1>
    <div class="muted mb-10"><?= e(__('ledger.generated')) ?>: <?= e(date('Y-m-d H:i')) ?></div>
    <div class="grid mb-10">
        <div class="card"><strong><?= e(__('common.customer')) ?></strong><br><?= e((string) $detail['name']) ?></div>
        <div class="card"><strong><?= e(__('customers.label_phone')) ?></strong><br><?= e((string) ($detail['phone'] ?? '-')) ?></div>
        <div class="card"><strong><?= e(__('customers.label_address')) ?></strong><br><?= e((string) ($detail['address'] ?? '-')) ?></div>
    </div>
    <div class="grid mb-10">
        <div class="card"><strong><?= e(__('ledger.total_billed')) ?></strong><br><?= e(format_currency($invoiceTotal)) ?></div>
        <div class="card"><strong><?= e(__('ledger.total_paid')) ?></strong><br><?= e(format_currency($invoicePaid)) ?></div>
        <div class="card"><strong><?= e(__('customers.col_outstanding')) ?></strong><br><?= e(format_currency($invoiceRemaining)) ?></div>
    </div>
    <h2><?= e(__('ledger.invoices')) ?></h2>
    <table>
        <thead><tr><th><?= e(__('invoices.col_invoice')) ?></th><th><?= e(__('common.total')) ?></th><th><?= e(__('common.paid')) ?></th><th><?= e(__('common.remaining')) ?></th></tr></thead>
        <tbody>
        <?php if (!$invoices): ?><tr><td colspan="4"><?= e(__('ledger.no_invoices')) ?></td></tr><?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
            <tr>
                <td>INV-<?= (int) $inv['id'] ?></td>
                <td class="text-right"><?= e(format_currency((float) $inv['total_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $inv['paid_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $inv['remaining_amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h2><?= e(__('ledger.payments')) ?></h2>
    <table>
        <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('invoices.col_invoice')) ?></th><th><?= e(__('payments.amount')) ?></th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="3"><?= e(__('ledger.no_payments')) ?></td></tr><?php endif; ?>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(format_date_pk((string) $p['payment_date'])) ?></td>
                <td>INV-<?= (int) $p['invoice_id'] ?></td>
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
                <td><?= e(format_date_pk((string) $l['date'])) ?></td>
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
    $renderA4Document('Customer Ledger Detail', $body);
    exit;
}

if (($export === 'supplier_detail_pdf' || $export === 'supplier_detail_print') && $viewSupplierId > 0) {
    $st = $pdo->prepare('SELECT id, name, phone, contact_person FROM suppliers WHERE id = ?');
    $st->execute([$viewSupplierId]);
    $detail = $st->fetch();
    if (!$detail) {
        exit('Supplier not found.');
    }
    $st = $pdo->prepare("SELECT t.id, t.transaction_date, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status,
        t.sent_qty_small, t.sent_qty_medium, t.sent_qty_large,
        t.sent_pressure_small, t.sent_pressure_medium, t.sent_pressure_large,
        (SELECT GROUP_CONCAT(CONCAT(COALESCE(b.refill_cylinder_type, ''), ':', COALESCE(b.quantity, 0), ':', COALESCE(b.pressure_received, 0)) ORDER BY b.id ASC SEPARATOR '|')
            FROM supplier_refill_breakdown b WHERE b.transaction_id = t.id) AS refill_breakdown
        FROM supplier_transactions t
        WHERE t.supplier_id = ?
        ORDER BY t.transaction_date DESC, t.id DESC");
    $st->execute([$viewSupplierId]);
    $transactions = $st->fetchAll();
    $st = $pdo->prepare('SELECT payment_date, amount, transaction_id, payment_type FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC');
    $st->execute([$viewSupplierId]);
    $payments = $st->fetchAll();
    $st = $pdo->prepare('SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE supplier_id = ? ORDER BY entry_date DESC, id DESC');
    $st->execute([$viewSupplierId]);
    $ledgerRows = $st->fetchAll();

    $purchaseTotal = 0.0; $purchasePaid = 0.0; $purchaseRemaining = 0.0;
    foreach ($transactions as $tx) {
        $purchaseTotal += (float) ($tx['total_amount'] ?? 0);
        $purchasePaid += (float) ($tx['paid_amount'] ?? 0);
        $purchaseRemaining += (float) ($tx['remaining_amount'] ?? 0);
    }

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
        <div class="card"><strong><?= e(__('customers.col_outstanding')) ?></strong><br><?= e(format_currency($purchaseRemaining)) ?></div>
    </div>
    <h2><?= e(__('ledger.transactions')) ?></h2>
    <table>
        <thead><tr><th><?= e(__('common.date')) ?></th><th><?= e(__('suppliers.col_purchase_ref')) ?></th><th><?= e(__('ledger.stock_breakdown')) ?></th><th><?= e(__('common.total')) ?></th><th><?= e(__('common.paid')) ?></th><th><?= e(__('common.remaining')) ?></th><th><?= e(__('common.status')) ?></th></tr></thead>
        <tbody>
        <?php if (!$transactions): ?><tr><td colspan="7"><?= e(__('ledger.no_transactions')) ?></td></tr><?php endif; ?>
        <?php foreach ($transactions as $tx): ?>
            <?php
            $sent = [];
            foreach ([['Small','small'],['Medium','medium'],['Large','large']] as $p) {
                $qty = (int) ($tx['sent_qty_' . $p[1]] ?? 0);
                $psi = (float) ($tx['sent_pressure_' . $p[1]] ?? 0);
                if ($qty > 0) { $sent[] = $p[0] . ' ' . $qty . ' (' . number_format($psi, 0) . ' PSI)'; }
            }
            $recv = [];
            foreach (explode('|', (string) ($tx['refill_breakdown'] ?? '')) as $chunk) {
                if ($chunk === '') continue;
                [$sz, $q, $p] = array_pad(explode(':', $chunk), 3, '0');
                $qv = (float) $q;
                if ($qv > 0) { $recv[] = trim((string) $sz) . ' ' . rtrim(rtrim(number_format($qv, 2, '.', ''), '0'), '.') . ' (' . number_format((float) $p, 0) . ' PSI)'; }
            }
            ?>
            <tr>
                <td><?= e(format_date_pk((string) $tx['transaction_date'])) ?></td>
                <td>SP-<?= (int) $tx['id'] ?></td>
                <td><?= e(__('ledger.sent')) ?>: <?= e($sent ? implode(' | ', $sent) : '—') ?><br><?= e(__('ledger.received')) ?>: <?= e($recv ? implode(' | ', $recv) : '—') ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['total_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['paid_amount'])) ?></td>
                <td class="text-right"><?= e(format_currency((float) $tx['remaining_amount'])) ?></td>
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

$customerDetail = null;
$customerDetailInvoices = [];
$customerDetailPayments = [];
$customerDetailLedger = [];
$ledgerScrollToPayment = false;
$ledgerPreselectInvoiceId = 0;
if ($entity === 'customer' && $viewCustomerId > 0) {
    $st = $pdo->prepare('SELECT id, name, phone, address FROM customers WHERE id = ?');
    $st->execute([$viewCustomerId]);
    $customerDetail = $st->fetch() ?: null;
    if ($customerDetail) {
        $st = $pdo->prepare('SELECT id, total_amount, paid_amount, remaining_amount FROM invoices WHERE customer_id = ? ORDER BY id DESC');
        $st->execute([$viewCustomerId]);
        $customerDetailInvoices = $st->fetchAll();
        $st = $pdo->prepare('SELECT p.payment_date, p.amount, p.invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? ORDER BY p.payment_date DESC, p.id DESC');
        $st->execute([$viewCustomerId]);
        $customerDetailPayments = $st->fetchAll();
        $st = $pdo->prepare('SELECT date, description, debit, credit, balance FROM ledger WHERE customer_id = ? ORDER BY date DESC, id DESC');
        $st->execute([$viewCustomerId]);
        $customerDetailLedger = $st->fetchAll();

        $ledgerScrollToPayment = isset($_GET['focus']) && (string) $_GET['focus'] === 'payment';
        $wantInvoice = (int) ($_GET['pay_invoice'] ?? 0);
        if ($wantInvoice > 0) {
            foreach ($customerDetailInvoices as $inv) {
                if ((int) ($inv['id'] ?? 0) === $wantInvoice && (float) ($inv['remaining_amount'] ?? 0) > 0.00001) {
                    $ledgerPreselectInvoiceId = $wantInvoice;
                    break;
                }
            }
        }
    }
}

$supplierDetail = null;
$supplierDetailTransactions = [];
$supplierDetailPayments = [];
$supplierDetailLedger = [];
if ($entity === 'supplier' && $viewSupplierId > 0) {
    $st = $pdo->prepare('SELECT id, name, phone, contact_person FROM suppliers WHERE id = ?');
    $st->execute([$viewSupplierId]);
    $supplierDetail = $st->fetch() ?: null;
    if ($supplierDetail) {
        $st = $pdo->prepare("SELECT t.id, t.transaction_date, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status,
            t.sent_qty_small, t.sent_qty_medium, t.sent_qty_large,
            t.sent_pressure_small, t.sent_pressure_medium, t.sent_pressure_large,
            (SELECT GROUP_CONCAT(CONCAT(
                COALESCE(b.refill_cylinder_type, ''),
                ':',
                COALESCE(b.quantity, 0),
                ':',
                COALESCE(b.pressure_received, 0)
            ) ORDER BY b.id ASC SEPARATOR '|')
            FROM supplier_refill_breakdown b WHERE b.transaction_id = t.id) AS refill_breakdown
            FROM supplier_transactions t
            WHERE t.supplier_id = ?
            ORDER BY t.transaction_date DESC, t.id DESC");
        $st->execute([$viewSupplierId]);
        $supplierDetailTransactions = $st->fetchAll();
        $st = $pdo->prepare('SELECT payment_date, amount, transaction_id, payment_type FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC');
        $st->execute([$viewSupplierId]);
        $supplierDetailPayments = $st->fetchAll();
        $st = $pdo->prepare('SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE supplier_id = ? ORDER BY entry_date DESC, id DESC');
        $st->execute([$viewSupplierId]);
        $supplierDetailLedger = $st->fetchAll();
    }
}

$summarizeLedgerDescription = static function (string $desc): string {
    $desc = trim($desc);
    if ($desc === '') return (string) __('common.none');
    if (preg_match('/#SP-\d+/i', $desc, $m)) {
        return 'Purchase ' . strtoupper($m[0]);
    }
    $line = trim((string) strtok($desc, "\n"));
    return mb_strlen($line) > 48 ? (mb_substr($line, 0, 48) . '...') : $line;
};

ob_start();
?>
<style>
@media print {
    @page {
        size: 80mm auto;
        margin: 4mm;
    }
    .no-print {
        display: none !important;
    }
}
</style>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5 no-print">
    <div class="flex flex-wrap items-center gap-2">
        <a href="?module=ledger&entity=customer<?= $customerSearch !== '' ? '&q=' . urlencode($customerSearch) : '' ?><?= i18n_lang_query() ?>" class="px-3 py-2 rounded-lg text-sm <?= $entity === 'customer' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700' ?>"><?= e(__('ledger.customer_ledger_tab')) ?></a>
        <a href="?module=ledger&entity=supplier<?= $supplierSearch !== '' ? '&supplier_q=' . urlencode($supplierSearch) : '' ?><?= i18n_lang_query() ?>" class="px-3 py-2 rounded-lg text-sm <?= $entity === 'supplier' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700' ?>"><?= e(__('ledger.supplier_ledger_tab')) ?></a>
    </div>
</section>
<?php if ($entity === 'customer'): ?>
<?php if (!$customerDetail): ?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between no-print">
        <h3 class="font-semibold shrink-0"><?= e(__('ledger.title')) ?> (Customers)</h3>
        <form method="get" class="flex flex-wrap gap-2 w-full sm:flex-1 sm:max-w-xl sm:ms-auto sm:justify-end">
            <input type="hidden" name="module" value="ledger">
            <input type="hidden" name="entity" value="customer">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <div class="relative min-w-0 flex-1 sm:min-w-[220px]">
                <input type="search" id="ledgerSearchField" name="q" value="<?= e($customerSearch) ?>" autocomplete="off" placeholder="<?= e(__('ledger.search_ph')) ?>" class="border rounded-lg px-3 py-2 text-sm w-full">
                <div id="ledgerSearchResults" class="hidden absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg text-sm"></div>
            </div>
            <button type="submit" class="bg-info text-white rounded-lg px-3 py-2 text-sm shrink-0"><?= e(__('common.search')) ?></button>
        </form>
    </div>
    <div class="overflow-x-auto table-wrap">
        <table data-sortable="true" class="w-full text-sm min-w-[980px]">
            <thead class="bg-slate-50">
            <tr>
                <th data-sort class="text-start p-3 whitespace-nowrap"><?= e(__('common.date')) ?></th>
                <th data-sort class="text-start p-3 min-w-[120px]"><?= e(__('ledger.col_customer')) ?></th>
                <th data-sort class="text-start p-3 min-w-[110px]"><?= e(__('customers.col_phone')) ?></th>
                <th data-sort class="text-start p-3 min-w-[160px]"><?= e(__('ledger.col_desc')) ?></th>
                <th class="text-start p-3"><?= e(__('ledger.col_cyl')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap"><?= e(__('ledger.col_debit')) ?></th>
                <th data-sort class="text-end p-3 whitespace-nowrap"><?= e(__('ledger.col_credit')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('ledger.due_payment')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('common.status')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $emptyMessage = null;
            if ($matchedCustomerCount === 0) {
                $emptyMessage = __('ledger.no_match');
            } elseif ($customerSearch !== '' && !$customerLedgerRows) {
                $emptyMessage = __('ledger.no_ledger_for_match');
            }
            if ($emptyMessage !== null): ?>
                <tr><td colspan="10" class="p-4 text-slate-500"><?= e($emptyMessage) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($customerLedgerRows as $row): $due = $customerDueMap[(int) ($row['customer_id'] ?? 0)] ?? ['due_payment' => 0.0, 'remaining_balance' => 0.0]; ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3 text-start align-middle whitespace-nowrap"><?= e(format_date_pk((string) $row['date'])) ?></td>
                    <td class="p-3 text-start align-middle"><?= e((string) $row['customer_name']) ?></td>
                    <td class="p-3 text-start align-middle tabular-nums"><?= e((string) $row['customer_phone']) ?></td>
                    <td class="p-3 text-start align-middle"><?php
                        $desc = trim((string) ($row['description'] ?? ''));
                        $fullDesc = $desc !== '' ? $desc : (string) __('common.none');
                        echo e($summarizeLedgerDescription($fullDesc));
                    ?></td>
                    <td class="p-3 text-start align-middle tabular-nums"><?= e((string) (($row['cylinders_sent'] ?? 0) . '/' . ($row['cylinders_received'] ?? 0) . '/' . ($row['cylinders_baqi'] ?? 0))) ?></td>
                    <td data-value="<?= (float) $row['debit'] ?>" class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $row['debit'])) ?></td>
                    <td data-value="<?= (float) $row['credit'] ?>" class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $row['credit'])) ?></td>
                    <td class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $due['due_payment'])) ?></td>
                    <td class="p-3 text-end align-middle tabular-nums">
                        <span class="<?= (float) $due['remaining_balance'] > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600 font-semibold' ?>">
                            <?= (float) $due['remaining_balance'] > 0 ? e(__('status.due')) . ' ' . e(format_currency((float) $due['remaining_balance'])) : e(__('status.paid')) ?>
                        </span>
                    </td>
                    <td class="p-3 text-end align-middle whitespace-nowrap">
                        <a href="?module=ledger&entity=customer&view_customer=<?= (int) ($row['customer_id'] ?? 0) ?><?= i18n_lang_query() ?>" class="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700"><?= e(__('customers.view')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 p-4 border-t border-slate-200 text-sm">
        <div><span class="text-slate-500"><?= e(__('ledger.total_debit')) ?>:</span> <strong><?= e(format_currency($customerSummary['debit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_credit')) ?>:</span> <strong><?= e(format_currency($customerSummary['credit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.net_balance')) ?>:</span> <strong class="<?= $customerSummary['balance'] > 0 ? 'text-warning' : 'text-primary' ?>"><?= e(format_currency($customerSummary['balance'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.due_payment')) ?>:</span> <strong><?= e(format_currency($customerSummary['due'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.remaining_balance')) ?>:</span> <strong><?= e(format_currency($customerSummary['remaining'])) ?></strong></div>
    </div>
</section>
<?php endif; ?>
<?php else: ?>
<?php if (!$supplierDetail): ?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <h3 class="font-semibold shrink-0"><?= e(__('ledger.supplier_ledger_title')) ?></h3>
        <form method="get" class="flex flex-wrap gap-2 w-full sm:flex-1 sm:max-w-xl sm:ms-auto sm:justify-end">
            <input type="hidden" name="module" value="ledger">
            <input type="hidden" name="entity" value="supplier">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <div class="min-w-0 flex-1 sm:min-w-[220px]">
                <input type="search" name="supplier_q" value="<?= e($supplierSearch) ?>" autocomplete="off" placeholder="Supplier name, phone or contact" class="border rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <button type="submit" class="bg-info text-white rounded-lg px-3 py-2 text-sm shrink-0"><?= e(__('common.search')) ?></button>
        </form>
    </div>
    <div class="overflow-x-auto table-wrap">
        <table data-sortable="true" class="w-full text-sm min-w-[980px]">
            <thead class="bg-slate-50">
            <tr>
                <th data-sort class="text-start p-3 whitespace-nowrap"><?= e(__('ledger.last_transaction')) ?></th>
                <th data-sort class="text-start p-3 min-w-[120px]"><?= e(__('suppliers.col_supplier')) ?></th>
                <th data-sort class="text-start p-3 min-w-[110px]"><?= e(__('suppliers.col_phone')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('ledger.due_payment')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('common.status')) ?></th>
                <th class="text-end p-3 whitespace-nowrap"><?= e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($supplierSearch === '' && $matchedSupplierCount === 0): ?>
                <tr><td colspan="6" class="p-4 text-slate-500"><?= e(__('ledger.no_supplier_records')) ?></td></tr>
            <?php elseif ($matchedSupplierCount === 0): ?>
                <tr><td colspan="6" class="p-4 text-slate-500"><?= e(__('ledger.no_supplier_match')) ?></td></tr>
            <?php elseif (!$supplierLedgerRows): ?>
                <tr><td colspan="6" class="p-4 text-slate-500"><?= e(__('ledger.no_supplier_refill_data')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($supplierLedgerRows as $row): $due = $supplierDueMap[(int) ($row['supplier_id'] ?? 0)] ?? ['due_payment' => (float) ($row['total_due'] ?? 0), 'remaining_balance' => (float) ($row['remaining_balance'] ?? 0)]; ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3 text-start align-middle whitespace-nowrap"><?= e(($row['last_transaction_date'] ?? '') !== '' ? format_date_pk((string) $row['last_transaction_date']) : '-') ?></td>
                    <td class="p-3 text-start align-middle"><?= e((string) ($row['supplier_name'] ?? '')) ?></td>
                    <td class="p-3 text-start align-middle tabular-nums"><?= e((string) ($row['supplier_phone'] ?? '')) ?></td>
                    <td class="p-3 text-end align-middle tabular-nums"><?= e(format_currency((float) $due['due_payment'])) ?></td>
                    <td class="p-3 text-end align-middle tabular-nums">
                        <span class="<?= (float) $due['remaining_balance'] > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600 font-semibold' ?>">
                            <?= (float) $due['remaining_balance'] > 0 ? e(__('status.due')) . ' ' . e(format_currency((float) $due['remaining_balance'])) : e(__('status.paid')) ?>
                        </span>
                    </td>
                    <td class="p-3 text-end align-middle whitespace-nowrap">
                        <a href="?module=ledger&entity=supplier&view_supplier=<?= (int) ($row['supplier_id'] ?? 0) ?><?= i18n_lang_query() ?>" class="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700"><?= e(__('customers.view')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 p-4 border-t border-slate-200 text-sm">
        <div><span class="text-slate-500"><?= e(__('ledger.total_debit')) ?>:</span> <strong><?= e(format_currency($supplierSummary['debit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_credit')) ?>:</span> <strong><?= e(format_currency($supplierSummary['credit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.net_balance')) ?>:</span> <strong class="<?= $supplierSummary['balance'] > 0 ? 'text-warning' : 'text-primary' ?>"><?= e(format_currency($supplierSummary['balance'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.due_payment')) ?>:</span> <strong><?= e(format_currency($supplierSummary['due'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.remaining_balance')) ?>:</span> <strong><?= e(format_currency($supplierSummary['remaining'])) ?></strong></div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php if ($entity === 'customer' && $customerDetail): ?>
<section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="font-semibold"><?= e(__('ledger.customer_view')) ?>: <?= e((string) $customerDetail['name']) ?></h3>
        <div class="flex gap-2">
            <a class="btn btn-soft" href="?module=ledger&entity=customer<?= i18n_lang_query() ?>"><?= e(__('ledger.back_to_list')) ?></a>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm mb-4">
        <div><span class="text-slate-500"><?= e(__('customers.label_phone')) ?>:</span> <?= e((string) ($customerDetail['phone'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('customers.label_address')) ?>:</span> <?= e((string) ($customerDetail['address'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_invoices')) ?>:</span> <?= count($customerDetailInvoices) ?></div>
    </div>
    <div id="ledgerCustomerPayment" class="border rounded-lg p-3 mb-4">
        <h4 class="font-semibold mb-2"><?= e(__('ledger.add_due_payment')) ?></h4>
        <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="add_customer_due_payment">
            <input type="hidden" name="customer_id" value="<?= (int) $customerDetail['id'] ?>">
            <select name="invoice_id" class="border rounded-lg px-3 py-2 text-sm" required>
                <option value=""><?= e(__('ledger.select_due_invoice')) ?></option>
                <?php foreach ($customerDetailInvoices as $inv): if ((float) ($inv['remaining_amount'] ?? 0) <= 0.00001) continue; ?>
                    <option value="<?= (int) $inv['id'] ?>"<?= $ledgerPreselectInvoiceId === (int) $inv['id'] ? ' selected' : '' ?>>INV-<?= (int) $inv['id'] ?> (<?= e(__('status.due')) ?>: <?= e(format_currency((float) $inv['remaining_amount'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="amount" step="0.01" min="0.01" required class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('payments.amount')) ?>">
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button class="bg-primary text-white rounded-lg px-3 py-2 text-sm"><?= e(__('payments.save')) ?></button>
        </form>
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('ledger.invoices')) ?></h4><?php foreach ($customerDetailInvoices as $inv): ?><p class="text-sm mb-1">INV-<?= (int) $inv['id'] ?> | <?= e(__('common.total')) ?> <?= e(format_currency((float) $inv['total_amount'])) ?> | <?= e(__('common.remaining')) ?> <?= e(format_currency((float) $inv['remaining_amount'])) ?></p><?php endforeach; ?><?php if (!$customerDetailInvoices): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_invoices')) ?></p><?php endif; ?></div>
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('ledger.payments')) ?></h4><?php foreach ($customerDetailPayments as $p): ?><p class="text-sm mb-1"><?= e(format_date_pk((string) $p['payment_date'])) ?> | INV-<?= (int) $p['invoice_id'] ?> | <?= e(format_currency((float) $p['amount'])) ?></p><?php endforeach; ?><?php if (!$customerDetailPayments): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_payments')) ?></p><?php endif; ?></div>
        <div class="border rounded-lg p-3"><h4 class="font-semibold mb-2"><?= e(__('ledger.ledger_transactions')) ?></h4><?php foreach ($customerDetailLedger as $l): ?><p class="text-sm mb-1"><?= e(format_date_pk((string) $l['date'])) ?> | D <?= e(format_currency((float) $l['debit'])) ?> | C <?= e(format_currency((float) $l['credit'])) ?> | B <?= e(format_currency((float) $l['balance'])) ?></p><?php endforeach; ?><?php if (!$customerDetailLedger): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_ledger_transactions')) ?></p><?php endif; ?></div>
    </div>
</section>
<?php if ($ledgerScrollToPayment): ?>
<script>
(() => {
    const el = document.getElementById('ledgerCustomerPayment');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const sel = el.querySelector('select[name="invoice_id"]');
        if (sel) {
            setTimeout(() => sel.focus(), 400);
        }
    }
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php if ($entity === 'supplier' && $supplierDetail): ?>
<section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
    <?php
    $paymentsByTx = [];
    foreach ($supplierDetailPayments as $payRow) {
        $txKey = (int) ($payRow['transaction_id'] ?? 0);
        if (!isset($paymentsByTx[$txKey])) {
            $paymentsByTx[$txKey] = [];
        }
        $paymentsByTx[$txKey][] = $payRow;
    }
    ?>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="font-semibold"><?= e(__('ledger.supplier_view')) ?>: <?= e((string) $supplierDetail['name']) ?></h3>
        <div class="flex gap-2">
            <a class="btn btn-soft" href="?module=ledger&entity=supplier<?= i18n_lang_query() ?>"><?= e(__('ledger.back_to_list')) ?></a>
            <button type="button" class="btn btn-primary" data-open-modal="supplierDuePaymentModal"><?= e(__('ledger.add_payment')) ?></button>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm mb-4">
        <div><span class="text-slate-500"><?= e(__('customers.label_phone')) ?>:</span> <?= e((string) ($supplierDetail['phone'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('suppliers.col_contact')) ?>:</span> <?= e((string) ($supplierDetail['contact_person'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_transactions')) ?>:</span> <?= count($supplierDetailTransactions) ?></div>
    </div>
    <div class="space-y-4">
        <?php foreach ($supplierDetailTransactions as $tx): ?>
            <?php
            $txId = (int) ($tx['id'] ?? 0);
            $sentParts = [];
            $sentData = [
                ['label' => 'Small', 'qty' => (int) ($tx['sent_qty_small'] ?? 0), 'psi' => (float) ($tx['sent_pressure_small'] ?? 0)],
                ['label' => 'Medium', 'qty' => (int) ($tx['sent_qty_medium'] ?? 0), 'psi' => (float) ($tx['sent_pressure_medium'] ?? 0)],
                ['label' => 'Large', 'qty' => (int) ($tx['sent_qty_large'] ?? 0), 'psi' => (float) ($tx['sent_pressure_large'] ?? 0)],
            ];
            foreach ($sentData as $s) {
                if ($s['qty'] <= 0) continue;
                $sentParts[] = $s['label'] . ' ' . $s['qty'] . ' (' . number_format($s['psi'], 0) . ' PSI)';
            }
            $recvParts = [];
            foreach (explode('|', (string) ($tx['refill_breakdown'] ?? '')) as $chunk) {
                if ($chunk === '') continue;
                [$sz, $q, $p] = array_pad(explode(':', $chunk), 3, '0');
                $qv = (float) $q;
                if ($qv <= 0) continue;
                $recvParts[] = trim((string) $sz) . ' ' . rtrim(rtrim(number_format($qv, 2, '.', ''), '0'), '.') . ' (' . number_format((float) $p, 0) . ' PSI)';
            }
            $txPayments = $paymentsByTx[$txId] ?? [];
            $paidTotal = 0.0;
            foreach ($txPayments as $paymentRow) {
                $paidTotal += (float) ($paymentRow['amount'] ?? 0);
            }
            $remaining = (float) ($tx['remaining_amount'] ?? 0);
            $status = $remaining <= 0.00001 ? 'Paid' : ($paidTotal > 0 ? 'Partial' : 'Due');
            ?>
            <div class="border rounded-lg p-3">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h4 class="font-semibold"><?= e(__('ledger.bill')) ?> SP-<?= $txId ?> (<?= e((string) $tx['transaction_date']) ?>)</h4>
                    <span class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e($status) ?></span>
                </div>
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-3 text-sm">
                    <div>
                        <p class="font-medium mb-1"><?= e(__('ledger.purchased_breakdown')) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.sent')) ?>:</span> <?= e($sentParts ? implode(' | ', $sentParts) : '—') ?></p>
                        <p class="text-slate-700"><span class="text-slate-500"><?= e(__('ledger.received')) ?>:</span> <?= e($recvParts ? implode(' | ', $recvParts) : '—') ?></p>
                    </div>
                    <div>
                        <p class="font-medium mb-1"><?= e(__('ledger.bill_summary')) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('common.total')) ?>:</span> <?= e(format_currency((float) $tx['total_amount'])) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('common.paid')) ?>:</span> <?= e(format_currency($paidTotal)) ?></p>
                        <p class="text-slate-700"><span class="text-slate-500"><?= e(__('common.remaining')) ?>:</span> <span class="<?= $remaining > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600 font-semibold' ?>"><?= e(format_currency($remaining)) ?></span></p>
                    </div>
                    <div>
                        <p class="font-medium mb-1"><?= e(__('ledger.payments_on_bill')) ?></p>
                        <?php if (!$txPayments): ?>
                            <p class="text-slate-500"><?= e(__('ledger.no_payments_yet')) ?></p>
                        <?php else: foreach ($txPayments as $paymentRow): ?>
                            <p class="mb-1"><?= e((string) $paymentRow['payment_date']) ?> | <?= e(format_currency((float) $paymentRow['amount'])) ?> (<?= e((string) $paymentRow['payment_type']) ?>)</p>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$supplierDetailTransactions): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_transactions')) ?></p><?php endif; ?>
    </div>
    <div class="border rounded-lg p-3 mt-4">
        <h4 class="font-semibold mb-2"><?= e(__('ledger.ledger_transactions')) ?></h4>
        <?php foreach ($supplierDetailLedger as $l): ?>
            <?php $entryType = (float) ($l['debit'] ?? 0) > 0 ? __('ledger.stock_purchase') : ((float) ($l['credit'] ?? 0) > 0 ? __('ledger.payment_received') : __('common.entry')); ?>
            <p class="text-sm mb-1"><?= e(format_date_pk((string) $l['entry_date'])) ?> | <?= e($entryType) ?> | D <?= e(format_currency((float) $l['debit'])) ?> | C <?= e(format_currency((float) $l['credit'])) ?> | B <?= e(format_currency((float) $l['balance'])) ?></p>
        <?php endforeach; ?>
        <?php if (!$supplierDetailLedger): ?><p class="text-sm text-slate-500"><?= e(__('ledger.no_ledger_transactions')) ?></p><?php endif; ?>
    </div>
</section>
<div id="supplierDuePaymentModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-modal="supplierDuePaymentModal"></div>
    <div class="relative w-full max-w-2xl bg-white border border-slate-200 rounded-xl p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="font-semibold"><?= e(__('ledger.add_due_payment')) ?></h3>
            <button type="button" class="btn btn-soft" data-close-modal="supplierDuePaymentModal"><?= e(__('common.close')) ?></button>
        </div>
        <form method="post" class="grid grid-cols-1 gap-3">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="add_supplier_due_payment">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierDetail['id'] ?>">
            <select name="transaction_id" class="border rounded-lg px-3 py-2 text-sm" required>
                <option value=""><?= e(__('ledger.select_due_transaction')) ?></option>
                <?php foreach ($supplierDetailTransactions as $tx): if ((float) ($tx['remaining_amount'] ?? 0) <= 0.00001) continue; ?>
                    <option value="<?= (int) $tx['id'] ?>">SP-<?= (int) $tx['id'] ?> (<?= e(__('status.due')) ?>: <?= e(format_currency((float) $tx['remaining_amount'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="amount" step="0.01" min="0.01" required class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('payments.amount')) ?>">
            <select name="payment_type" class="border rounded-lg px-3 py-2 text-sm">
                <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                <option value="Credit"><?= e(__('suppliers.pay_credit')) ?></option>
            </select>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button class="bg-primary text-white rounded-lg px-3 py-2 text-sm w-full"><?= e(__('payments.save')) ?></button>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
(() => {
    const i18n = <?= json_encode([
        'noCustomers' => __('ledger.js_no_customers'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const searchField = document.getElementById('ledgerSearchField');
    const searchResults = document.getElementById('ledgerSearchResults');
    if (searchField instanceof HTMLInputElement && searchResults instanceof HTMLElement) {
        let searchTimer = null;

        const hideResults = () => {
            searchResults.classList.add('hidden');
            searchResults.replaceChildren();
        };

        const runSearch = async () => {
            const q = searchField.value.trim();
            if (q.length < 1) {
                hideResults();
                return;
            }
            try {
                const u = new URL(window.location.href);
                u.searchParams.set('module', 'ledger');
                u.searchParams.set('ajax', 'search_customers');
                u.searchParams.set('q', q);
                const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const list = Array.isArray(data.customers) ? data.customers : [];
                searchResults.replaceChildren();

                if (!list.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-slate-500';
                    empty.textContent = i18n.noCustomers;
                    searchResults.appendChild(empty);
                } else {
                    list.forEach((customer) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'w-full text-left px-3 py-2 hover:bg-slate-50 border-b border-slate-100 last:border-0';
                        btn.textContent = `${customer.name} — ${customer.phone}`;
                        btn.addEventListener('mousedown', (event) => event.preventDefault());
                        btn.addEventListener('click', () => {
                            searchField.value = `${customer.name} ${customer.phone}`.trim();
                            hideResults();
                        });
                        searchResults.appendChild(btn);
                    });
                }
                searchResults.classList.remove('hidden');
            } catch (_) {
                hideResults();
            }
        };

        searchField.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = window.setTimeout(runSearch, 250);
        });
        searchField.addEventListener('focus', () => {
            if (searchField.value.trim().length >= 1) {
                runSearch();
            }
        });
        searchField.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') hideResults();
        });
        document.addEventListener('click', (event) => {
            if (event.target instanceof Node && !searchField.contains(event.target) && !searchResults.contains(event.target)) {
                hideResults();
            }
        });
    }

})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.ledger'), $content);
