<?php

declare(strict_types=1);

$tab = trim((string) ($_GET['tab'] ?? 'monthly'));
if ($tab === 'customer' || $tab === 'supplier') {
    header('Location: ?module=dashboard' . i18n_lang_query());
    exit;
}
if (!in_array($tab, ['monthly', 'yearly', 'customer', 'supplier'], true)) {
    $tab = 'monthly';
}

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$periodYear, $periodMonth] = array_map('intval', explode('-', $month, 2));
$periodMonth = max(1, min(12, $periodMonth));
$periodYear = max(2000, min(2100, $periodYear));
$month = sprintf('%04d-%02d', $periodYear, $periodMonth);

$year = (int) ($_GET['year'] ?? date('Y'));
$year = max(2000, min(2100, $year));

$customerId = (int) ($_GET['customer_id'] ?? 0);
$supplierId = (int) ($_GET['supplier_id'] ?? 0);
$customerPeriod = trim((string) ($_GET['customer_period'] ?? 'monthly'));
if (!in_array($customerPeriod, ['monthly', 'yearly'], true)) {
    $customerPeriod = 'monthly';
}
$customerMonth = trim((string) ($_GET['customer_month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $customerMonth)) {
    $customerMonth = date('Y-m');
}
[$customerPeriodYear, $customerPeriodMonth] = array_map('intval', explode('-', $customerMonth, 2));
$customerPeriodMonth = max(1, min(12, $customerPeriodMonth));
$customerPeriodYear = max(2000, min(2100, $customerPeriodYear));
$customerMonth = sprintf('%04d-%02d', $customerPeriodYear, $customerPeriodMonth);
$customerYear = (int) ($_GET['customer_year'] ?? date('Y'));
$customerYear = max(2000, min(2100, $customerYear));

$supplierPeriod = trim((string) ($_GET['supplier_period'] ?? 'monthly'));
if (!in_array($supplierPeriod, ['monthly', 'yearly'], true)) {
    $supplierPeriod = 'monthly';
}
$supplierMonth = trim((string) ($_GET['supplier_month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $supplierMonth)) {
    $supplierMonth = date('Y-m');
}
[$supplierPeriodYear, $supplierPeriodMonth] = array_map('intval', explode('-', $supplierMonth, 2));
$supplierPeriodMonth = max(1, min(12, $supplierPeriodMonth));
$supplierPeriodYear = max(2000, min(2100, $supplierPeriodYear));
$supplierMonth = sprintf('%04d-%02d', $supplierPeriodYear, $supplierPeriodMonth);
$supplierYear = (int) ($_GET['supplier_year'] ?? date('Y'));
$supplierYear = max(2000, min(2100, $supplierYear));

$hasInvoiceServiceId = table_exists($pdo, 'invoices') && column_exists($pdo, 'invoices', 'service_id');
$hasGrand = table_exists($pdo, 'services') && column_exists($pdo, 'services', 'grand_total');
$hasTotalBill = table_exists($pdo, 'services') && column_exists($pdo, 'services', 'total_bill');

/** Amount for one service row (matches list views / refill logic). */
$parts = [];
if ($hasGrand) {
    $parts[] = 's.grand_total';
}
if ($hasTotalBill) {
    $parts[] = 's.total_bill';
}
if ($hasInvoiceServiceId) {
    $parts[] = 'i.total_amount';
}
$parts[] = '(s.quantity * s.price)';
$serviceAmountExpr = 'COALESCE(' . implode(', ', $parts) . ')';

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$suppliers = table_exists($pdo, 'suppliers')
    ? $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll()
    : [];
$monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// --- CSV export (respects tab & filters) ---
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report-' . $tab . '-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Afghan Oxygen — Report export']);
    fputcsv($out, ['Generated', date('Y-m-d H:i')]);
    fputcsv($out, ['Tab', $tab]);

    if ($tab === 'monthly') {
        fputcsv($out, ['Month', $month]);
        $st = $pdo->prepare(
            "SELECT DAY(payment_date) AS d, COALESCE(SUM(amount), 0) AS total
             FROM payments WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?
             GROUP BY DAY(payment_date) ORDER BY d"
        );
        $st->execute([$periodYear, $periodMonth]);
        fputcsv($out, []);
        fputcsv($out, ['Payments by day', 'Amount']);
        while ($r = $st->fetch()) {
            fputcsv($out, [$r['d'], $r['total']]);
        }
        $st2 = $pdo->prepare(
            "SELECT s.service_type, COALESCE(SUM({$serviceAmountExpr}), 0) AS earnings
             FROM services s
             LEFT JOIN invoices i ON i.service_id = s.id
             WHERE YEAR(s.date) = ? AND MONTH(s.date) = ?
             GROUP BY s.service_type ORDER BY earnings DESC"
        );
        $st2->execute([$periodYear, $periodMonth]);
        fputcsv($out, []);
        fputcsv($out, ['Service type (orders in month)', 'Billed amount']);
        while ($r = $st2->fetch()) {
            fputcsv($out, [ucfirst((string) $r['service_type']), $r['earnings']]);
        }
    } elseif ($tab === 'yearly') {
        fputcsv($out, ['Year', (string) $year]);
        $st = $pdo->prepare(
            "SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount), 0) AS total
             FROM payments WHERE YEAR(payment_date) = ?
             GROUP BY MONTH(payment_date) ORDER BY m"
        );
        $st->execute([$year]);
        fputcsv($out, []);
        fputcsv($out, ['Month', 'Payments received']);
        while ($r = $st->fetch()) {
            fputcsv($out, [$r['m'], $r['total']]);
        }
        $st2 = $pdo->prepare(
            "SELECT MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}), 0) AS total
             FROM services s
             LEFT JOIN invoices i ON i.service_id = s.id
             WHERE YEAR(s.date) = ?
             GROUP BY MONTH(s.date) ORDER BY m"
        );
        $st2->execute([$year]);
        fputcsv($out, []);
        fputcsv($out, ['Month', 'Order billed total']);
        while ($r = $st2->fetch()) {
            fputcsv($out, [$r['m'], $r['total']]);
        }
    } elseif ($tab === 'customer') {
        fputcsv($out, ['Customer ID', (string) $customerId]);
        if ($customerId > 0) {
            if ($customerPeriod === 'yearly') {
                $st = $pdo->prepare(
                    "SELECT s.id, s.date, s.service_type, s.quantity,
                            COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt,
                            COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt,
                            {$serviceAmountExpr} AS line_total
                     FROM services s
                     LEFT JOIN invoices i ON i.service_id = s.id
                     WHERE s.customer_id = ? AND YEAR(s.date) = ?
                     ORDER BY s.date DESC, s.id DESC"
                );
                $st->execute([$customerId, $customerYear]);
            } else {
                $st = $pdo->prepare(
                    "SELECT s.id, s.date, s.service_type, s.quantity,
                            COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt,
                            COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt,
                            {$serviceAmountExpr} AS line_total
                     FROM services s
                     LEFT JOIN invoices i ON i.service_id = s.id
                     WHERE s.customer_id = ? AND YEAR(s.date) = ? AND MONTH(s.date) = ?
                     ORDER BY s.date DESC, s.id DESC"
                );
                $st->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Order ID', 'Date', 'Service', 'Qty', 'Line total', 'Paid', 'Remaining']);
            while ($r = $st->fetch()) {
                fputcsv($out, [
                    $r['id'],
                    $r['date'],
                    $r['service_type'],
                    $r['quantity'],
                    $r['line_total'],
                    $r['paid_amt'],
                    $r['rem_amt'],
                ]);
            }
        }
    } else {
        fputcsv($out, ['Supplier ID', (string) $supplierId]);
        if ($supplierId > 0 && table_exists($pdo, 'supplier_transactions')) {
            if ($supplierPeriod === 'yearly') {
                $st = $pdo->prepare(
                    "SELECT id, transaction_date, cylinder_type, sent_quantity, total_received, total_amount, paid_amount, remaining_amount, payment_status
                     FROM supplier_transactions
                     WHERE supplier_id = ? AND YEAR(transaction_date) = ?
                     ORDER BY transaction_date DESC, id DESC"
                );
                $st->execute([$supplierId, $supplierYear]);
            } else {
                $st = $pdo->prepare(
                    "SELECT id, transaction_date, cylinder_type, sent_quantity, total_received, total_amount, paid_amount, remaining_amount, payment_status
                     FROM supplier_transactions
                     WHERE supplier_id = ? AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?
                     ORDER BY transaction_date DESC, id DESC"
                );
                $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Purchase ID', 'Date', 'Sent', 'Received', 'Total', 'Paid', 'Remaining', 'Status']);
            while ($r = $st->fetch()) {
                fputcsv($out, [
                    $r['id'],
                    $r['transaction_date'],
                    $r['sent_quantity'],
                    $r['total_received'],
                    $r['total_amount'],
                    $r['paid_amount'],
                    $r['remaining_amount'],
                    $r['payment_status'],
                ]);
            }
        }
    }

    $pendingExport = $pdo->query(
        "SELECT i.id, c.name, i.total_amount, i.paid_amount, i.remaining_amount
         FROM invoices i
         INNER JOIN customers c ON c.id = i.customer_id
         WHERE i.remaining_amount > 0.00001
         ORDER BY i.id DESC"
    )->fetchAll();
    fputcsv($out, []);
    fputcsv($out, ['Pending invoices', 'Customer', 'Total', 'Paid', 'Remaining']);
    foreach ($pendingExport as $row) {
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['total_amount'],
            $row['paid_amount'],
            $row['remaining_amount'],
        ]);
    }

    fclose($out);
    exit;
}

// --- KPI cards (period-aware labels) ---
$globalOutstanding = (float) $pdo->query('SELECT COALESCE(SUM(remaining_amount),0) FROM invoices WHERE remaining_amount > 0.00001')->fetchColumn();

if ($tab === 'monthly') {
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?');
    $st->execute([$periodYear, $periodMonth]);
    $periodCollected = (float) $st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM({$serviceAmountExpr}),0)
         FROM services s
         LEFT JOIN invoices i ON i.service_id = s.id
         WHERE YEAR(s.date) = ? AND MONTH(s.date) = ?"
    );
    $st->execute([$periodYear, $periodMonth]);
    $periodInvoiced = (float) $st->fetchColumn();

    $kpiInvoiced = $periodInvoiced;
    $kpiCollected = $periodCollected;
    $kpiOutstanding = $globalOutstanding;
    $kpiInvoicedLabel = 'Billed (orders in ' . date('M Y', strtotime($month . '-01')) . ')';
    $kpiCollectedLabel = 'Collected (payments in ' . date('M Y', strtotime($month . '-01')) . ')';
} elseif ($tab === 'yearly') {
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date) = ?');
    $st->execute([$year]);
    $periodCollected = (float) $st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM({$serviceAmountExpr}),0)
         FROM services s
         LEFT JOIN invoices i ON i.service_id = s.id
         WHERE YEAR(s.date) = ?"
    );
    $st->execute([$year]);
    $periodInvoiced = (float) $st->fetchColumn();

    $kpiInvoiced = $periodInvoiced;
    $kpiCollected = $periodCollected;
    $kpiOutstanding = $globalOutstanding;
    $kpiInvoicedLabel = 'Billed (orders in ' . $year . ')';
    $kpiCollectedLabel = 'Collected (payments in ' . $year . ')';
} elseif ($tab === 'customer') {
    $kpiInvoiced = 0.0;
    $kpiCollected = 0.0;
    $kpiOutstanding = $globalOutstanding;
    $periodLabel = $customerPeriod === 'yearly' ? (string) $customerYear : date('M Y', strtotime($customerMonth . '-01'));
    $kpiInvoicedLabel = 'Customer billed (' . $periodLabel . ')';
    $kpiCollectedLabel = 'Customer paid (' . $periodLabel . ')';
    if ($customerId > 0) {
        if ($customerPeriod === 'yearly') {
            $st = $pdo->prepare(
                "SELECT COALESCE(SUM({$serviceAmountExpr}),0)
                 FROM services s
                 LEFT JOIN invoices i ON i.service_id = s.id
                 WHERE s.customer_id = ? AND YEAR(s.date) = ?"
            );
            $st->execute([$customerId, $customerYear]);
        } else {
            $st = $pdo->prepare(
                "SELECT COALESCE(SUM({$serviceAmountExpr}),0)
                 FROM services s
                 LEFT JOIN invoices i ON i.service_id = s.id
                 WHERE s.customer_id = ? AND YEAR(s.date) = ? AND MONTH(s.date) = ?"
            );
            $st->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
        }
        $kpiInvoiced = (float) $st->fetchColumn();

        if ($customerPeriod === 'yearly') {
            $st = $pdo->prepare(
                'SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? AND YEAR(p.payment_date) = ?'
            );
            $st->execute([$customerId, $customerYear]);
        } else {
            $st = $pdo->prepare(
                'SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.customer_id = ? AND YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?'
            );
            $st->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
        }
        $kpiCollected = (float) $st->fetchColumn();
    }
} else {
    $kpiInvoiced = 0.0;
    $kpiCollected = 0.0;
    $kpiOutstanding = 0.0;
    $periodLabel = $supplierPeriod === 'yearly' ? (string) $supplierYear : date('M Y', strtotime($supplierMonth . '-01'));
    $kpiInvoicedLabel = 'Supplier purchases (' . $periodLabel . ')';
    $kpiCollectedLabel = 'Supplier paid (' . $periodLabel . ')';
    if ($supplierId > 0 && table_exists($pdo, 'supplier_transactions')) {
        if ($supplierPeriod === 'yearly') {
            $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM supplier_transactions WHERE supplier_id = ? AND YEAR(transaction_date) = ?');
            $st->execute([$supplierId, $supplierYear]);
        } else {
            $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM supplier_transactions WHERE supplier_id = ? AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?');
            $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
        }
        $kpiInvoiced = (float) $st->fetchColumn();
        if ($supplierPeriod === 'yearly') {
            $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_id = ? AND YEAR(payment_date) = ?');
            $st->execute([$supplierId, $supplierYear]);
        } else {
            $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_id = ? AND YEAR(payment_date) = ? AND MONTH(payment_date) = ?');
            $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
        }
        $kpiCollected = (float) $st->fetchColumn();
        if ($supplierPeriod === 'yearly') {
            $st = $pdo->prepare('SELECT COALESCE(SUM(remaining_amount),0) FROM supplier_transactions WHERE supplier_id = ? AND YEAR(transaction_date) = ?');
            $st->execute([$supplierId, $supplierYear]);
        } else {
            $st = $pdo->prepare('SELECT COALESCE(SUM(remaining_amount),0) FROM supplier_transactions WHERE supplier_id = ? AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?');
            $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
        }
        $kpiOutstanding = (float) $st->fetchColumn();
    }
}

