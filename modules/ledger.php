<?php

require_once __DIR__ . '/../app/bootstrap.php';
use App\Services\OxygenOpsService;

$pdo = db();
ensure_supplier_transaction_notes_column($pdo);

$loadCustomerOrders = static function (PDO $pdo, int $customerId): array {
    if ($customerId <= 0) {
        return [];
    }
    $hasCylinderRows = table_exists($pdo, 'service_cylinder_rows');
    $sql = 'SELECT s.id AS service_id, s.date, s.service_type, s.total_bill, s.service_charges,
            s.previous_balance, s.grand_total, s.paid_amount, s.remaining_balance, s.notes,
            i.id AS invoice_id,
            COALESCE(i.total_amount, s.grand_total, 0) AS invoice_total,
            COALESCE(i.paid_amount, s.paid_amount, 0) AS invoice_paid,
            COALESCE(i.remaining_amount, s.remaining_balance, 0) AS invoice_remaining';
    if ($hasCylinderRows) {
        $sql .= ',
            COALESCE(SUM(r.sent_qty), 0) AS cylinders_sent,
            COALESCE(SUM(r.received_qty), 0) AS cylinders_received,
            COALESCE(SUM(r.baqi_qty), 0) AS cylinders_baqi';
    } else {
        $sql .= ', COALESCE(s.quantity, 0) AS cylinders_sent, 0 AS cylinders_received, 0 AS cylinders_baqi';
    }
    $sql .= '
        FROM services s
        LEFT JOIN invoices i ON i.service_id = s.id';
    if ($hasCylinderRows) {
        $sql .= ' LEFT JOIN service_cylinder_rows r ON r.service_id = s.id';
    }
    $sql .= ' WHERE s.customer_id = ?';
    if ($hasCylinderRows) {
        $sql .= ' GROUP BY s.id, s.date, s.service_type, s.total_bill, s.service_charges,
            s.previous_balance, s.grand_total, s.paid_amount, s.remaining_balance, s.notes,
            i.id, i.total_amount, i.paid_amount, i.remaining_amount, s.quantity';
    }
    $sql .= ' ORDER BY s.date DESC, s.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute([$customerId]);
    return $st->fetchAll();
};

$loadCustomerCylinderRows = static function (PDO $pdo, int $customerId): array {
    if ($customerId <= 0 || !table_exists($pdo, 'service_cylinder_rows')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT r.service_id, r.cylinder_size, r.sent_qty, r.received_qty, r.baqi_qty, r.rate, r.total_amount
         FROM service_cylinder_rows r
         INNER JOIN services s ON s.id = r.service_id
         WHERE s.customer_id = ?
         ORDER BY r.service_id DESC, r.id ASC'
    );
    $st->execute([$customerId]);
    $byService = [];
    foreach ($st->fetchAll() as $row) {
        $sid = (int) ($row['service_id'] ?? 0);
        if (!isset($byService[$sid])) {
            $byService[$sid] = [];
        }
        $byService[$sid][] = $row;
    }
    return $byService;
};

$buildLedgerCustomerDetailPayload = static function (PDO $pdo, int $customerId) use ($loadCustomerOrders): array {
    if ($customerId <= 0) {
        return ['ok' => false];
    }
    $st = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
    $st->execute([$customerId]);
    if (!$st->fetch()) {
        return ['ok' => false];
    }

    $ledgerDetailCols = 'date, description, debit, credit, balance';
    if (column_exists($pdo, 'ledger', 'reference_type')) {
        $ledgerDetailCols .= ', reference_type';
    }
    if (column_exists($pdo, 'ledger', 'cylinders_sent')) {
        $ledgerDetailCols .= ', cylinders_sent, cylinders_received, cylinders_baqi';
    }
    $st = $pdo->prepare("SELECT {$ledgerDetailCols} FROM ledger WHERE customer_id = ? ORDER BY date ASC, id ASC");
    $st->execute([$customerId]);
    $ledgerRows = $st->fetchAll();
    $hasCylCols = column_exists($pdo, 'ledger', 'cylinders_sent');
    $colspan = $hasCylCols ? 6 : 5;

    $ledgerRowsUi = [];
    foreach ($ledgerRows as $ledgerRow) {
        $refType = (string) ($ledgerRow['reference_type'] ?? '');
        $desc = trim((string) ($ledgerRow['description'] ?? ''));
        $ledgerRowsUi[] = [
            'date' => format_date_pk((string) $ledgerRow['date']),
            'description' => $desc !== '' ? $desc : (string) __('common.none'),
            'cylinders' => $hasCylCols
                ? (string) (($ledgerRow['cylinders_sent'] ?? 0) . '/' . ($ledgerRow['cylinders_received'] ?? 0) . '/' . ($ledgerRow['cylinders_baqi'] ?? 0))
                : '',
            'debit' => format_currency((float) ($ledgerRow['debit'] ?? 0)),
            'credit' => format_currency((float) ($ledgerRow['credit'] ?? 0)),
            'balance' => format_currency((float) ($ledgerRow['balance'] ?? 0)),
            'highlight' => in_array($refType, ['opening_balance', 'cylinder_settlement'], true),
        ];
    }

    $st = $pdo->prepare('SELECT id, remaining_amount FROM invoices WHERE customer_id = ? ORDER BY id ASC');
    $st->execute([$customerId]);
    $invoiceOptions = [];
    foreach ($st->fetchAll() as $inv) {
        $remaining = (float) ($inv['remaining_amount'] ?? 0);
        if ($remaining <= 0.00001) {
            continue;
        }
        $invId = (int) ($inv['id'] ?? 0);
        $invoiceOptions[] = [
            'id' => $invId,
            'label' => 'INV-' . $invId . ' (' . __('status.due') . ': ' . format_currency($remaining) . ')',
            'remaining' => $remaining,
        ];
    }

    $ordersUi = [];
    foreach ($loadCustomerOrders($pdo, $customerId) as $order) {
        $paid = (float) ($order['invoice_paid'] ?? 0);
        $remaining = (float) ($order['invoice_remaining'] ?? 0);
        $status = payment_status_from_amounts((float) ($order['invoice_total'] ?? 0), $paid);
        $ordersUi[] = [
            'service_id' => (int) ($order['service_id'] ?? 0),
            'paid_formatted' => format_currency($paid),
            'remaining_formatted' => format_currency($remaining),
            'remaining_raw' => $remaining,
            'status_label' => payment_status_label($status),
            'status_class' => $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due'),
        ];
    }

    return [
        'ok' => true,
        'snapshot' => customer_account_snapshot_for_ui($pdo, $customerId),
        'ledger_rows' => $ledgerRowsUi,
        'ledger_colspan' => $colspan,
        'ledger_has_cyl' => $hasCylCols,
        'invoices' => $invoiceOptions,
        'orders' => $ordersUi,
    ];
};

