<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';

$pdo = db();
$invoiceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(400);
    exit('Invalid invoice ID');
}

$stmt = $pdo->prepare(
    "SELECT i.*, c.*
     FROM invoices i
     INNER JOIN customers c ON c.id = i.customer_id
     WHERE i.id = ?"
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found');
}

$customerName = (string) ($invoice['name'] ?? $invoice['full_name'] ?? 'Customer');
$customerPhone = (string) ($invoice['phone'] ?? '');
$invoiceNo = (string) ($invoice['invoice_no'] ?? ('INV-' . $invoiceId));
$invoiceDate = (string) ($invoice['issue_date'] ?? $invoice['created_at'] ?? date('Y-m-d'));
$dueDate = (string) ($invoice['due_date'] ?? '');
$total = (float) ($invoice['total_amount'] ?? 0);
$paid = (float) ($invoice['paid_amount'] ?? 0);
$remaining = (float) ($invoice['remaining_amount'] ?? max(0, $total - $paid));
$status = (string) ($invoice['status'] ?? ($remaining > 0 ? 'Pending' : 'Paid'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($invoiceNo) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #334155; padding: 24px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; max-width: 820px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .title { color: #0284C7; font-size: 22px; margin: 0; }
        .muted { color: #64748b; font-size: 13px; margin-top: 4px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; margin-bottom: 18px; }
        .row { font-size: 14px; }
        .label { color: #64748b; }
        .totals { border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 10px; }
        .btns { margin-top: 20px; display: flex; gap: 10px; }
        .btn { border: 0; background: #0EA5E9; color: #fff; border-radius: 8px; padding: 10px 14px; cursor: pointer; }
        .btn.secondary { background: #64748b; }
        @media print { .btns { display: none; } body { background: #fff; padding: 0; } .card { border: 0; } }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div>
            <h1 class="title">Afghan Oxygen Supply</h1>
            <div class="muted">Invoice Document</div>
        </div>
        <div>
            <div class="row"><span class="label">Invoice #:</span> <?= e($invoiceNo) ?></div>
            <div class="row"><span class="label">Date:</span> <?= e($invoiceDate) ?></div>
            <div class="row"><span class="label">Status:</span> <?= e($status) ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="row"><span class="label">Customer:</span> <?= e($customerName) ?></div>
        <div class="row"><span class="label">Phone:</span> <?= e($customerPhone) ?></div>
        <div class="row"><span class="label">Due Date:</span> <?= e($dueDate !== '' ? $dueDate : '-') ?></div>
        <div class="row"><span class="label">Address:</span> <?= e((string) ($invoice['address'] ?? '-')) ?></div>
    </div>

    <div class="totals">
        <div class="row"><span class="label">Total Amount:</span> <?= number_format($total, 2) ?></div>
        <div class="row"><span class="label">Paid Amount:</span> <?= number_format($paid, 2) ?></div>
        <div class="row"><span class="label">Remaining Amount:</span> <?= number_format($remaining, 2) ?></div>
    </div>

    <div class="btns">
        <button class="btn" onclick="window.print()">Export / Save as PDF</button>
        <button class="btn secondary" onclick="window.close()">Close</button>
    </div>
</div>
</body>
</html>