// --- Chart 1 & 2 (tab-specific) ---
if ($tab === 'monthly') {
    $lastDay = (int) date('t', strtotime($month . '-01'));
    $st = $pdo->prepare(
        'SELECT DAY(payment_date) AS d, COALESCE(SUM(amount), 0) AS total
         FROM payments WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?
         GROUP BY DAY(payment_date) ORDER BY d'
    );
    $st->execute([$periodYear, $periodMonth]);
    $dayMap = [];
    while ($r = $st->fetch()) {
        $dayMap[(int) $r['d']] = (float) $r['total'];
    }
    $chart1Labels = [];
    $chart1Data = [];
    for ($d = 1; $d <= $lastDay; $d++) {
        $chart1Labels[] = (string) $d;
        $chart1Data[] = $dayMap[$d] ?? 0.0;
    }
    $chart1Title = 'Payments by day — ' . date('F Y', strtotime($month . '-01'));

    $st = $pdo->prepare(
        "SELECT s.service_type, COALESCE(SUM({$serviceAmountExpr}), 0) AS earnings
         FROM services s
         LEFT JOIN invoices i ON i.service_id = s.id
         WHERE YEAR(s.date) = ? AND MONTH(s.date) = ?
         GROUP BY s.service_type ORDER BY earnings DESC"
    );
    $st->execute([$periodYear, $periodMonth]);
    $chart2Rows = $st->fetchAll();
    $chart2Title = 'Billed by service type — ' . date('F Y', strtotime($month . '-01'));
} elseif ($tab === 'yearly') {
    $st = $pdo->prepare(
        'SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount), 0) AS total
         FROM payments WHERE YEAR(payment_date) = ?
         GROUP BY MONTH(payment_date) ORDER BY m'
    );
    $st->execute([$year]);
    $payMap = [];
    while ($r = $st->fetch()) {
        $payMap[(int) $r['m']] = (float) $r['total'];
    }
    $chart1Labels = $monthShort;
    $chart1Data = [];
    for ($m = 1; $m <= 12; $m++) {
        $chart1Data[] = $payMap[$m] ?? 0.0;
    }
    $chart1Title = 'Payments by month — ' . $year;

    $st = $pdo->prepare(
        "SELECT MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}), 0) AS total
         FROM services s
         LEFT JOIN invoices i ON i.service_id = s.id
         WHERE YEAR(s.date) = ?
         GROUP BY MONTH(s.date) ORDER BY m"
    );
    $st->execute([$year]);
    $billMap = [];
    while ($r = $st->fetch()) {
        $billMap[(int) $r['m']] = (float) $r['total'];
    }
    $chart2Rows = [];
    for ($m = 1; $m <= 12; $m++) {
        $chart2Rows[] = ['month_num' => $m, 'total' => $billMap[$m] ?? 0.0];
    }
    $chart2Title = 'Order totals by month — ' . $year;
} elseif ($tab === 'customer') {
    $chart1Labels = [];
    $chart1Data = [];
    $chart2Rows = [];
    $chart1Title = $customerPeriod === 'yearly' ? 'Customer payments by month' : 'Customer payments by day';
    $chart2Title = $customerPeriod === 'yearly' ? 'Customer orders billed by month' : 'Customer orders billed by day';
    if ($customerId > 0) {
        if ($customerPeriod === 'yearly') {
            $st = $pdo->prepare(
                "SELECT MONTH(p.payment_date) AS m, COALESCE(SUM(p.amount), 0) AS total
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.customer_id = ? AND YEAR(p.payment_date) = ?
                 GROUP BY m
                 ORDER BY m"
            );
            $st->execute([$customerId, $customerYear]);
            $payMap = [];
            while ($r = $st->fetch()) {
                $payMap[(int) $r['m']] = (float) $r['total'];
            }
            $chart1Labels = $monthShort;
            for ($m = 1; $m <= 12; $m++) {
                $chart1Data[] = $payMap[$m] ?? 0.0;
            }

            $st = $pdo->prepare(
                "SELECT MONTH(s.date) AS m, COALESCE(SUM({$serviceAmountExpr}), 0) AS total
                 FROM services s
                 LEFT JOIN invoices i ON i.service_id = s.id
                 WHERE s.customer_id = ? AND YEAR(s.date) = ?
                 GROUP BY m
                 ORDER BY m"
            );
            $st->execute([$customerId, $customerYear]);
            $billMap = [];
            while ($r = $st->fetch()) {
                $billMap[(int) $r['m']] = (float) $r['total'];
            }
            for ($m = 1; $m <= 12; $m++) {
                $chart2Rows[] = ['y' => $customerYear, 'm' => $m, 'total' => $billMap[$m] ?? 0.0];
            }
        } else {
            $lastDay = (int) date('t', strtotime($customerMonth . '-01'));
            $st = $pdo->prepare(
                "SELECT DAY(p.payment_date) AS d, COALESCE(SUM(p.amount), 0) AS total
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.customer_id = ? AND YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?
                 GROUP BY d
                 ORDER BY d"
            );
            $st->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
            $dayPayMap = [];
            while ($r = $st->fetch()) {
                $dayPayMap[(int) $r['d']] = (float) $r['total'];
            }
            for ($d = 1; $d <= $lastDay; $d++) {
                $chart1Labels[] = (string) $d;
                $chart1Data[] = $dayPayMap[$d] ?? 0.0;
            }

            $st = $pdo->prepare(
                "SELECT DAY(s.date) AS d, COALESCE(SUM({$serviceAmountExpr}), 0) AS total
                 FROM services s
                 LEFT JOIN invoices i ON i.service_id = s.id
                 WHERE s.customer_id = ? AND YEAR(s.date) = ? AND MONTH(s.date) = ?
                 GROUP BY d
                 ORDER BY d"
            );
            $st->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
            $dayBillMap = [];
            while ($r = $st->fetch()) {
                $dayBillMap[(int) $r['d']] = (float) $r['total'];
            }
            for ($d = 1; $d <= $lastDay; $d++) {
                $chart2Rows[] = ['y' => $customerPeriodYear, 'm' => $d, 'total' => $dayBillMap[$d] ?? 0.0];
            }
        }
    } else {
        $chart1Labels = ['—'];
        $chart1Data = [0.0];
        $chart2Rows = [['y' => 0, 'm' => 0, 'total' => 0.0]];
    }
} else {
    $chart1Labels = [];
    $chart1Data = [];
    $chart2Rows = [];
    $chart1Title = $supplierPeriod === 'yearly' ? 'Supplier payments by month' : 'Supplier payments by day';
    $chart2Title = $supplierPeriod === 'yearly' ? 'Supplier purchases by month' : 'Supplier purchases by day';
    if ($supplierId > 0 && table_exists($pdo, 'supplier_payments')) {
        if ($supplierPeriod === 'yearly') {
            $st = $pdo->prepare(
                "SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount), 0) AS total
                 FROM supplier_payments
                 WHERE supplier_id = ? AND YEAR(payment_date) = ?
                 GROUP BY m
                 ORDER BY m"
            );
            $st->execute([$supplierId, $supplierYear]);
            $payMap = [];
            while ($r = $st->fetch()) {
                $payMap[(int) $r['m']] = (float) $r['total'];
            }
            $chart1Labels = $monthShort;
            for ($m = 1; $m <= 12; $m++) {
                $chart1Data[] = $payMap[$m] ?? 0.0;
            }

            $st = $pdo->prepare(
                "SELECT MONTH(transaction_date) AS m, COALESCE(SUM(total_amount), 0) AS total
                 FROM supplier_transactions
                 WHERE supplier_id = ? AND YEAR(transaction_date) = ?
                 GROUP BY m
                 ORDER BY m"
            );
            $st->execute([$supplierId, $supplierYear]);
            $billMap = [];
            while ($r = $st->fetch()) {
                $billMap[(int) $r['m']] = (float) $r['total'];
            }
            for ($m = 1; $m <= 12; $m++) {
                $chart2Rows[] = ['y' => $supplierYear, 'm' => $m, 'total' => $billMap[$m] ?? 0.0];
            }
        } else {
            $lastDay = (int) date('t', strtotime($supplierMonth . '-01'));
            $st = $pdo->prepare(
                "SELECT DAY(payment_date) AS d, COALESCE(SUM(amount), 0) AS total
                 FROM supplier_payments
                 WHERE supplier_id = ? AND YEAR(payment_date) = ? AND MONTH(payment_date) = ?
                 GROUP BY d
                 ORDER BY d"
            );
            $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
            $dayPayMap = [];
            while ($r = $st->fetch()) {
                $dayPayMap[(int) $r['d']] = (float) $r['total'];
            }
            for ($d = 1; $d <= $lastDay; $d++) {
                $chart1Labels[] = (string) $d;
                $chart1Data[] = $dayPayMap[$d] ?? 0.0;
            }

            $st = $pdo->prepare(
                "SELECT DAY(transaction_date) AS d, COALESCE(SUM(total_amount), 0) AS total
                 FROM supplier_transactions
                 WHERE supplier_id = ? AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?
                 GROUP BY d
                 ORDER BY d"
            );
            $st->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
            $dayBillMap = [];
            while ($r = $st->fetch()) {
                $dayBillMap[(int) $r['d']] = (float) $r['total'];
            }
            for ($d = 1; $d <= $lastDay; $d++) {
                $chart2Rows[] = ['y' => $supplierPeriodYear, 'm' => $d, 'total' => $dayBillMap[$d] ?? 0.0];
            }
        }
    } else {
        $chart1Labels = ['—'];
        $chart1Data = [0.0];
        $chart2Rows = [['y' => 0, 'm' => 0, 'total' => 0.0]];
    }
}