// Balance JSON for services.php (expects customer_id)
if (($_GET['ajax'] ?? '') === 'balance') {
    $cid = (int) ($_GET['customer_id'] ?? 0);
    $payload = ['ok' => true, 'balance' => 0.0, 'receivable' => 0.0];
    if ($cid > 0) {
        $snap = customer_account_snapshot_for_ui($pdo, $cid);
        $payload = array_merge($payload, $snap);
        $payload['balance'] = (float) $snap['receivable'];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['ajax'] ?? '') === 'finance_pulse') {
    header('Content-Type: application/json; charset=utf-8');
    $cashFrom = trim((string) ($_GET['cash_from'] ?? ''));
    $cashTo = trim((string) ($_GET['cash_to'] ?? ''));
    $pulse = financial_system_pulse(
        $pdo,
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $cashFrom) ? $cashFrom : null,
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $cashTo) ? $cashTo : null
    );
    $cid = (int) ($_GET['customer_id'] ?? 0);
    if ($cid > 0) {
        $pulse['customer'] = customer_receivable_map($pdo, [$cid])[$cid] ?? [
            'receivable' => 0.0,
            'receivable_formatted' => format_currency(0),
        ];
    }
    echo json_encode(['ok' => true, 'pulse' => $pulse], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['ajax'] ?? '') === 'customer_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int) ($_GET['customer_id'] ?? 0);
    $payload = $buildLedgerCustomerDetailPayload($pdo, $cid);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