// --- Service-wise earnings (matches selected tab scope) ---
$svcWhere = '';
$svcParams = [];
if ($tab === 'monthly') {
    $svcWhere = 'WHERE YEAR(s.date) = ? AND MONTH(s.date) = ?';
    $svcParams = [$periodYear, $periodMonth];
} elseif ($tab === 'yearly') {
    $svcWhere = 'WHERE YEAR(s.date) = ?';
    $svcParams = [$year];
} elseif ($tab === 'customer' && $customerId > 0) {
    $svcWhere = 'WHERE s.customer_id = ?';
    $svcParams = [$customerId];
} elseif ($tab === 'supplier') {
    $svcWhere = 'WHERE 1=0';
}
$svcSql = "SELECT s.service_type, COALESCE(SUM({$serviceAmountExpr}), 0) AS earnings
     FROM services s
     LEFT JOIN invoices i ON i.service_id = s.id
     {$svcWhere}
     GROUP BY s.service_type
     ORDER BY earnings DESC";
$svcSt = $pdo->prepare($svcSql);
$svcSt->execute($svcParams);
$serviceWiseRows = $svcSt->fetchAll();
$serviceLabels = array_map(static fn(array $x): string => ucfirst((string) $x['service_type']), $serviceWiseRows);
$serviceEarningsData = array_map(static fn(array $x): float => (float) $x['earnings'], $serviceWiseRows);
if ($serviceLabels === []) {
    $serviceLabels = ['—'];
    $serviceEarningsData = [0.0];
}

// --- Chart 2 series for JS ---
if ($tab === 'monthly') {
    $chart2ChartJsType = 'doughnut';
    $chart2Labels = array_map(static fn(array $x): string => ucfirst((string) $x['service_type']), $chart2Rows);
    $chart2Data = array_map(static fn(array $x): float => (float) $x['earnings'], $chart2Rows);
    if ($chart2Labels === []) {
        $chart2Labels = ['—'];
        $chart2Data = [0.0];
    }
} elseif ($tab === 'yearly') {
    $chart2ChartJsType = 'line';
    $chart2Labels = $monthShort;
    $chart2Data = array_map(static fn(array $x): float => (float) $x['total'], $chart2Rows);
} else {
    $chart2ChartJsType = 'line';
    $chart2Labels = [];
    $chart2Data = [];
    foreach ($chart2Rows as $r) {
        $y = (int) ($r['y'] ?? 0);
        $m = (int) ($r['m'] ?? 0);
        if ($y < 1 || $m < 1) {
            continue;
        }
        if (($tab === 'customer' && $customerPeriod === 'monthly') || ($tab === 'supplier' && $supplierPeriod === 'monthly')) {
            $chart2Labels[] = (string) $m;
        } else {
            $chart2Labels[] = $monthShort[$m - 1] . ' ' . $y;
        }
        $chart2Data[] = (float) ($r['total'] ?? 0);
    }
    if ($chart2Labels === []) {
        $chart2Labels = ['—'];
        $chart2Data = [0.0];
    }
}