$entity = 'customer';
$export = trim((string) ($_GET['export'] ?? ''));
$viewCustomerId = (int) ($_GET['view_customer'] ?? 0);
$viewSupplierId = (int) ($_GET['view_supplier'] ?? 0);
$legacySupplierQ = trim((string) ($_GET['supplier_q'] ?? ''));
if ($entity === 'supplier' || $viewSupplierId > 0 || $legacySupplierQ !== '') {
    if ($viewSupplierId > 0) {
        header('Location: ?module=suppliers&action=view_ledger&id=' . $viewSupplierId . i18n_lang_query());
        exit;
    }
    header('Location: ?module=suppliers' . i18n_lang_query());
    exit;
}
$entity = 'customer';

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
    $redirectCustomerId = (int) request_value('customer_id', '0');
    $ledgerAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $ops = new OxygenOpsService();
    try {
        if ($action === 'add_customer_due_payment') {
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
        } elseif ($action === 'add_customer_receive_payment') {
            $amount = (float) request_value('amount', '0');
            $paymentDate = request_value('payment_date', date('Y-m-d'));
            $note = request_value('payment_note');
            if ($redirectCustomerId > 0 && $amount > 0) {
                $ops->receiveCustomerPayment([
                    'customer_id' => $redirectCustomerId,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'note' => $note,
                ]);
            }
        } elseif ($action === 'add_customer_cylinder_return') {
            $qty = (int) request_value('cylinders_returned', '0');
            $returnDate = request_value('return_date', date('Y-m-d'));
            $note = request_value('return_note');
            if ($redirectCustomerId > 0 && $qty > 0) {
                $ops->recordCustomerCylinderReturn([
                    'customer_id' => $redirectCustomerId,
                    'cylinders_returned' => $qty,
                    'return_date' => $returnDate,
                    'note' => $note,
                ]);
            }
        }
    } catch (Throwable $e) {
        if ($ledgerAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ?module=ledger&view_customer=' . $redirectCustomerId . '&err=' . urlencode($e->getMessage()) . i18n_lang_query());
        exit;
    }
    if ($redirectCustomerId > 0) {
        if ($ledgerAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $detail = $buildLedgerCustomerDetailPayload($pdo, $redirectCustomerId);
            echo json_encode([
                'ok' => true,
                'snapshot' => $detail['snapshot'] ?? customer_account_snapshot_for_ui($pdo, $redirectCustomerId),
                'detail' => $detail,
                'pulse' => financial_system_pulse($pdo),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ?module=ledger&view_customer=' . $redirectCustomerId . i18n_lang_query());
        exit;
    }
}

$customerLedgerRows = [];
$customerSummary = ['debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0, 'due' => 0.0, 'remaining' => 0.0];
$customerDueMap = [];
$matchedCustomerCount = 0;

$customerIds = [];
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
    $st = $pdo->prepare('SELECT name, phone FROM customers WHERE id = ?');
    $st->execute([$viewCustomerId]);
    $cust = $st->fetch() ?: ['name' => '', 'phone' => ''];
    $orders = $loadCustomerOrders($pdo, $viewCustomerId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer-ledger-detail-' . $viewCustomerId . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'wb');
    fputcsv($out, [
        'Date', 'Customer', 'Phone', 'Order', 'Description', 'Daka', 'Tash', 'Baqi', 'Gas total', 'Service charges',
        'Previous balance', 'Grand total', 'Paid', 'Remaining', 'Invoice',
    ]);
    foreach ($orders as $order) {
        $serviceCharges = (float) ($order['service_charges'] ?? 0);
        $totalBill = (float) ($order['total_bill'] ?? 0);
        fputcsv($out, [
            (string) ($order['date'] ?? ''),
            (string) ($cust['name'] ?? ''),
            (string) ($cust['phone'] ?? ''),
            'ORD-' . (int) ($order['service_id'] ?? 0),
            trim((string) ($order['notes'] ?? '')),
            (int) ($order['cylinders_sent'] ?? 0),
            (int) ($order['cylinders_received'] ?? 0),
            (int) ($order['cylinders_baqi'] ?? 0),
            number_format(max(0, $totalBill - $serviceCharges), 2, '.', ''),
            number_format($serviceCharges, 2, '.', ''),
            number_format((float) ($order['previous_balance'] ?? 0), 2, '.', ''),
            number_format((float) ($order['grand_total'] ?? 0), 2, '.', ''),
            number_format((float) ($order['invoice_paid'] ?? 0), 2, '.', ''),
            number_format((float) ($order['invoice_remaining'] ?? 0), 2, '.', ''),
            (int) ($order['invoice_id'] ?? 0) > 0 ? 'INV-' . (int) $order['invoice_id'] : '',
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
    $orders = $loadCustomerOrders($pdo, $viewCustomerId);
    $st = $pdo->prepare('SELECT p.payment_date, p.amount, p.invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? ORDER BY p.payment_date DESC, p.id DESC');
    $st->execute([$viewCustomerId]);
    $paymentsByInvoice = [];
    foreach ($st->fetchAll() as $p) {
        $invId = (int) ($p['invoice_id'] ?? 0);
        if (!isset($paymentsByInvoice[$invId])) {
            $paymentsByInvoice[$invId] = [];
        }
        $paymentsByInvoice[$invId][] = $p;
    }

    $invoiceTotal = 0.0;
    $invoicePaid = 0.0;
    $invoiceRemaining = 0.0;
    foreach ($orders as $order) {
        $invoiceTotal += (float) ($order['invoice_total'] ?? 0);
        $invoicePaid += (float) ($order['invoice_paid'] ?? 0);
        $invoiceRemaining += (float) ($order['invoice_remaining'] ?? 0);
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
    <h2><?= e(__('ledger.order_history')) ?></h2>
    <?php if (!$orders): ?>
        <p><?= e(__('ledger.no_orders')) ?></p>
    <?php endif; ?>
    <?php foreach ($orders as $order): ?>
        <?php
        $serviceId = (int) ($order['service_id'] ?? 0);
        $invoiceId = (int) ($order['invoice_id'] ?? 0);
        $serviceCharges = (float) ($order['service_charges'] ?? 0);
        $totalBill = (float) ($order['total_bill'] ?? 0);
        $gasTotal = max(0, $totalBill - $serviceCharges);
        $paid = (float) ($order['invoice_paid'] ?? 0);
        $remaining = (float) ($order['invoice_remaining'] ?? 0);
        $status = payment_status_from_amounts((float) ($order['invoice_total'] ?? 0), $paid);
        $orderPayments = $paymentsByInvoice[$invoiceId] ?? [];
        ?>
        <div class="card mb-10">
            <strong><?= e(__('ledger.order_ref')) ?> ORD-<?= $serviceId ?></strong>
            (<?= e(format_date_pk((string) $order['date'])) ?>)
            — <?= e(payment_status_label($status)) ?>
            <table>
                <tr><td><?= e(__('ledger.col_desc')) ?></td><td><?= e(trim((string) ($order['notes'] ?? '')) !== '' ? trim((string) $order['notes']) : (string) __('common.none')) ?></td></tr>
                <tr><td><?= e(__('ledger.daka')) ?></td><td class="text-right"><?= (int) ($order['cylinders_sent'] ?? 0) ?></td></tr>
                <tr><td><?= e(__('ledger.tash')) ?></td><td class="text-right"><?= (int) ($order['cylinders_received'] ?? 0) ?></td></tr>
                <tr><td><?= e(__('ledger.baqi')) ?></td><td class="text-right"><?= (int) ($order['cylinders_baqi'] ?? 0) ?></td></tr>
                <tr><td><?= e(__('ledger.gas_total')) ?></td><td class="text-right"><?= e(format_currency($gasTotal)) ?></td></tr>
                <tr><td><?= e(__('ledger.service_charges')) ?></td><td class="text-right"><?= e(format_currency($serviceCharges)) ?></td></tr>
                <tr><td><?= e(__('ledger.previous_balance')) ?></td><td class="text-right"><?= e(format_currency((float) ($order['previous_balance'] ?? 0))) ?></td></tr>
                <tr><td><?= e(__('ledger.grand_total')) ?></td><td class="text-right"><?= e(format_currency((float) ($order['grand_total'] ?? 0))) ?></td></tr>
                <tr><td><?= e(__('ledger.paid_on_order')) ?></td><td class="text-right"><?= e(format_currency($paid)) ?></td></tr>
                <tr><td><?= e(__('common.remaining')) ?></td><td class="text-right"><?= e(format_currency($remaining)) ?></td></tr>
            </table>
            <?php if ($orderPayments): ?>
                <p><strong><?= e(__('ledger.payments_on_bill')) ?></strong></p>
                <ul>
                <?php foreach ($orderPayments as $p): ?>
                    <li><?= e(format_date_pk((string) $p['payment_date'])) ?> — <?= e(format_currency((float) $p['amount'])) ?></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php
    $body = (string) ob_get_clean();
    $renderA4Document('Customer Ledger Detail', $body);
    exit;
}

$customerDetail = null;
$customerDetailInvoices = [];
$customerDetailPayments = [];
$customerDetailOrders = [];
$customerDetailCylinderRows = [];
$customerDetailLedger = [];
$customerLedgerBalance = 0.0;
$customerAccountSnapshot = [];
$customerDetailSummary = ['orders' => 0, 'billed' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];
$ledgerFlashError = trim((string) ($_GET['err'] ?? ''));
$ledgerScrollToPayment = false;
$ledgerPreselectInvoiceId = 0;
if ($viewCustomerId > 0) {
    $st = $pdo->prepare('SELECT id, name, phone, address FROM customers WHERE id = ?');
    $st->execute([$viewCustomerId]);
    $customerDetail = $st->fetch() ?: null;
    if ($customerDetail) {
        $customerDetailOrders = $loadCustomerOrders($pdo, $viewCustomerId);
        $customerDetailCylinderRows = $loadCustomerCylinderRows($pdo, $viewCustomerId);
        $st = $pdo->prepare('SELECT id, total_amount, paid_amount, remaining_amount FROM invoices WHERE customer_id = ? ORDER BY id DESC');
        $st->execute([$viewCustomerId]);
        $customerDetailInvoices = $st->fetchAll();
        $st = $pdo->prepare('SELECT p.payment_date, p.amount, p.invoice_id FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? ORDER BY p.payment_date DESC, p.id DESC');
        $st->execute([$viewCustomerId]);
        $customerDetailPayments = $st->fetchAll();
        $ledgerDetailCols = 'date, description, debit, credit, balance';
        if (column_exists($pdo, 'ledger', 'reference_type')) {
            $ledgerDetailCols .= ', reference_type';
        }
        if (column_exists($pdo, 'ledger', 'cylinders_sent')) {
            $ledgerDetailCols .= ', cylinders_sent, cylinders_received, cylinders_baqi';
        }
        $st = $pdo->prepare("SELECT {$ledgerDetailCols} FROM ledger WHERE customer_id = ? ORDER BY date ASC, id ASC");
        $st->execute([$viewCustomerId]);
        $customerDetailLedger = $st->fetchAll();
        $customerLedgerBalance = 0.0;
        if ($customerDetailLedger !== []) {
            $lastLedgerRow = $customerDetailLedger[count($customerDetailLedger) - 1];
            $customerLedgerBalance = (float) ($lastLedgerRow['balance'] ?? 0);
        }
        $customerAccountSnapshot = customer_account_snapshot($pdo, $viewCustomerId);
        $customerLedgerBalance = (float) ($customerAccountSnapshot['receivable'] ?? $customerLedgerBalance);
        $customerDetailSummary['orders'] = count($customerDetailOrders);
        foreach ($customerDetailOrders as $order) {
            $customerDetailSummary['billed'] += (float) ($order['grand_total'] ?? 0);
            $customerDetailSummary['paid'] += (float) ($order['invoice_paid'] ?? 0);
            $customerDetailSummary['remaining'] += (float) ($order['invoice_remaining'] ?? 0);
        }

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
<?php if (!$customerDetail): ?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between no-print">
        <h3 class="font-semibold shrink-0"><?= e(__('ledger.title')) ?></h3>
        <form method="get" class="flex flex-wrap gap-2 w-full sm:flex-1 sm:max-w-xl sm:ms-auto sm:justify-end">
            <input type="hidden" name="module" value="ledger">
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
                        <a href="?module=ledger&view_customer=<?= (int) ($row['customer_id'] ?? 0) ?><?= i18n_lang_query() ?>" class="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700"><?= e(__('customers.view')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 p-4 border-t border-slate-200 text-sm">
        <div><span class="text-slate-500"><?= e(__('ledger.total_debit')) ?>:</span> <strong><?= e(format_currency($customerSummary['debit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_credit')) ?>:</span> <strong><?= e(format_currency($customerSummary['credit'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.net_receivable')) ?>:</span> <strong class="<?= $customerSummary['balance'] > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= e(format_currency($customerSummary['balance'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.due_payment')) ?>:</span> <strong><?= e(format_currency($customerSummary['due'])) ?></strong></div>
        <div><span class="text-slate-500"><?= e(__('ledger.remaining_balance')) ?>:</span> <strong><?= e(format_currency($customerSummary['remaining'])) ?></strong></div>
    </div>
</section>
<?php endif; ?>
<?php if ($customerDetail): ?>
<section class="mt-5 bg-white border border-slate-200 rounded-xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="font-semibold"><?= e(__('ledger.customer_view')) ?>: <?= e((string) $customerDetail['name']) ?></h3>
        <div class="flex gap-2">
            <a class="btn btn-soft" href="?module=ledger<?= i18n_lang_query() ?>"><?= e(__('ledger.back_to_list')) ?></a>
        </div>
    </div>
    <?php
    $paymentsByInvoice = [];
    foreach ($customerDetailPayments as $payRow) {
        $invKey = (int) ($payRow['invoice_id'] ?? 0);
        if (!isset($paymentsByInvoice[$invKey])) {
            $paymentsByInvoice[$invKey] = [];
        }
        $paymentsByInvoice[$invKey][] = $payRow;
    }
    ?>
    <?php
    $showOpeningBalance = !empty($customerAccountSnapshot['show_opening_balance']);
    $showOpeningCylinders = !empty($customerAccountSnapshot['show_opening_cylinders']);
    $receivableTone = $customerLedgerBalance > 0.00001 ? 'text-amber-700' : 'text-emerald-700';
    ?>
    <div id="ledgerCustomerHeader" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-sm mb-4">
        <div><span class="text-slate-500"><?= e(__('customers.label_phone')) ?>:</span> <?= e((string) ($customerDetail['phone'] ?? '')) ?></div>
        <div><span class="text-slate-500"><?= e(__('ledger.total_orders')) ?>:</span> <strong id="ledgerHdrOrders"><?= (int) $customerDetailSummary['orders'] ?></strong></div>
        <div id="ledgerHdrOpeningBalance" class="<?= $showOpeningBalance ? '' : 'hidden' ?>">
            <span class="text-slate-500"><?= e(__('customers.opening_balance')) ?>:</span>
            <strong id="ledgerHdrOpeningBalanceVal"><?= e(format_currency((float) ($customerAccountSnapshot['opening_balance_remaining'] ?? 0))) ?></strong>
        </div>
        <div id="ledgerHdrOpeningCylinders" class="<?= $showOpeningCylinders ? '' : 'hidden' ?>">
            <span class="text-slate-500"><?= e(__('customers.opening_cylinders')) ?>:</span>
            <strong id="ledgerHdrOpeningCylindersVal"><?= (int) ($customerAccountSnapshot['opening_cylinders_remaining'] ?? 0) ?></strong>
        </div>
        <div>
            <span class="text-slate-500"><?= e(__('ledger.cylinders_owed_now')) ?>:</span>
            <strong id="ledgerHdrCylindersOwed" class="text-amber-700"><?= (int) ($customerAccountSnapshot['cylinders_owed'] ?? 0) ?></strong>
        </div>
        <div>
            <span class="text-slate-500"><?= e(__('ledger.invoice_remaining')) ?>:</span>
            <strong id="ledgerHdrInvoiceRemaining" class="text-amber-700"><?= e(format_currency((float) ($customerAccountSnapshot['invoice_remaining'] ?? 0))) ?></strong>
        </div>
        <div class="col-span-2 md:col-span-1">
            <span class="text-slate-500"><?= e(__('ledger.net_receivable')) ?>:</span>
            <strong id="ledgerHdrReceivable" class="<?= e($receivableTone) ?>"><?= e(format_currency($customerLedgerBalance)) ?></strong>
        </div>
    </div>
    <p id="ledgerAjaxError" class="hidden mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert"></p>

    <h4 class="font-semibold mb-2"><?= e(__('ledger.ledger_transactions')) ?></h4>
    <div class="overflow-x-auto table-wrap mb-6">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-slate-50">
            <tr>
                <th class="text-start p-3"><?= e(__('common.date')) ?></th>
                <th class="text-start p-3"><?= e(__('ledger.col_desc')) ?></th>
                <?php if (column_exists($pdo, 'ledger', 'cylinders_sent')): ?>
                <th class="text-start p-3"><?= e(__('ledger.col_cyl')) ?></th>
                <?php endif; ?>
                <th class="text-end p-3"><?= e(__('ledger.col_debit')) ?></th>
                <th class="text-end p-3"><?= e(__('ledger.col_credit')) ?></th>
                <th class="text-end p-3"><?= e(__('ledger.col_balance')) ?></th>
            </tr>
            </thead>
            <tbody id="ledgerCustomerLedgerBody">
            <?php if (!$customerDetailLedger): ?>
                <tr><td colspan="<?= column_exists($pdo, 'ledger', 'cylinders_sent') ? 6 : 5 ?>" class="p-3 text-slate-500"><?= e(__('ledger.no_ledger_transactions')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($customerDetailLedger as $ledgerRow): ?>
                <?php $refType = (string) ($ledgerRow['reference_type'] ?? ''); ?>
                <tr class="border-t border-slate-100<?= in_array($refType, ['opening_balance', 'cylinder_settlement'], true) ? ' bg-sky-50/60' : '' ?>">
                    <td class="p-3 whitespace-nowrap"><?= e(format_date_pk((string) $ledgerRow['date'])) ?></td>
                    <td class="p-3"><?= e(trim((string) ($ledgerRow['description'] ?? '')) !== '' ? trim((string) $ledgerRow['description']) : (string) __('common.none')) ?></td>
                    <?php if (column_exists($pdo, 'ledger', 'cylinders_sent')): ?>
                    <td class="p-3 tabular-nums"><?= e((string) (($ledgerRow['cylinders_sent'] ?? 0) . '/' . ($ledgerRow['cylinders_received'] ?? 0) . '/' . ($ledgerRow['cylinders_baqi'] ?? 0))) ?></td>
                    <?php endif; ?>
                    <td class="p-3 text-end tabular-nums"><?= e(format_currency((float) ($ledgerRow['debit'] ?? 0))) ?></td>
                    <td class="p-3 text-end tabular-nums"><?= e(format_currency((float) ($ledgerRow['credit'] ?? 0))) ?></td>
                    <td class="p-3 text-end tabular-nums font-medium"><?= e(format_currency((float) ($ledgerRow['balance'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($ledgerFlashError !== ''): ?>
        <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= e($ledgerFlashError) ?></p>
    <?php endif; ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div id="ledgerCustomerPayment" class="border rounded-lg p-3">
            <h4 class="font-semibold mb-1"><?= e(__('ledger.receive_payment')) ?></h4>
            <p class="text-xs text-slate-500 mb-2"><?= e(__('ledger.receive_payment_hint')) ?></p>
            <form method="post" class="ledger-ajax-form grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
                <input type="hidden" name="action" value="add_customer_receive_payment">
                <input type="hidden" name="customer_id" value="<?= (int) $customerDetail['id'] ?>">
                <input type="number" id="ledgerReceivePaymentAmount" name="amount" step="0.01" min="0.01"<?= $customerLedgerBalance > 0 ? ' max="' . e((string) $customerLedgerBalance) . '"' : '' ?> required class="border rounded-lg px-3 py-2 text-sm sm:col-span-2" placeholder="<?= e(__('payments.amount')) ?>">
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
                <input type="text" name="payment_note" class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('ledger.payment_note_ph')) ?>">
                <button class="bg-primary text-white rounded-lg px-3 py-2 text-sm sm:col-span-2"><?= e(__('ledger.receive_payment_btn')) ?></button>
            </form>
        </div>
        <div class="border rounded-lg p-3">
            <h4 class="font-semibold mb-1"><?= e(__('ledger.pay_against_invoice')) ?></h4>
            <p class="text-xs text-slate-500 mb-2"><?= e(__('ledger.pay_against_invoice_hint')) ?></p>
            <form method="post" class="ledger-ajax-form grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
                <input type="hidden" name="action" value="add_customer_due_payment">
                <input type="hidden" name="customer_id" value="<?= (int) $customerDetail['id'] ?>">
                <select id="ledgerInvoiceSelect" name="invoice_id" class="border rounded-lg px-3 py-2 text-sm sm:col-span-2" required>
                    <option value=""><?= e(__('ledger.select_due_invoice')) ?></option>
                    <?php foreach ($customerDetailInvoices as $inv): if ((float) ($inv['remaining_amount'] ?? 0) <= 0.00001) continue; ?>
                        <option value="<?= (int) $inv['id'] ?>"<?= $ledgerPreselectInvoiceId === (int) $inv['id'] ? ' selected' : '' ?>>INV-<?= (int) $inv['id'] ?> (<?= e(__('status.due')) ?>: <?= e(format_currency((float) $inv['remaining_amount'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="amount" step="0.01" min="0.01" required class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('payments.amount')) ?>">
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
                <button class="bg-slate-800 text-white rounded-lg px-3 py-2 text-sm sm:col-span-2"><?= e(__('payments.save')) ?></button>
            </form>
        </div>
    </div>
    <div class="border rounded-lg p-3 mb-4">
        <h4 class="font-semibold mb-1"><?= e(__('ledger.record_cylinder_return')) ?></h4>
        <p class="text-xs text-slate-500 mb-2"><?= e(__('ledger.record_cylinder_return_hint')) ?></p>
        <form method="post" class="ledger-ajax-form grid grid-cols-1 sm:grid-cols-4 gap-2">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="add_customer_cylinder_return">
            <input type="hidden" name="customer_id" value="<?= (int) $customerDetail['id'] ?>">
            <input type="number" id="ledgerCylinderReturnQty" name="cylinders_returned" step="1" min="1"<?= (int) ($customerAccountSnapshot['cylinders_owed'] ?? 0) > 0 ? ' max="' . (int) $customerAccountSnapshot['cylinders_owed'] . '"' : '' ?> required class="border rounded-lg px-3 py-2 text-sm" placeholder="<?= e(__('ledger.cylinders_returned_ph')) ?>">
            <input type="date" name="return_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
            <input type="text" name="return_note" class="border rounded-lg px-3 py-2 text-sm sm:col-span-2" placeholder="<?= e(__('ledger.return_note_ph')) ?>">
            <button class="bg-sky-700 text-white rounded-lg px-3 py-2 text-sm"><?= e(__('ledger.record_cylinder_return_btn')) ?></button>
        </form>
    </div>

    <h4 class="font-semibold mb-3"><?= e(__('ledger.order_history')) ?></h4>
    <div class="space-y-4">
        <?php foreach ($customerDetailOrders as $order): ?>
            <?php
            $serviceId = (int) ($order['service_id'] ?? 0);
            $invoiceId = (int) ($order['invoice_id'] ?? 0);
            $serviceCharges = (float) ($order['service_charges'] ?? 0);
            $totalBill = (float) ($order['total_bill'] ?? 0);
            $gasTotal = max(0, $totalBill - $serviceCharges);
            $paid = (float) ($order['invoice_paid'] ?? 0);
            $remaining = (float) ($order['invoice_remaining'] ?? 0);
            $status = payment_status_from_amounts((float) ($order['invoice_total'] ?? 0), $paid);
            $orderPayments = $paymentsByInvoice[$invoiceId] ?? [];
            $cylinderLines = $customerDetailCylinderRows[$serviceId] ?? [];
            $orderDescription = trim((string) ($order['notes'] ?? ''));
            ?>
            <div class="border rounded-lg p-3 sm:p-4" data-ledger-order-id="<?= $serviceId ?>">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h5 class="font-semibold text-slate-900"><?= e(__('ledger.order_ref')) ?> ORD-<?= $serviceId ?> · <?= e(format_date_pk((string) $order['date'])) ?></h5>
                    <span data-ledger-order-status class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span>
                </div>
                <p class="text-sm text-slate-600 mb-3">
                    <span class="font-medium text-slate-700"><?= e(__('ledger.col_desc')) ?>:</span>
                    <?= e($orderDescription !== '' ? $orderDescription : (string) __('common.none')) ?>
                </p>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="font-medium mb-2 text-slate-800"><?= e(__('ledger.cylinder_lines')) ?></p>
                        <div class="grid grid-cols-3 gap-2 mb-2 text-center">
                            <div class="rounded-lg bg-slate-50 p-2">
                                <p class="text-[11px] text-slate-500"><?= e(__('ledger.daka')) ?></p>
                                <p class="text-lg font-semibold"><?= (int) ($order['cylinders_sent'] ?? 0) ?></p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-2">
                                <p class="text-[11px] text-emerald-800"><?= e(__('ledger.tash')) ?></p>
                                <p class="text-lg font-semibold text-emerald-900"><?= (int) ($order['cylinders_received'] ?? 0) ?></p>
                            </div>
                            <div class="rounded-lg bg-sky-50 p-2">
                                <p class="text-[11px] text-sky-800"><?= e(__('ledger.baqi')) ?></p>
                                <p class="text-lg font-semibold text-sky-900"><?= (int) ($order['cylinders_baqi'] ?? 0) ?></p>
                            </div>
                        </div>
                        <?php if ($cylinderLines): ?>
                            <ul class="text-xs text-slate-600 space-y-1">
                            <?php foreach ($cylinderLines as $line): ?>
                                <li><?= e(__('ledger.daka')) ?> <?= (int) ($line['sent_qty'] ?? 0) ?>, <?= e(__('ledger.tash')) ?> <?= (int) ($line['received_qty'] ?? 0) ?><?php if ((float) ($line['total_amount'] ?? 0) > 0): ?> · <?= e(format_currency((float) $line['total_amount'])) ?><?php endif; ?></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="font-medium mb-2 text-slate-800"><?= e(__('ledger.bill_summary')) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.gas_total')) ?>:</span> <?= e(format_currency($gasTotal)) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.service_charges')) ?>:</span> <?= e(format_currency($serviceCharges)) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.previous_balance')) ?>:</span> <?= e(format_currency((float) ($order['previous_balance'] ?? 0))) ?></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.grand_total')) ?>:</span> <strong><?= e(format_currency((float) ($order['grand_total'] ?? 0))) ?></strong></p>
                        <p class="text-slate-700 mb-1"><span class="text-slate-500"><?= e(__('ledger.paid_on_order')) ?>:</span> <span data-ledger-order-paid><?= e(format_currency($paid)) ?></span></p>
                        <p class="text-slate-700"><span class="text-slate-500"><?= e(__('common.remaining')) ?>:</span> <span data-ledger-order-remaining class="<?= $remaining > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600 font-semibold' ?>"><?= e(format_currency($remaining)) ?></span></p>
                        <?php if ($invoiceId > 0): ?>
                            <p class="text-xs text-slate-500 mt-1">INV-<?= $invoiceId ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="font-medium mb-2 text-slate-800"><?= e(__('ledger.payments_on_bill')) ?></p>
                        <?php if (!$orderPayments): ?>
                            <p class="text-slate-500"><?= e(__('ledger.no_payments_yet')) ?></p>
                        <?php else: foreach ($orderPayments as $paymentRow): ?>
                            <p class="mb-1"><?= e(format_date_pk((string) $paymentRow['payment_date'])) ?> · <?= e(format_currency((float) $paymentRow['amount'])) ?></p>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$customerDetailOrders): ?>
            <p class="text-sm text-slate-500"><?= e(__('ledger.no_orders')) ?></p>
        <?php endif; ?>
    </div>
</section>
<script>
(() => {
    const errEl = document.getElementById('ledgerAjaxError');
    const noLedgerTx = <?= json_encode(__('ledger.no_ledger_transactions'), JSON_UNESCAPED_UNICODE) ?>;
    const selectInvoicePh = <?= json_encode(__('ledger.select_due_invoice'), JSON_UNESCAPED_UNICODE) ?>;
    const toneClass = (tone) => (tone === 'amber' ? 'text-amber-700' : 'text-emerald-700');

    const notifyFinance = (customerId) => {
        const payload = { type: 'payment', customerId: Number(customerId || 0), at: Date.now() };
        if (window.OxygenFinance?.notify) {
            window.OxygenFinance.notify(payload);
        }
    };

    const renderLedgerRows = (detail) => {
        const tbody = document.getElementById('ledgerCustomerLedgerBody');
        if (!tbody || !detail) return;
        const rows = Array.isArray(detail.ledger_rows) ? detail.ledger_rows : [];
        const colspan = Number(detail.ledger_colspan || 5);
        const hasCyl = !!detail.ledger_has_cyl;
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="p-3 text-slate-500">${noLedgerTx}</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map((row) => {
            const hi = row.highlight ? ' bg-sky-50/60' : '';
            const cyl = hasCyl ? `<td class="p-3 tabular-nums">${row.cylinders || ''}</td>` : '';
            return `<tr class="border-t border-slate-100${hi}">
                <td class="p-3 whitespace-nowrap">${row.date || ''}</td>
                <td class="p-3">${row.description || ''}</td>
                ${cyl}
                <td class="p-3 text-end tabular-nums">${row.debit || ''}</td>
                <td class="p-3 text-end tabular-nums">${row.credit || ''}</td>
                <td class="p-3 text-end tabular-nums font-medium">${row.balance || ''}</td>
            </tr>`;
        }).join('');
    };

    const renderInvoiceSelect = (detail) => {
        const sel = document.getElementById('ledgerInvoiceSelect');
        if (!sel || !detail) return;
        const current = sel.value;
        const invoices = Array.isArray(detail.invoices) ? detail.invoices : [];
        sel.innerHTML = `<option value="">${selectInvoicePh}</option>` + invoices.map((inv) =>
            `<option value="${inv.id}">${inv.label}</option>`
        ).join('');
        if (current && [...sel.options].some((o) => o.value === current)) {
            sel.value = current;
        }
    };

    const applyOrders = (detail) => {
        const orders = Array.isArray(detail?.orders) ? detail.orders : [];
        orders.forEach((o) => {
            const card = document.querySelector(`[data-ledger-order-id="${o.service_id}"]`);
            if (!card) return;
            const statusEl = card.querySelector('[data-ledger-order-status]');
            if (statusEl) {
                statusEl.textContent = o.status_label || '';
                statusEl.className = `px-2 py-1 rounded-full text-xs ${o.status_class || 'status-due'}`;
            }
            const paidEl = card.querySelector('[data-ledger-order-paid]');
            if (paidEl) paidEl.textContent = o.paid_formatted || '';
            const remEl = card.querySelector('[data-ledger-order-remaining]');
            if (remEl) {
                remEl.textContent = o.remaining_formatted || '';
                remEl.classList.remove('text-amber-600', 'text-emerald-600', 'font-semibold');
                remEl.classList.add((Number(o.remaining_raw || 0) > 0 ? 'text-amber-600' : 'text-emerald-600'), 'font-semibold');
            }
        });
    };

    const applyCustomerDetail = (detail) => {
        if (!detail?.ok) return;
        applySnapshot(detail.snapshot);
        renderLedgerRows(detail);
        renderInvoiceSelect(detail);
        applyOrders(detail);
    };

    const applySnapshot = (snap) => {
        if (!snap) return;
        const obCard = document.getElementById('ledgerHdrOpeningBalance');
        const ocCard = document.getElementById('ledgerHdrOpeningCylinders');
        const obVal = document.getElementById('ledgerHdrOpeningBalanceVal');
        const ocVal = document.getElementById('ledgerHdrOpeningCylindersVal');
        const cylOwed = document.getElementById('ledgerHdrCylindersOwed');
        const invRem = document.getElementById('ledgerHdrInvoiceRemaining');
        const receivable = document.getElementById('ledgerHdrReceivable');
        if (obCard) obCard.classList.toggle('hidden', !snap.show_opening_balance);
        if (ocCard) ocCard.classList.toggle('hidden', !snap.show_opening_cylinders);
        if (obVal) obVal.textContent = snap.opening_balance_remaining_formatted || '';
        if (ocVal) ocVal.textContent = String(snap.opening_cylinders_remaining ?? 0);
        if (cylOwed) cylOwed.textContent = String(snap.cylinders_owed ?? 0);
        if (invRem) invRem.textContent = snap.invoice_remaining_formatted || '';
        if (receivable) {
            receivable.textContent = snap.receivable_formatted || '';
            receivable.classList.remove('text-amber-700', 'text-emerald-700');
            receivable.classList.add(toneClass(snap.receivable_tone || 'emerald'));
        }
        const payAmt = document.getElementById('ledgerReceivePaymentAmount');
        const rec = Number(snap.receivable ?? 0);
        if (payAmt instanceof HTMLInputElement) {
            if (rec > 0.00001) {
                payAmt.max = String(rec);
            } else {
                payAmt.removeAttribute('max');
            }
            payAmt.value = '';
        }
        const cylInput = document.getElementById('ledgerCylinderReturnQty');
        const owed = Number(snap.cylinders_owed ?? 0);
        if (cylInput instanceof HTMLInputElement) {
            if (owed > 0) {
                cylInput.max = String(owed);
            } else {
                cylInput.removeAttribute('max');
            }
            cylInput.value = '';
        }
    };

    document.querySelectorAll('.ledger-ajax-form').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (errEl) {
                errEl.classList.add('hidden');
                errEl.textContent = '';
            }
            const fd = new FormData(form);
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Request failed');
                }
                applyCustomerDetail(data.detail);
                if (!data.detail?.ok && data.snapshot) {
                    applySnapshot(data.snapshot);
                }
                const customerId = Number(fd.get('customer_id') || 0);
                notifyFinance(customerId);
                form.reset();
                const dateInput = form.querySelector('input[type="date"]');
                if (dateInput instanceof HTMLInputElement && !dateInput.value) {
                    dateInput.value = new Date().toISOString().slice(0, 10);
                }
            } catch (err) {
                if (errEl) {
                    errEl.textContent = err instanceof Error ? err.message : String(err);
                    errEl.classList.remove('hidden');
                }
            }
        });
    });
})();
</script>
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