$pendingRows = $pdo->query(
    "SELECT i.id, c.name, i.total_amount, i.paid_amount, i.remaining_amount, i.status, i.created_at
     FROM invoices i
     INNER JOIN customers c ON c.id = i.customer_id
     WHERE i.remaining_amount > 0.00001
     ORDER BY i.created_at DESC"
)->fetchAll();
$pendingSearch = trim((string) ($_GET['pending_search'] ?? ''));

$exportQuery = http_build_query([
    'module' => 'reports',
    'export' => 'csv',
    'tab' => $tab,
    'month' => $month,
    'year' => $year,
    'customer_id' => $customerId,
    'supplier_id' => $supplierId,
    'customer_period' => $customerPeriod,
    'customer_month' => $customerMonth,
    'customer_year' => $customerYear,
    'supplier_period' => $supplierPeriod,
    'supplier_month' => $supplierMonth,
    'supplier_year' => $supplierYear,
]);

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
    <div class="flex flex-wrap gap-2 mb-3 items-center">
        <a href="?module=reports&amp;tab=monthly&amp;month=<?= e(urlencode($month)) ?>&amp;year=<?= (int) $year ?>&amp;customer_id=<?= (int) $customerId ?>&amp;supplier_id=<?= (int) $supplierId ?>&amp;customer_period=<?= e($customerPeriod) ?>&amp;customer_month=<?= e(urlencode($customerMonth)) ?>&amp;customer_year=<?= (int) $customerYear ?>&amp;supplier_period=<?= e($supplierPeriod) ?>&amp;supplier_month=<?= e(urlencode($supplierMonth)) ?>&amp;supplier_year=<?= (int) $supplierYear ?>" class="px-3 py-2 rounded-lg text-sm <?= $tab === 'monthly' ? 'bg-primary text-white' : 'bg-slate-100' ?>">Monthly Report</a>
        <a href="?module=reports&amp;tab=yearly&amp;month=<?= e(urlencode($month)) ?>&amp;year=<?= (int) $year ?>&amp;customer_id=<?= (int) $customerId ?>&amp;supplier_id=<?= (int) $supplierId ?>&amp;customer_period=<?= e($customerPeriod) ?>&amp;customer_month=<?= e(urlencode($customerMonth)) ?>&amp;customer_year=<?= (int) $customerYear ?>&amp;supplier_period=<?= e($supplierPeriod) ?>&amp;supplier_month=<?= e(urlencode($supplierMonth)) ?>&amp;supplier_year=<?= (int) $supplierYear ?>" class="px-3 py-2 rounded-lg text-sm <?= $tab === 'yearly' ? 'bg-primary text-white' : 'bg-slate-100' ?>">Yearly Report</a>
        <a href="?<?= htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-2 rounded-lg text-sm bg-info text-white no-underline inline-block">Export CSV</a>
    </div>
    <?php if ($tab === 'monthly'): ?>
        <form method="get" class="flex flex-wrap gap-2 mb-2">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="tab" value="monthly">
            <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierId ?>">
            <input type="hidden" name="customer_period" value="<?= e($customerPeriod) ?>">
            <input type="hidden" name="customer_month" value="<?= e($customerMonth) ?>">
            <input type="hidden" name="customer_year" value="<?= (int) $customerYear ?>">
            <input type="hidden" name="supplier_period" value="<?= e($supplierPeriod) ?>">
            <input type="hidden" name="supplier_month" value="<?= e($supplierMonth) ?>">
            <input type="hidden" name="supplier_year" value="<?= (int) $supplierYear ?>">
            <input type="month" name="month" value="<?= e($month) ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm">Apply</button>
        </form>
    <?php elseif ($tab === 'yearly'): ?>
        <form method="get" class="flex flex-wrap gap-2 mb-2">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="tab" value="yearly">
            <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierId ?>">
            <input type="hidden" name="customer_period" value="<?= e($customerPeriod) ?>">
            <input type="hidden" name="customer_month" value="<?= e($customerMonth) ?>">
            <input type="hidden" name="customer_year" value="<?= (int) $customerYear ?>">
            <input type="hidden" name="supplier_period" value="<?= e($supplierPeriod) ?>">
            <input type="hidden" name="supplier_month" value="<?= e($supplierMonth) ?>">
            <input type="hidden" name="supplier_year" value="<?= (int) $supplierYear ?>">
            <input type="number" min="2000" max="2100" name="year" value="<?= (int) $year ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm">Apply</button>
        </form>
    <?php elseif ($tab === 'customer'): ?>
        <form method="get" class="flex flex-wrap gap-2 mb-2">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="tab" value="customer">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierId ?>">
            <select name="customer_period" class="border rounded-lg px-3 py-2 text-sm">
                <option value="monthly" <?= $customerPeriod === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= $customerPeriod === 'yearly' ? 'selected' : '' ?>>Yearly</option>
            </select>
            <input type="<?= $customerPeriod === 'yearly' ? 'number' : 'month' ?>" name="<?= $customerPeriod === 'yearly' ? 'customer_year' : 'customer_month' ?>" value="<?= e($customerPeriod === 'yearly' ? (string) $customerYear : $customerMonth) ?>" class="border rounded-lg px-3 py-2 text-sm">
            <select name="customer_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="0">Select customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $customerId === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="supplier_period" value="<?= e($supplierPeriod) ?>">
            <input type="hidden" name="supplier_month" value="<?= e($supplierMonth) ?>">
            <input type="hidden" name="supplier_year" value="<?= (int) $supplierYear ?>">
            <button type="submit" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm">Apply</button>
        </form>
    <?php else: ?>
        <form method="get" class="flex flex-wrap gap-2 mb-2">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="tab" value="supplier">
            <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">
            <select name="supplier_period" class="border rounded-lg px-3 py-2 text-sm">
                <option value="monthly" <?= $supplierPeriod === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= $supplierPeriod === 'yearly' ? 'selected' : '' ?>>Yearly</option>
            </select>
            <input type="<?= $supplierPeriod === 'yearly' ? 'number' : 'month' ?>" name="<?= $supplierPeriod === 'yearly' ? 'supplier_year' : 'supplier_month' ?>" value="<?= e($supplierPeriod === 'yearly' ? (string) $supplierYear : $supplierMonth) ?>" class="border rounded-lg px-3 py-2 text-sm">
            <select name="supplier_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="0">Select supplier</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="customer_period" value="<?= e($customerPeriod) ?>">
            <input type="hidden" name="customer_month" value="<?= e($customerMonth) ?>">
            <input type="hidden" name="customer_year" value="<?= (int) $customerYear ?>">
            <button type="submit" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm">Apply</button>
        </form>
    <?php endif; ?>
</section>
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500"><?= e($kpiInvoicedLabel) ?></p>
        <p class="text-2xl font-bold text-oxygenDeep"><?= e(format_currency($kpiInvoiced)) ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500"><?= e($kpiCollectedLabel) ?></p>
        <p class="text-2xl font-bold text-success"><?= e(format_currency($kpiCollected)) ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500"><?= e($tab === 'supplier' ? 'Outstanding (selected supplier)' : 'Outstanding (all invoices)') ?></p>
        <p class="text-2xl font-bold text-rose-500"><?= e(format_currency(max(0, $kpiOutstanding))) ?></p>
    </div>
</section>
<?php if ($tab === 'customer' && $customerId > 0):
    $cylSql = '';
    if (table_exists($pdo, 'service_cylinder_rows')) {
        $cylSql = ", COALESCE((SELECT GROUP_CONCAT(CONCAT(r.sent_qty, '/', r.received_qty, ' @ ', ROUND(r.sale_pressure, 2), ' Bar') SEPARATOR ' | ') FROM service_cylinder_rows r WHERE r.service_id = s.id), '') AS cylinder_summary";
    } else {
        $cylSql = ", '' AS cylinder_summary";
    }
    $customerOrdersSt = $pdo->prepare(
        "SELECT s.id, s.date, s.service_type, s.quantity, s.price,
                COALESCE(s.paid_amount, i.paid_amount, 0) AS paid_amt,
                COALESCE(s.remaining_balance, i.remaining_amount, 0) AS rem_amt,
                {$serviceAmountExpr} AS line_total
                {$cylSql}
         FROM services s
         LEFT JOIN invoices i ON i.service_id = s.id
         WHERE s.customer_id = ?
         " . ($customerPeriod === 'yearly' ? "AND YEAR(s.date) = ?" : "AND YEAR(s.date) = ? AND MONTH(s.date) = ?") . "
         ORDER BY s.date DESC, s.id DESC"
    );
    if ($customerPeriod === 'yearly') {
        $customerOrdersSt->execute([$customerId, $customerYear]);
    } else {
        $customerOrdersSt->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
    }
    $customerOrders = $customerOrdersSt->fetchAll();
    ?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-200 font-semibold">Customer orders</div>
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[900px]">
            <thead class="bg-slate-50"><tr><th data-sort class="text-left p-3">Date</th><th data-sort class="text-left p-3">Order</th><th data-sort class="text-left p-3">Service</th><th class="text-left p-3">Cylinders</th><th data-sort class="text-left p-3">Qty</th><th data-sort class="text-left p-3">Line total</th><th data-sort class="text-left p-3">Paid</th><th data-sort class="text-left p-3">Remaining</th></tr></thead>
            <tbody>
            <?php if (!$customerOrders): ?><tr><td colspan="8" class="p-4 text-slate-500">No orders for selected customer.</td></tr><?php endif; ?>
            <?php foreach ($customerOrders as $r): ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3"><?= e(format_date_pk((string) $r['date'])) ?></td>
                    <td class="p-3">ORD-<?= (int) $r['id'] ?></td>
                    <td class="p-3"><?= e(ucfirst((string) $r['service_type'])) ?></td>
                    <td class="p-3 text-xs text-slate-600"><?= e((string) ($r['cylinder_summary'] !== '' ? $r['cylinder_summary'] : '—')) ?></td>
                    <td class="p-3"><?= (int) $r['quantity'] ?></td>
                    <td class="p-3"><?= e(format_currency((float) $r['line_total'])) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $r['paid_amt'])) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $r['rem_amt'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php if ($tab === 'customer' && $customerId > 0):
    $customerPayments = $pdo->prepare(
        "SELECT p.payment_date, p.amount, i.id AS invoice_id
         FROM payments p
         INNER JOIN invoices i ON i.id = p.invoice_id
         WHERE i.customer_id = ?
         " . ($customerPeriod === 'yearly' ? "AND YEAR(p.payment_date) = ?" : "AND YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?") . "
         ORDER BY p.payment_date DESC, p.id DESC"
    );
    if ($customerPeriod === 'yearly') {
        $customerPayments->execute([$customerId, $customerYear]);
    } else {
        $customerPayments->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
    }
    $customerPaymentRows = $customerPayments->fetchAll();
    $customerLedger = $pdo->prepare(
        "SELECT date, description, debit, credit, balance
         FROM ledger
         WHERE customer_id = ?
         " . ($customerPeriod === 'yearly' ? "AND YEAR(date) = ?" : "AND YEAR(date) = ? AND MONTH(date) = ?") . "
         ORDER BY id DESC"
    );
    if ($customerPeriod === 'yearly') {
        $customerLedger->execute([$customerId, $customerYear]);
    } else {
        $customerLedger->execute([$customerId, $customerPeriodYear, $customerPeriodMonth]);
    }
    $customerLedgerRows = $customerLedger->fetchAll();
?>
<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Customer payments</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[620px]">
                <thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Invoice</th><th class="text-left p-3">Amount</th></tr></thead>
                <tbody>
                <?php if (!$customerPaymentRows): ?><tr><td colspan="3" class="p-4 text-slate-500">No payments for selected customer.</td></tr><?php endif; ?>
                <?php foreach ($customerPaymentRows as $p): ?>
                    <tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $p['payment_date'])) ?></td><td class="p-3">INV-<?= (int) $p['invoice_id'] ?></td><td class="p-3"><?= e(format_currency((float) $p['amount'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Customer ledger</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Description</th><th class="text-left p-3">Debit</th><th class="text-left p-3">Credit</th><th class="text-left p-3">Balance</th></tr></thead>
                <tbody>
                <?php if (!$customerLedgerRows): ?><tr><td colspan="5" class="p-4 text-slate-500">No ledger rows for selected customer.</td></tr><?php endif; ?>
                <?php foreach ($customerLedgerRows as $l): ?>
                    <tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $l['date'])) ?></td><td class="p-3"><?= e((string) ($l['description'] ?? '—')) ?></td><td class="p-3"><?= e(format_currency((float) $l['debit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['credit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['balance'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if ($tab === 'supplier' && $supplierId > 0 && table_exists($pdo, 'supplier_transactions')):
    $supplierPurchases = $pdo->prepare(
        "SELECT id, transaction_date, cylinder_type, sent_quantity, total_received, total_amount, paid_amount, remaining_amount, payment_status
         FROM supplier_transactions
         WHERE supplier_id = ?
         " . ($supplierPeriod === 'yearly' ? "AND YEAR(transaction_date) = ?" : "AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?") . "
         ORDER BY transaction_date DESC, id DESC"
    );
    if ($supplierPeriod === 'yearly') {
        $supplierPurchases->execute([$supplierId, $supplierYear]);
    } else {
        $supplierPurchases->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
    }
    $supplierPurchaseRows = $supplierPurchases->fetchAll();
    $supplierPaymentsRows = [];
    if (table_exists($pdo, 'supplier_payments')) {
        $sp = $pdo->prepare("SELECT payment_date, transaction_id, amount, payment_type FROM supplier_payments WHERE supplier_id = ? " . ($supplierPeriod === 'yearly' ? "AND YEAR(payment_date) = ?" : "AND YEAR(payment_date) = ? AND MONTH(payment_date) = ?") . " ORDER BY payment_date DESC, id DESC");
        if ($supplierPeriod === 'yearly') {
            $sp->execute([$supplierId, $supplierYear]);
        } else {
            $sp->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
        }
        $supplierPaymentsRows = $sp->fetchAll();
    }
    $supplierLedgerRows = [];
    if (table_exists($pdo, 'supplier_ledger')) {
        $sl = $pdo->prepare("SELECT entry_date, description, debit, credit, balance FROM supplier_ledger WHERE supplier_id = ? " . ($supplierPeriod === 'yearly' ? "AND YEAR(entry_date) = ?" : "AND YEAR(entry_date) = ? AND MONTH(entry_date) = ?") . " ORDER BY id DESC");
        if ($supplierPeriod === 'yearly') {
            $sl->execute([$supplierId, $supplierYear]);
        } else {
            $sl->execute([$supplierId, $supplierPeriodYear, $supplierPeriodMonth]);
        }
        $supplierLedgerRows = $sl->fetchAll();
    }
?>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-200 font-semibold">Supplier purchases</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[1080px]">
            <thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Purchase</th><th class="text-left p-3">Sent</th><th class="text-left p-3">Received</th><th class="text-left p-3">Total</th><th class="text-left p-3">Paid</th><th class="text-left p-3">Remaining</th><th class="text-left p-3">Status</th></tr></thead>
            <tbody>
            <?php if (!$supplierPurchaseRows): ?><tr><td colspan="8" class="p-4 text-slate-500">No purchases for selected supplier.</td></tr><?php endif; ?>
            <?php foreach ($supplierPurchaseRows as $r): ?>
                <tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $r['transaction_date'])) ?></td><td class="p-3">SP-<?= (int) $r['id'] ?></td><td class="p-3"><?= (int) $r['sent_quantity'] ?></td><td class="p-3"><?= (int) $r['total_received'] ?></td><td class="p-3"><?= e(format_currency((float) $r['total_amount'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['paid_amount'])) ?></td><td class="p-3"><?= e(format_currency((float) $r['remaining_amount'])) ?></td><td class="p-3"><?= e((string) $r['payment_status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Supplier payments</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[680px]">
                <thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Purchase</th><th class="text-left p-3">Payment type</th><th class="text-left p-3">Amount</th></tr></thead>
                <tbody>
                <?php if (!$supplierPaymentsRows): ?><tr><td colspan="4" class="p-4 text-slate-500">No payments for selected supplier.</td></tr><?php endif; ?>
                <?php foreach ($supplierPaymentsRows as $p): ?>
                    <tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $p['payment_date'])) ?></td><td class="p-3">SP-<?= (int) $p['transaction_id'] ?></td><td class="p-3"><?= e((string) $p['payment_type']) ?></td><td class="p-3"><?= e(format_currency((float) $p['amount'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Supplier ledger</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-slate-50"><tr><th class="text-left p-3">Date</th><th class="text-left p-3">Description</th><th class="text-left p-3">Debit</th><th class="text-left p-3">Credit</th><th class="text-left p-3">Balance</th></tr></thead>
                <tbody>
                <?php if (!$supplierLedgerRows): ?><tr><td colspan="5" class="p-4 text-slate-500">No ledger rows for selected supplier.</td></tr><?php endif; ?>
                <?php foreach ($supplierLedgerRows as $l): ?>
                    <tr class="border-t border-slate-100"><td class="p-3"><?= e(format_date_pk((string) $l['entry_date'])) ?></td><td class="p-3"><?= e((string) ($l['description'] ?? '—')) ?></td><td class="p-3"><?= e(format_currency((float) $l['debit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['credit'])) ?></td><td class="p-3"><?= e(format_currency((float) $l['balance'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-3"><?= e($chart1Title) ?></h3>
        <div class="h-72"><canvas id="chartPrimary"></canvas></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-3"><?= e($chart2Title) ?></h3>
        <div class="h-72"><canvas id="chartSecondary"></canvas></div>
    </div>
</section>

<?php if ($tab !== 'supplier'): ?>
<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-3">Service-wise earnings<?= $tab === 'monthly' ? ' (this month)' : ($tab === 'yearly' ? ' (this year)' : ($customerId > 0 ? ' (this customer)' : ' (all customers)')) ?></h3>
        <div class="h-72"><canvas id="serviceEarningsChart"></canvas></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden xl:col-span-2">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Pending Payments Report</div>
        <form method="get" class="p-3 border-b border-slate-200 flex flex-wrap gap-2">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierId ?>">
            <input type="hidden" name="customer_period" value="<?= e($customerPeriod) ?>">
            <input type="hidden" name="customer_month" value="<?= e($customerMonth) ?>">
            <input type="hidden" name="customer_year" value="<?= (int) $customerYear ?>">
            <input type="hidden" name="supplier_period" value="<?= e($supplierPeriod) ?>">
            <input type="hidden" name="supplier_month" value="<?= e($supplierMonth) ?>">
            <input type="hidden" name="supplier_year" value="<?= (int) $supplierYear ?>">
            <input name="pending_search" value="<?= e($pendingSearch) ?>" placeholder="Search customer or invoice #" class="flex-1 min-w-[200px] border rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="bg-slate-700 text-white px-3 py-2 rounded-lg text-sm">Search</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[680px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-3">Invoice ID</th>
                        <th class="text-left p-3">Customer</th>
                        <th class="text-left p-3">Total</th>
                        <th class="text-left p-3">Paid</th>
                        <th class="text-left p-3">Remaining</th>
                        <th class="text-left p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pendingRows): ?>
                    <tr><td colspan="6" class="p-4 text-slate-500">No pending payments.</td></tr>
                <?php else: ?>
                    <?php
                    $pendingShown = 0;
                    foreach ($pendingRows as $row):
                        if (
                            $pendingSearch !== '' &&
                            stripos((string) $row['name'], $pendingSearch) === false &&
                            stripos((string) $row['id'], $pendingSearch) === false
                        ) {
                            continue;
                        }
                        $pendingShown++;
                        $pendStatus = payment_status_from_amounts((float) $row['total_amount'], (float) $row['paid_amount']);
                        ?>
                        <tr class="border-t border-slate-100">
                            <td class="p-3">#<?= (int) $row['id'] ?></td>
                            <td class="p-3"><?= e((string) $row['name']) ?></td>
                            <td class="p-3"><?= e(format_currency((float) $row['total_amount'])) ?></td>
                            <td class="p-3"><?= e(format_currency((float) $row['paid_amount'])) ?></td>
                            <td class="p-3 text-amber-600 font-medium"><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs <?= $pendStatus === 'Paid' ? 'status-paid' : ($pendStatus === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e($pendStatus) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($pendingShown === 0 && $pendingSearch !== ''): ?>
                        <tr><td colspan="6" class="p-4 text-slate-500">No rows match your search.</td></tr>
                    <?php endif; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200 font-semibold">Pending Payment Details</div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[480px]">
        <thead class="bg-slate-50">
            <tr><th class="text-left p-3">Customer</th><th class="text-left p-3">Outstanding</th></tr>
        </thead>
        <tbody>
            <?php if (!$pendingRows): ?><tr><td colspan="2" class="p-4 text-slate-500">No pending report data available.</td></tr><?php endif; ?>
            <?php foreach ($pendingRows as $row): ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3"><?= e((string) $row['name']) ?></td>
                    <td class="p-3"><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chartLabelAmount = <?= json_encode(__('chart.amount_afn'), JSON_UNESCAPED_UNICODE) ?>;
    const chartLabelBilled = <?= json_encode(__('chart.billed_afn'), JSON_UNESCAPED_UNICODE) ?>;
    const chart1Labels = <?= json_encode($chart1Labels, JSON_UNESCAPED_UNICODE) ?>;
    const chart1Data = <?= json_encode($chart1Data, JSON_UNESCAPED_UNICODE) ?>;
    const chart2Type = <?= json_encode($chart2ChartJsType, JSON_UNESCAPED_UNICODE) ?>;
    const chart2Labels = <?= json_encode($chart2Labels, JSON_UNESCAPED_UNICODE) ?>;
    const chart2Data = <?= json_encode($chart2Data, JSON_UNESCAPED_UNICODE) ?>;
    const serviceLabels = <?= json_encode($serviceLabels, JSON_UNESCAPED_UNICODE) ?>;
    const serviceEarningsData = <?= json_encode($serviceEarningsData, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('chartPrimary'), {
        type: 'bar',
        data: { labels: chart1Labels, datasets: [{ label: chartLabelAmount, data: chart1Data, backgroundColor: '#0EA5E9' }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    if (chart2Type === 'doughnut') {
        new Chart(document.getElementById('chartSecondary'), {
            type: 'doughnut',
            data: { labels: chart2Labels, datasets: [{ data: chart2Data, backgroundColor: ['#0EA5E9', '#0284C7', '#22C55E', '#0F766E', '#7C3AED', '#EA580C'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    } else {
        new Chart(document.getElementById('chartSecondary'), {
            type: 'line',
            data: { labels: chart2Labels, datasets: [{ label: chartLabelBilled, data: chart2Data, borderColor: '#0284C7', backgroundColor: 'rgba(2,132,199,0.12)', fill: true, tension: 0.35 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const serviceChartEl = document.getElementById('serviceEarningsChart');
    if (serviceChartEl) {
        new Chart(serviceChartEl, {
            type: 'doughnut',
            data: { labels: serviceLabels, datasets: [{ data: serviceEarningsData, backgroundColor: ['#0EA5E9', '#0284C7', '#22C55E', '#0F766E', '#7C3AED', '#EA580C'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.reports'), $content);
