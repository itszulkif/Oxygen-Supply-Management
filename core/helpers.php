<?php

require_once __DIR__ . '/db.php';

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
        date_default_timezone_set($config['timezone'] ?? 'UTC');
    }
    return $config;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $module): void
{
    header('Location: ?module=' . urlencode($module) . i18n_lang_query());
    exit;
}

function request_value(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function format_currency(float $amount): string
{
    return __('currency.symbol') . ' ' . number_format($amount, 2, '.', ',');
}

function format_date_pk(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    return date('d/m/Y', $ts);
}

function format_datetime_pk(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    return date('d/m/Y H:i', $ts);
}

/** Sortable occurred-at timestamp (prefers DB created_at, else date + stable id offset). */
function financial_transaction_occurred_at(string $dateYmd, mixed $createdAt, int $refId): string
{
    if ($createdAt !== null && $createdAt !== '') {
        $ts = strtotime((string) $createdAt);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
        $dateYmd = date('Y-m-d');
    }
    $seconds = max(0, min(86399, $refId % 86400));

    return $dateYmd . ' ' . gmdate('H:i:s', $seconds);
}

/** @param array<string, mixed> $tx */
function financial_enrich_transaction_row(array $tx): array
{
    $refId = (int) ($tx['ref_id'] ?? 0);
    $date = (string) ($tx['date'] ?? '');
    $occurredAt = financial_transaction_occurred_at($date, $tx['created_at'] ?? null, $refId);
    $tx['occurred_at'] = $occurredAt;
    $tx['time_formatted'] = format_datetime_pk($occurredAt);
    $tx['date_formatted'] = format_date_pk($date);

    return $tx;
}

/**
 * @param list<array<string, mixed>> $transactions
 * @return list<array<string, mixed>>
 */
function financial_sort_transactions_timeline(array $transactions): array
{
    $rows = array_map('financial_enrich_transaction_row', $transactions);
    usort($rows, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($b['ref_id'] ?? 0) <=> (int) ($a['ref_id'] ?? 0);
    });

    return $rows;
}

function payment_status_from_amounts(float $total, float $paid): string
{
    if ($paid >= $total && $total > 0) {
        return 'Paid';
    }
    if ($paid > 0) {
        return 'Partial';
    }
    return 'Due';
}

function payment_status_label(string $status): string
{
    return match ($status) {
        'Paid' => __('status.paid'),
        'Partial' => __('status.partial'),
        'Due' => __('status.due'),
        default => $status,
    };
}

/**
 * Customer-level settlement status (orders + opening balance) for ledger list UI.
 *
 * @return array{
 *   code: string,
 *   label: string,
 *   class: string,
 *   text_class: string,
 *   show_amount: bool
 * }
 */
function customer_settlement_status_display(PDO $pdo, int $customerId, float $receivable): array
{
    if ($receivable <= 0.00001) {
        return [
            'code' => 'Paid',
            'label' => payment_status_label('Paid'),
            'class' => 'status-paid',
            'text_class' => 'text-emerald-600 font-semibold',
            'show_amount' => false,
        ];
    }
    $credited = 0.0;
    if ($customerId > 0 && table_exists($pdo, 'ledger')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(credit), 0) FROM ledger WHERE customer_id = ?');
        $st->execute([$customerId]);
        $credited = (float) $st->fetchColumn();
    }
    $code = $credited > 0.00001 ? 'Partial' : 'Due';

    return [
        'code' => $code,
        'label' => payment_status_label($code),
        'class' => $code === 'Partial' ? 'status-partial' : 'status-due',
        'text_class' => 'text-amber-600 font-semibold',
        'show_amount' => true,
    ];
}

/**
 * Per-customer due figures for the customer ledger list (includes opening balance in outstanding).
 *
 * @param list<int> $customerIds
 * @return array<int, array{
 *   due_payment: float,
 *   invoice_remaining: float,
 *   remaining_balance: float,
 *   status_code: string,
 *   status_label: string,
 *   status_class: string,
 *   status_text_class: string,
 *   status_show_amount: bool
 * }>
 */
function customer_ledger_due_map_for_ids(PDO $pdo, array $customerIds): array
{
    $map = [];
    foreach ($customerIds as $id) {
        $cid = (int) $id;
        if ($cid <= 0) {
            continue;
        }
        $map[$cid] = [
            'due_payment' => 0.0,
            'invoice_remaining' => 0.0,
            'remaining_balance' => 0.0,
            'status_code' => 'Paid',
            'status_label' => payment_status_label('Paid'),
            'status_class' => 'status-paid',
            'status_text_class' => 'text-emerald-600 font-semibold',
            'status_show_amount' => false,
        ];
    }
    if ($map === []) {
        return [];
    }
    if (table_exists($pdo, 'invoices')) {
        $invoiceTotalExpr = column_exists($pdo, 'invoices', 'total_amount')
            ? 'COALESCE(total_amount, 0)'
            : 'COALESCE(grand_total, 0)';
        $ph = implode(',', array_fill(0, count($map), '?'));
        $dueSt = $pdo->prepare(
            "SELECT customer_id, COALESCE(SUM({$invoiceTotalExpr}), 0) AS due_payment,
                    COALESCE(SUM(remaining_amount), 0) AS invoice_remaining
             FROM invoices WHERE customer_id IN ($ph) GROUP BY customer_id"
        );
        $dueSt->execute(array_keys($map));
        foreach ($dueSt->fetchAll() as $row) {
            $cid = (int) ($row['customer_id'] ?? 0);
            if ($cid <= 0 || !isset($map[$cid])) {
                continue;
            }
            $map[$cid]['due_payment'] = (float) ($row['due_payment'] ?? 0);
            $map[$cid]['invoice_remaining'] = (float) ($row['invoice_remaining'] ?? 0);
        }
    }
    $receivableMap = customer_receivable_map($pdo, array_keys($map));
    foreach (array_keys($map) as $cid) {
        $receivable = (float) ($receivableMap[$cid]['receivable'] ?? 0);
        $status = customer_settlement_status_display($pdo, $cid, $receivable);
        $map[$cid]['remaining_balance'] = $receivable;
        $map[$cid]['status_code'] = $status['code'];
        $map[$cid]['status_label'] = $status['label'];
        $map[$cid]['status_class'] = $status['class'];
        $map[$cid]['status_text_class'] = $status['text_class'];
        $map[$cid]['status_show_amount'] = $status['show_amount'];
    }

    return $map;
}

function dashboard_counts(): array
{
    $pdo = db();
    $counts = [];
    $counts['customers'] = safe_count_query($pdo, 'SELECT COUNT(*) FROM customers');
    $counts['services'] = safe_count_query($pdo, 'SELECT COUNT(*) FROM services');

    if (table_exists($pdo, 'cylinders')) {
        $counts['available_cylinders'] = safe_count_query($pdo, 'SELECT COALESCE(SUM(available),0) FROM cylinders');
    } elseif (table_exists($pdo, 'inventory_cylinders')) {
        $counts['available_cylinders'] = safe_count_query($pdo, "SELECT COUNT(*) FROM inventory_cylinders WHERE status = 'Available'");
    } else {
        $counts['available_cylinders'] = 0;
    }

    if (table_exists($pdo, 'invoices')) {
        if (column_exists($pdo, 'invoices', 'status')) {
            $counts['unpaid_invoices'] = safe_count_query($pdo, "SELECT COUNT(*) FROM invoices WHERE status <> 'Paid'");
        } else {
            $counts['unpaid_invoices'] = safe_count_query($pdo, "SELECT COUNT(*) FROM invoices");
        }
    } else {
        $counts['unpaid_invoices'] = 0;
    }

    return $counts;
}

function safe_count_query(PDO $pdo, string $sql): int
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tableName]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$tableName, $columnName]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Sum of opening-balance liabilities still owed to all suppliers (AFN), after opening-only payments. */
function supplier_opening_liability_sum(PDO $pdo): float
{
    if (!table_exists($pdo, 'suppliers') || !column_exists($pdo, 'suppliers', 'opening_balance')) {
        return 0.0;
    }
    if (!table_exists($pdo, 'supplier_payments')) {
        return max(0.0, (float) $pdo->query('SELECT COALESCE(SUM(opening_balance), 0) FROM suppliers')->fetchColumn());
    }
    $sql = 'SELECT COALESCE(SUM(GREATEST(0, s.opening_balance - COALESCE(op.paid, 0))), 0)
            FROM suppliers s
            LEFT JOIN (
                SELECT supplier_id, SUM(amount) AS paid
                FROM supplier_payments
                WHERE transaction_id IS NULL OR transaction_id = 0
                GROUP BY supplier_id
            ) op ON op.supplier_id = s.id';

    return max(0.0, (float) $pdo->query($sql)->fetchColumn());
}

/** Amount still owed to one supplier: opening liability + purchases − payments. */
function supplier_amount_owed(float $openingBalance, float $purchases, float $paid): float
{
    return max(0.0, $openingBalance + $purchases - $paid);
}

/** Per-supplier balance still owed (opening remaining + unpaid purchase balances). */
function supplier_outstanding_balance(PDO $pdo, int $supplierId): float
{
    if ($supplierId <= 0) {
        return 0.0;
    }
    $openingRemaining = supplier_opening_balance_remaining($pdo, $supplierId);
    if (table_exists($pdo, 'supplier_transactions') && column_exists($pdo, 'supplier_transactions', 'remaining_amount')) {
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(remaining_amount), 0)
             FROM supplier_transactions
             WHERE supplier_id = ? AND remaining_amount > 0.00001'
        );
        $st->execute([$supplierId]);

        return max(0.0, $openingRemaining + (float) $st->fetchColumn());
    }
    $purchases = 0.0;
    $paid = 0.0;
    if (table_exists($pdo, 'supplier_transactions')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) FROM supplier_transactions WHERE supplier_id = ?');
        $st->execute([$supplierId]);
        $purchases = (float) $st->fetchColumn();
    }
    if (table_exists($pdo, 'supplier_payments')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE supplier_id = ?');
        $st->execute([$supplierId]);
        $paid = (float) $st->fetchColumn();
    }

    return supplier_amount_owed($openingRemaining, $purchases, $paid);
}

/** Cash paid against supplier opening balance only (not linked to a purchase). */
function supplier_opening_payments_paid(PDO $pdo, int $supplierId): float
{
    if ($supplierId <= 0 || !table_exists($pdo, 'supplier_payments')) {
        return 0.0;
    }
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM supplier_payments
         WHERE supplier_id = ? AND (transaction_id IS NULL OR transaction_id = 0)'
    );
    $st->execute([$supplierId]);

    return (float) $st->fetchColumn();
}

/** Opening balance still owed to this supplier (pending liability). */
function supplier_opening_balance_remaining(PDO $pdo, int $supplierId): float
{
    if ($supplierId <= 0 || !table_exists($pdo, 'suppliers') || !column_exists($pdo, 'suppliers', 'opening_balance')) {
        return 0.0;
    }
    $st = $pdo->prepare('SELECT opening_balance FROM suppliers WHERE id = ? LIMIT 1');
    $st->execute([$supplierId]);
    $opening = max(0.0, (float) ($st->fetchColumn() ?: 0));

    return max(0.0, $opening - supplier_opening_payments_paid($pdo, $supplierId));
}

/** Supplier cash payments (money paid out to suppliers) for a date range. */
function financial_sum_supplier_payments_total(PDO $pdo, string $from, string $to): float
{
    if (!table_exists($pdo, 'supplier_payments')) {
        return 0.0;
    }
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM supplier_payments
         WHERE payment_date >= ? AND payment_date <= ?'
    );
    $st->execute([$from, $to]);

    return (float) $st->fetchColumn();
}

/** Customer ledger balance: positive = customer owes you (receivable). */
function customer_receivable_balance(PDO $pdo, int $customerId): float
{
    if ($customerId <= 0 || !table_exists($pdo, 'ledger')) {
        return 0.0;
    }
    $st = $pdo->prepare('SELECT balance FROM ledger WHERE customer_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$customerId]);
    return (float) ($st->fetch()['balance'] ?? 0);
}

/** Opening balance and cylinders from the initial ledger row. */
function customer_opening_ledger_row(PDO $pdo, int $customerId): array
{
    $out = ['opening_balance' => 0.0, 'opening_cylinders' => 0];
    if ($customerId <= 0 || !table_exists($pdo, 'ledger')) {
        return $out;
    }
    $cols = 'debit';
    if (column_exists($pdo, 'ledger', 'cylinders_baqi')) {
        $cols .= ', cylinders_baqi';
    }
    $st = $pdo->prepare("SELECT {$cols} FROM ledger WHERE customer_id = ? AND reference_type = 'opening_balance' ORDER BY id ASC LIMIT 1");
    $st->execute([$customerId]);
    $row = $st->fetch();
    if ($row) {
        $out['opening_balance'] = max(0, (float) ($row['debit'] ?? 0));
        $out['opening_cylinders'] = max(0, (int) ($row['cylinders_baqi'] ?? 0));
    }
    return $out;
}

/** Cylinders returned via ledger settlement (not tied to an order). */
function customer_cylinder_settlements_total(PDO $pdo, int $customerId): int
{
    if ($customerId <= 0 || !table_exists($pdo, 'ledger') || !column_exists($pdo, 'ledger', 'cylinders_received')) {
        return 0;
    }
    $st = $pdo->prepare("SELECT COALESCE(SUM(cylinders_received), 0) FROM ledger WHERE customer_id = ? AND reference_type = 'cylinder_settlement'");
    $st->execute([$customerId]);
    return max(0, (int) $st->fetchColumn());
}

/** Net cylinders owed from orders only (sent − received, floored at 0). */
function customer_order_cylinder_net(PDO $pdo, int $customerId): int
{
    if ($customerId <= 0) {
        return 0;
    }
    $totalDaka = 0;
    $totalTash = 0;
    if (table_exists($pdo, 'service_cylinder_rows')) {
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(r.sent_qty), 0) AS total_daka, COALESCE(SUM(r.received_qty), 0) AS total_tash
             FROM service_cylinder_rows r INNER JOIN services s ON s.id = r.service_id WHERE s.customer_id = ?'
        );
        $st->execute([$customerId]);
        $row = $st->fetch();
        if ($row) {
            $totalDaka = (int) ($row['total_daka'] ?? 0);
            $totalTash = (int) ($row['total_tash'] ?? 0);
        }
    } else {
        $st = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM services WHERE customer_id = ?');
        $st->execute([$customerId]);
        $totalDaka = (int) $st->fetchColumn();
    }

    return max(0, $totalDaka - $totalTash);
}

/** Opening-cylinder baqi still outstanding (settlements apply to order net first). */
function customer_opening_cylinders_remaining(PDO $pdo, int $customerId): int
{
    $opening = customer_opening_ledger_row($pdo, $customerId);
    $openingBaqi = (int) $opening['opening_cylinders'];
    if ($openingBaqi <= 0) {
        return 0;
    }
    $orderNet = customer_order_cylinder_net($pdo, $customerId);
    $settled = customer_cylinder_settlements_total($pdo, $customerId);

    return max(0, $openingBaqi - max(0, $settled - $orderNet));
}

/** Cylinders the customer still owes (opening baqi + net sent − settlements). */
function customer_cylinders_owed(PDO $pdo, int $customerId): int
{
    if ($customerId <= 0) {
        return 0;
    }
    $opening = customer_opening_ledger_row($pdo, $customerId);
    $openingBaqi = (int) $opening['opening_cylinders'];
    $orderNet = customer_order_cylinder_net($pdo, $customerId);
    $settled = customer_cylinder_settlements_total($pdo, $customerId);

    return max(0, $openingBaqi + $orderNet - $settled);
}

/** Financial snapshot for order form and ledger headers. */
function customer_account_snapshot(PDO $pdo, int $customerId): array
{
    $opening = customer_opening_ledger_row($pdo, $customerId);
    $receivable = customer_receivable_balance($pdo, $customerId);
    $invoiceRemaining = 0.0;
    if (table_exists($pdo, 'invoices')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(remaining_amount), 0) FROM invoices WHERE customer_id = ?');
        $st->execute([$customerId]);
        $invoiceRemaining = max(0, (float) $st->fetchColumn());
    }
    $openingBalanceOriginal = (float) $opening['opening_balance'];
    $openingCylindersOriginal = (int) $opening['opening_cylinders'];
    $openingBalanceRemaining = max(0.0, $receivable - $invoiceRemaining);
    $openingCylindersRemaining = customer_opening_cylinders_remaining($pdo, $customerId);
    $showOpeningBalance = $openingBalanceOriginal > 0.00001 && $openingBalanceRemaining > 0.00001;
    $showOpeningCylinders = $openingCylindersOriginal > 0 && $openingCylindersRemaining > 0;

    return [
        'receivable' => $receivable,
        'opening_balance' => $openingBalanceOriginal,
        'opening_cylinders' => $openingCylindersOriginal,
        'opening_balance_remaining' => $openingBalanceRemaining,
        'opening_cylinders_remaining' => $openingCylindersRemaining,
        'cylinders_owed' => customer_cylinders_owed($pdo, $customerId),
        'invoice_remaining' => $invoiceRemaining,
        'show_opening_balance' => $showOpeningBalance,
        'show_opening_cylinders' => $showOpeningCylinders,
    ];
}

/** Snapshot with formatted currency strings for AJAX UI updates. */
function customer_account_snapshot_for_ui(PDO $pdo, int $customerId): array
{
    $snap = customer_account_snapshot($pdo, $customerId);
    $receivable = (float) ($snap['receivable'] ?? 0);

    return array_merge($snap, [
        'receivable_formatted' => format_currency($receivable),
        'invoice_remaining_formatted' => format_currency((float) ($snap['invoice_remaining'] ?? 0)),
        'opening_balance_remaining_formatted' => format_currency((float) ($snap['opening_balance_remaining'] ?? 0)),
        'receivable_tone' => $receivable > 0.00001 ? 'amber' : 'emerald',
    ]);
}

/**
 * Global opening-balance and opening-baqi totals for the customers list KPIs.
 *
 * @return array{
 *   opening_balance_total: float,
 *   opening_cylinders_total: int,
 *   opening_balance_formatted: string,
 *   opening_cylinders_formatted: string
 * }
 */
function customers_global_opening_totals(PDO $pdo): array
{
    $balanceTotal = 0.0;
    $cylindersTotal = 0;
    if (table_exists($pdo, 'customers')) {
        $ids = $pdo->query('SELECT id FROM customers')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $rawId) {
            $cid = (int) $rawId;
            if ($cid <= 0) {
                continue;
            }
            $snap = customer_account_snapshot($pdo, $cid);
            if ((float) ($snap['opening_balance'] ?? 0) > 0.00001) {
                $balanceTotal += max(0.0, (float) ($snap['opening_balance_remaining'] ?? 0));
            }
            if ((int) ($snap['opening_cylinders'] ?? 0) > 0) {
                $cylindersTotal += max(0, (int) ($snap['opening_cylinders_remaining'] ?? 0));
            }
        }
    }

    return [
        'opening_balance_total' => $balanceTotal,
        'opening_cylinders_total' => $cylindersTotal,
        'opening_balance_formatted' => format_currency($balanceTotal),
        'opening_cylinders_formatted' => (string) $cylindersTotal,
    ];
}

/** Dashboard / global figures after payments (reads live DB). */
function financial_system_pulse(PDO $pdo, ?string $cashLogFrom = null, ?string $cashLogTo = null): array
{
    $todayYmd = date('Y-m-d');
    $todayReceived = financial_net_received_for_range($pdo, $todayYmd, $todayYmd);
    $totalReceivedAllTime = financial_total_received_all_time($pdo);

    $supplierPending = supplier_global_pending_payments($pdo);

    $pulse = [
        'today_received' => $todayReceived,
        'today_received_formatted' => format_currency($todayReceived),
        'total_received_all_time' => $totalReceivedAllTime,
        'total_received_all_time_formatted' => format_currency($totalReceivedAllTime),
        'supplier_pending' => $supplierPending,
        'supplier_pending_formatted' => format_currency($supplierPending),
    ];

    if ($cashLogFrom !== null && $cashLogTo !== null && $cashLogFrom !== '' && $cashLogTo !== '') {
        $totalInflow = financial_net_received_for_range($pdo, $cashLogFrom, $cashLogTo);

        $allLogTx = financial_overview_transactions($pdo, $cashLogFrom, $cashLogTo);
        $logFilteredIn = 0.0;
        $logFilteredOut = 0.0;
        foreach ($allLogTx as $txSum) {
            $logFilteredIn += (float) ($txSum['inflow'] ?? 0);
            $logFilteredOut += (float) ($txSum['outflow'] ?? 0);
        }

        $pulse['cash'] = [
            'total_inflow_formatted' => format_currency($totalInflow),
            'filtered_in_formatted' => format_currency($logFilteredIn),
            'filtered_out_formatted' => format_currency($logFilteredOut),
            'filtered_net_formatted' => format_currency($logFilteredIn - $logFilteredOut),
        ];
    }

    return $pulse;
}

/**
 * @return array<int, array{receivable: float, receivable_formatted: string}>
 */
function customer_receivable_map(PDO $pdo, array $customerIds): array
{
    $out = [];
    foreach ($customerIds as $id) {
        $cid = (int) $id;
        if ($cid <= 0) {
            continue;
        }
        $receivable = customer_receivable_balance($pdo, $cid);
        $out[$cid] = [
            'receivable' => $receivable,
            'receivable_formatted' => format_currency($receivable),
        ];
    }

    return $out;
}

/** Insert opening-balance ledger row for a new customer. */
function customer_insert_opening_ledger(PDO $pdo, int $customerId, float $openingBalance, int $openingCylinders): void
{
    if ($customerId <= 0 || !table_exists($pdo, 'ledger')) {
        return;
    }
    if ($openingBalance <= 0 && $openingCylinders === 0) {
        return;
    }
    $ledgerStmt = $pdo->prepare(
        'INSERT INTO ledger (customer_id, debit, credit, balance, date, description, cylinders_sent, cylinders_received, cylinders_baqi, reference_type, reference_id)
         VALUES (?, ?, 0, ?, ?, ?, 0, 0, ?, ?, ?)'
    );
    $ledgerStmt->execute([
        $customerId,
        $openingBalance,
        $openingBalance,
        date('Y-m-d'),
        __('customers.ledger_opening_desc'),
        $openingCylinders,
        'opening_balance',
        $customerId,
    ]);
}

/** Create customer record and optional opening ledger entry; returns new id. */
function customer_create_with_opening(
    PDO $pdo,
    string $name,
    string $phone,
    string $address = '',
    string $customerType = 'Retail',
    string $notes = '',
    float $openingBalance = 0.0,
    int $openingCylinders = 0
): int {
    $hasType = column_exists($pdo, 'customers', 'customer_type');
    $hasNotes = column_exists($pdo, 'customers', 'notes');
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
    $customerId = (int) $pdo->lastInsertId();
    if ($customerId > 0) {
        customer_insert_opening_ledger($pdo, $customerId, max(0, $openingBalance), $openingCylinders);
    }
    return $customerId;
}

/** Total pending payments to suppliers (opening liabilities + unpaid purchase balances). */
function supplier_global_pending_payments(PDO $pdo): float
{
    $opening = supplier_opening_liability_sum($pdo);
    if (table_exists($pdo, 'supplier_transactions') && column_exists($pdo, 'supplier_transactions', 'remaining_amount')) {
        $remaining = (float) $pdo->query(
            'SELECT COALESCE(SUM(remaining_amount), 0) FROM supplier_transactions WHERE remaining_amount > 0.00001'
        )->fetchColumn();
        return max(0.0, $opening + $remaining);
    }
    $purchases = table_exists($pdo, 'supplier_transactions')
        ? (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM supplier_transactions')->fetchColumn()
        : 0.0;
    $paid = table_exists($pdo, 'supplier_payments')
        ? (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments')->fetchColumn()
        : 0.0;
    return supplier_amount_owed($opening, $purchases, $paid);
}

/** Billed amount per service row (same logic as dashboard / recent orders). */
function service_order_amount_expr(PDO $pdo): string
{
    $parts = [];
    if (column_exists($pdo, 'services', 'grand_total')) {
        $parts[] = 's.grand_total';
    }
    if (column_exists($pdo, 'services', 'total_bill')) {
        $parts[] = 's.total_bill';
    }
    if (column_exists($pdo, 'invoices', 'service_id')) {
        $parts[] = 'i.total_amount';
    }
    $parts[] = '(s.quantity * s.price)';

    return 'COALESCE(' . implode(', ', $parts) . ')';
}

require_once __DIR__ . '/i18n.php';

/** Create tables for dashboard cash-out tracking (general expenses, salaries). */
function ensure_financial_expense_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS general_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            description VARCHAR(500) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            amount DECIMAL(12,2) NOT NULL,
            expense_date DATE NOT NULL,
            payment_type ENUM('Cash','Bank') NOT NULL DEFAULT 'Cash',
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_expense_date (expense_date)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employee_salaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(200) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            salary_date DATE NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_salary_date (salary_date)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cash_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            description VARCHAR(500) NULL,
            inflow DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            outflow DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            transaction_date DATE NOT NULL,
            is_opening TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cash_tx_date (transaction_date),
            KEY idx_cash_opening (is_opening)
        )
    ");
}

/**
 * Cash Book health filter: today, current week (Mon–today), current month (1st–today),
 * or a specific calendar month via YYYY-MM (full month, capped at today for the current month).
 *
 * @return array{from: string, to: string, period: string, month: string}
 */
function financial_health_period_range(string $period, ?string $monthKey = null): array
{
    $monthKey = trim((string) $monthKey);
    if ($monthKey !== '' && preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $monthKey . '-01');
        if ($start) {
            $today = new DateTimeImmutable('today');
            if ($start <= $today) {
                $end = $start->modify('last day of this month');
                if ($end > $today) {
                    $end = $today;
                }

                return [
                    'from' => $start->format('Y-m-d'),
                    'to' => $end->format('Y-m-d'),
                    'period' => 'month',
                    'month' => $monthKey,
                ];
            }
        }
    }

    $period = in_array($period, ['today', 'week', 'month'], true) ? $period : 'month';
    $to = new DateTimeImmutable('today');
    if ($period === 'today') {
        $from = $to;
    } elseif ($period === 'week') {
        $from = $to->modify('monday this week');
    } else {
        $from = $to->modify('first day of this month');
    }

    return [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'period' => $period,
        'month' => '',
    ];
}

/** @return list<array{value: string, label: string}> last N calendar months (YYYY-MM), newest first */
function financial_health_month_options(int $count = 24): array
{
    $count = max(1, min(60, $count));
    $today = new DateTimeImmutable('today');
    $cursor = $today->modify('first day of this month');
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $value = $cursor->format('Y-m');
        $out[] = [
            'value' => $value,
            'label' => financial_bucket_label($cursor->format('Y-m-d'), 'month'),
        ];
        $cursor = $cursor->modify('-1 month');
    }

    return $out;
}

/**
 * @return array{
 *   revenue: float,
 *   purchases: float,
 *   expenses: float,
 *   salaries: float,
 *   net: float,
 *   revenue_formatted: string,
 *   purchases_formatted: string,
 *   expenses_formatted: string,
 *   salaries_formatted: string,
 *   net_formatted: string,
 *   net_tone: string
 * }
 */
function financial_health_summary(PDO $pdo, string $from, string $to): array
{
    $revenue = financial_gross_inflow_for_range($pdo, $from, $to);
    $purchases = financial_sum_supplier_purchases_total($pdo, $from, $to);
    $expenses = array_sum(financial_sum_by_date($pdo, 'general_expenses', 'expense_date', 'amount', $from, $to));
    $salaries = array_sum(financial_sum_by_date($pdo, 'employee_salaries', 'salary_date', 'amount', $from, $to));
    $net = $revenue - $purchases - $expenses - $salaries;

    return [
        'revenue' => $revenue,
        'purchases' => $purchases,
        'expenses' => $expenses,
        'salaries' => $salaries,
        'net' => $net,
        'revenue_formatted' => format_currency($revenue),
        'purchases_formatted' => format_currency($purchases),
        'expenses_formatted' => format_currency($expenses),
        'salaries_formatted' => format_currency($salaries),
        'net_formatted' => format_currency($net),
        'net_tone' => $net >= -0.00001 ? 'emerald' : 'rose',
    ];
}

/** Payments against supplier previous balance owed (supplier_payments with no purchase row). */
function financial_sum_supplier_opening_payments_total(PDO $pdo, string $from, string $to): float
{
    if (!table_exists($pdo, 'supplier_payments')) {
        return 0.0;
    }
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM supplier_payments
         WHERE transaction_id IS NULL
           AND payment_date >= ? AND payment_date <= ?'
    );
    $st->execute([$from, $to]);

    return (float) $st->fetchColumn();
}

/** @return array<string, float> date Y-m-d => opening-balance payment total */
function financial_sum_supplier_opening_payments_by_date(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'supplier_payments')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT payment_date AS d, COALESCE(SUM(amount), 0) AS t
         FROM supplier_payments
         WHERE transaction_id IS NULL
           AND payment_date >= ? AND payment_date <= ?
         GROUP BY payment_date'
    );
    $st->execute([$from, $to]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(string) $row['d']] = (float) $row['t'];
    }

    return $out;
}

/** @return array<string, float> date Y-m-d => purchase total (refill purchases + opening-balance payments) */
function financial_sum_supplier_purchases_by_date(PDO $pdo, string $from, string $to): array
{
    $out = [];
    if (table_exists($pdo, 'supplier_transactions')) {
        $st = $pdo->prepare(
            'SELECT transaction_date AS d, COALESCE(SUM(total_amount), 0) AS t
             FROM supplier_transactions
             WHERE transaction_date >= ? AND transaction_date <= ?
             GROUP BY transaction_date'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['d']] = (float) $row['t'];
        }
    }
    foreach (financial_sum_supplier_opening_payments_by_date($pdo, $from, $to) as $dateKey => $amount) {
        $out[$dateKey] = ($out[$dateKey] ?? 0.0) + $amount;
    }

    return $out;
}

/**
 * Last six calendar months of gross revenue vs total outflows (purchases + expenses + salaries).
 *
 * @return list<array{key: string, label: string, revenue: float, outflow: float, net: float}>
 */
function financial_health_monthly_chart_data(PDO $pdo): array
{
    $to = new DateTimeImmutable('today');
    $from = $to->modify('first day of this month')->modify('-5 months');
    $fromStr = $from->format('Y-m-d');
    $toStr = $to->format('Y-m-d');

    $inflowBy = financial_sum_total_inflow_by_date($pdo, $fromStr, $toStr);
    $purchaseBy = financial_sum_supplier_purchases_by_date($pdo, $fromStr, $toStr);
    $expenseBy = financial_sum_by_date($pdo, 'general_expenses', 'expense_date', 'amount', $fromStr, $toStr);
    $salaryBy = financial_sum_by_date($pdo, 'employee_salaries', 'salary_date', 'amount', $fromStr, $toStr);

    $keys = [];
    $cursor = $from;
    while ($cursor <= $to) {
        $keys[] = $cursor->format('Y-m-01');
        $cursor = $cursor->modify('first day of next month');
    }

    $out = [];
    foreach ($keys as $monthKey) {
        $monthStart = new DateTimeImmutable($monthKey);
        $monthEnd = $monthStart->modify('last day of this month');
        if ($monthEnd > $to) {
            $monthEnd = $to;
        }
        $revenue = 0.0;
        $outflow = 0.0;
        $day = $monthStart;
        while ($day <= $monthEnd) {
            $d = $day->format('Y-m-d');
            $revenue += (float) ($inflowBy[$d] ?? 0);
            $outflow += (float) ($purchaseBy[$d] ?? 0)
                + (float) ($expenseBy[$d] ?? 0)
                + (float) ($salaryBy[$d] ?? 0);
            $day = $day->modify('+1 day');
        }
        $out[] = [
            'key' => $monthKey,
            'label' => financial_bucket_label($monthKey, 'month'),
            'revenue' => $revenue,
            'outflow' => $outflow,
            'net' => $revenue - $outflow,
        ];
    }

    return $out;
}

/**
 * Daily revenue vs outflows for a selected Cash Book date range (chart follows active filter).
 *
 * @return list<array{label: string, revenue: float, outflow: float}>
 */
function financial_health_chart_data(PDO $pdo, string $from, string $to): array
{
    $inflowBy = financial_sum_total_inflow_by_date($pdo, $from, $to);
    $purchaseBy = financial_sum_supplier_purchases_by_date($pdo, $from, $to);
    $expenseBy = financial_sum_by_date($pdo, 'general_expenses', 'expense_date', 'amount', $from, $to);
    $salaryBy = financial_sum_by_date($pdo, 'employee_salaries', 'salary_date', 'amount', $from, $to);

    $cursor = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    $out = [];
    while ($cursor <= $end) {
        $d = $cursor->format('Y-m-d');
        $revenue = (float) ($inflowBy[$d] ?? 0);
        $outflow = (float) ($purchaseBy[$d] ?? 0)
            + (float) ($expenseBy[$d] ?? 0)
            + (float) ($salaryBy[$d] ?? 0);
        $out[] = [
            'label' => format_date_pk($d),
            'revenue' => $revenue,
            'outflow' => $outflow,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $out;
}

/** Cash book inflows (opening capital and other manual entries). */
function financial_sum_cash_inflow_by_date(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'cash_transactions')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT transaction_date AS d, COALESCE(SUM(inflow), 0) AS t
         FROM cash_transactions
         WHERE transaction_date >= ? AND transaction_date <= ?
         GROUP BY transaction_date'
    );
    $st->execute([$from, $to]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(string) $row['d']] = (float) $row['t'];
    }
    return $out;
}

/** Customer payments applied to opening / account balance (ledger credits, not invoice rows). */
function financial_sum_customer_opening_payments_by_date(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'ledger') || !column_exists($pdo, 'ledger', 'reference_type')) {
        return [];
    }
    $openingDesc = __('ledger.payment_opening_settlement');
    $st = $pdo->prepare(
        "SELECT l.date AS d, COALESCE(SUM(l.credit), 0) AS t
         FROM ledger l
         WHERE l.date >= ? AND l.date <= ?
           AND l.credit > 0.00001
           AND (
             l.reference_type = 'opening_payment'
             OR (l.reference_type = 'payment' AND l.reference_id = 0)
             OR (l.reference_type = 'payment' AND TRIM(l.description) = ?)
           )
         GROUP BY l.date"
    );
    $st->execute([$from, $to, $openingDesc]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(string) $row['d']] = (float) $row['t'];
    }

    return $out;
}

/**
 * @return list<array{date: string, type: string, type_label: string, description: string, inflow: float, outflow: float, ref_id: int, row_key: string}>
 */
function financial_customer_opening_cash_transactions(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'ledger') || !column_exists($pdo, 'ledger', 'reference_type')) {
        return [];
    }
    $openingDesc = __('ledger.payment_opening_settlement');
    $st = $pdo->prepare(
        "SELECT l.id, l.date AS tx_date, l.created_at, l.credit AS amount, l.description, c.name AS customer_name
         FROM ledger l
         INNER JOIN customers c ON c.id = l.customer_id
         WHERE l.date >= ? AND l.date <= ?
           AND l.credit > 0.00001
           AND (
             l.reference_type = 'opening_payment'
             OR (l.reference_type = 'payment' AND l.reference_id = 0)
             OR (l.reference_type = 'payment' AND TRIM(l.description) = ?)
           )
         ORDER BY l.date DESC, l.id DESC"
    );
    $st->execute([$from, $to, $openingDesc]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $refId = (int) ($r['id'] ?? 0);
        $desc = trim((string) ($r['description'] ?? ''));
        if ($desc === '') {
            $desc = (string) __('ledger.payment_opening_settlement');
        }
        $rows[] = [
            'date' => (string) $r['tx_date'],
            'created_at' => (string) ($r['created_at'] ?? ''),
            'type' => 'inflow_customer_opening',
            'type_label' => __('dashboard.tx_type_customer_opening_payment'),
            'description' => $desc . ' — ' . (string) ($r['customer_name'] ?? ''),
            'inflow' => (float) ($r['amount'] ?? 0),
            'outflow' => 0.0,
            'ref_id' => $refId,
            'row_key' => financial_transaction_row_key('inflow_customer_opening', $refId),
        ];
    }

    return $rows;
}

/**
 * Opening balance recorded when a customer is added (receivable; shown in log, not cash in).
 *
 * @return list<array{date: string, type: string, type_label: string, description: string, inflow: float, outflow: float, ref_id: int, row_key: string}>
 */
function financial_customer_opening_recorded_transactions(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'ledger') || !column_exists($pdo, 'ledger', 'reference_type')) {
        return [];
    }
    $st = $pdo->prepare(
        "SELECT l.id, l.date AS tx_date, l.created_at, l.debit AS amount, l.description, c.name AS customer_name
         FROM ledger l
         INNER JOIN customers c ON c.id = l.customer_id
         WHERE l.date >= ? AND l.date <= ?
           AND l.reference_type = 'opening_balance'
           AND l.debit > 0.00001
         ORDER BY l.date DESC, l.id DESC"
    );
    $st->execute([$from, $to]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $refId = (int) ($r['id'] ?? 0);
        $amount = (float) ($r['amount'] ?? 0);
        $rows[] = [
            'date' => (string) $r['tx_date'],
            'created_at' => (string) ($r['created_at'] ?? ''),
            'type' => 'customer_opening_recorded',
            'type_label' => __('dashboard.tx_type_customer_opening_recorded'),
            'description' => __('dashboard.tx_customer_opening_recorded_desc', [
                'name' => (string) ($r['customer_name'] ?? ''),
                'amount' => format_currency($amount),
            ]),
            'inflow' => 0.0,
            'outflow' => 0.0,
            'ref_id' => $refId,
            'row_key' => financial_transaction_row_key('customer_opening_recorded', $refId),
        ];
    }

    return $rows;
}

/** Combined customer payments + cash book inflows for KPI totals. */
function financial_sum_total_inflow_by_date(PDO $pdo, string $from, string $to): array
{
    $merged = financial_sum_order_payments_by_date($pdo, $from, $to);
    foreach (financial_sum_customer_opening_payments_by_date($pdo, $from, $to) as $dateKey => $amount) {
        $merged[$dateKey] = ($merged[$dateKey] ?? 0.0) + $amount;
    }
    foreach (financial_sum_cash_inflow_by_date($pdo, $from, $to) as $dateKey => $amount) {
        $merged[$dateKey] = ($merged[$dateKey] ?? 0.0) + $amount;
    }
    return $merged;
}

/** Gross customer/cash inflows for a date range (includes opening cash when outside range). */
function financial_gross_inflow_for_range(PDO $pdo, string $from, string $to): float
{
    $total = array_sum(financial_sum_total_inflow_by_date($pdo, $from, $to));
    if (table_exists($pdo, 'cash_transactions')) {
        $openingCashRow = $pdo->query(
            'SELECT inflow, transaction_date FROM cash_transactions WHERE is_opening = 1 ORDER BY id ASC LIMIT 1'
        )->fetch();
        if ($openingCashRow) {
            $openingDate = (string) ($openingCashRow['transaction_date'] ?? '');
            $openingAmount = (float) ($openingCashRow['inflow'] ?? 0);
            if ($openingAmount > 0 && ($openingDate < $from || $openingDate > $to)) {
                $total += $openingAmount;
            }
        }
    }

    return $total;
}

/** Supplier purchases for a date range: refill totals plus payments on previous balance owed. */
function financial_sum_supplier_purchases_total(PDO $pdo, string $from, string $to): float
{
    $total = 0.0;
    if (table_exists($pdo, 'supplier_transactions')) {
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(total_amount), 0)
             FROM supplier_transactions
             WHERE transaction_date >= ? AND transaction_date <= ?'
        );
        $st->execute([$from, $to]);
        $total += (float) $st->fetchColumn();
    }

    return $total + financial_sum_supplier_opening_payments_total($pdo, $from, $to);
}

/** Received payments net of supplier cash paid out (payments) for the range. */
function financial_net_received_for_range(PDO $pdo, string $from, string $to): float
{
    return financial_gross_inflow_for_range($pdo, $from, $to)
        - financial_sum_supplier_payments_total($pdo, $from, $to);
}

/** Cumulative received payments (inflows minus all supplier payments), all dates. */
function financial_total_received_all_time(PDO $pdo): float
{
    $from = '1970-01-01';
    $to = '2099-12-31';

    return financial_net_received_for_range($pdo, $from, $to);
}

/**
 * Supplier refill purchases as cash log outflows.
 *
 * @return list<array{date: string, type: string, type_label: string, description: string, inflow: float, outflow: float, ref_id: int, row_key: string}>
 */
function financial_supplier_purchase_transactions(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'supplier_transactions')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT t.id, t.transaction_date AS tx_date, t.created_at, t.total_amount AS amount, s.name AS supplier_name
         FROM supplier_transactions t
         INNER JOIN suppliers s ON s.id = t.supplier_id
         WHERE t.transaction_date >= ? AND t.transaction_date <= ?
           AND t.total_amount > 0.00001
         ORDER BY t.transaction_date DESC, t.id DESC'
    );
    $st->execute([$from, $to]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $refId = (int) ($r['id'] ?? 0);
        $rows[] = [
            'date' => (string) $r['tx_date'],
            'created_at' => (string) ($r['created_at'] ?? ''),
            'type' => 'outflow_supplier_purchase',
            'type_label' => __('dashboard.tx_type_supplier_purchase'),
            'description' => 'PUR-' . $refId . ' — ' . (string) ($r['supplier_name'] ?? ''),
            'inflow' => 0.0,
            'outflow' => (float) ($r['amount'] ?? 0),
            'ref_id' => $refId,
            'row_key' => financial_transaction_row_key('outflow_supplier_purchase', $refId),
        ];
    }

    return $rows;
}

/**
 * @return array{
 *   items: list<array<string, mixed>>,
 *   pagination: array{total: int, page: int, per_page: int, total_pages: int},
 *   summary: array{in: float, out: float, net: float, in_formatted: string, out_formatted: string, net_formatted: string, total: int}
 * }
 */
function financial_build_cash_log_feed(
    PDO $pdo,
    string $from,
    string $to,
    string $type,
    string $direction,
    string $q,
    int $page,
    int $perPage
): array {
    $all = financial_overview_transactions($pdo, $from, $to);
    $filtered = financial_filter_transactions($all, [
        'type' => $type,
        'direction' => $direction,
        'q' => $q,
    ]);
    $pagination = financial_paginate_transactions($filtered, $page, $perPage);
    $in = 0.0;
    $out = 0.0;
    foreach ($filtered as $tx) {
        $in += (float) ($tx['inflow'] ?? 0);
        $out += (float) ($tx['outflow'] ?? 0);
    }

    return [
        'items' => $pagination['items'],
        'pagination' => [
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'per_page' => $pagination['per_page'],
            'total_pages' => $pagination['total_pages'],
        ],
        'summary' => [
            'in' => $in,
            'out' => $out,
            'net' => $in - $out,
            'in_formatted' => format_currency($in),
            'out_formatted' => format_currency($out),
            'net_formatted' => format_currency($in - $out),
            'total' => $pagination['total'],
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function financial_cash_log_feed_json_items(array $items): array
{
    $out = [];
    foreach ($items as $tx) {
        $out[] = [
            'type' => (string) ($tx['type'] ?? ''),
            'type_label' => (string) ($tx['type_label'] ?? ''),
            'description' => (string) ($tx['description'] ?? ''),
            'time_formatted' => (string) ($tx['time_formatted'] ?? ''),
            'date_formatted' => (string) ($tx['date_formatted'] ?? format_date_pk((string) ($tx['date'] ?? ''))),
            'inflow' => (float) ($tx['inflow'] ?? 0),
            'outflow' => (float) ($tx['outflow'] ?? 0),
            'inflow_formatted' => (float) ($tx['inflow'] ?? 0) > 0 ? format_currency((float) $tx['inflow']) : '',
            'outflow_formatted' => (float) ($tx['outflow'] ?? 0) > 0 ? format_currency((float) $tx['outflow']) : '',
            'ref_id' => (int) ($tx['ref_id'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return array{from: DateTimeImmutable, to: DateTimeImmutable, bucket: string}
 */
function financial_overview_period_range(string $period): array
{
    $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';
    $to = new DateTimeImmutable('today');
    if ($period === 'weekly') {
        $from = $to->modify('-7 weeks')->modify('monday this week');
        return ['from' => $from, 'to' => $to, 'bucket' => 'week'];
    }
    if ($period === 'monthly') {
        $from = $to->modify('first day of this month')->modify('-5 months');
        return ['from' => $from, 'to' => $to, 'bucket' => 'month'];
    }
    $from = $to->modify('-29 days');
    return ['from' => $from, 'to' => $to, 'bucket' => 'day'];
}

/**
 * @return list<array{key: string, label: string, inflow: float, expense: float, supplier: float, salary: float, outflow: float, net: float}>
 */
function financial_overview_bucket_totals(PDO $pdo, string $period, string $fromDate, string $toDate): array
{
    $range = financial_overview_period_range($period);
    $bucket = $range['bucket'];
    $inflowBy = financial_sum_total_inflow_by_date($pdo, $fromDate, $toDate);
    $supplierBy = table_exists($pdo, 'supplier_payments')
        ? financial_sum_by_date($pdo, 'supplier_payments', 'payment_date', 'amount', $fromDate, $toDate)
        : [];
    $expenseBy = financial_sum_by_date($pdo, 'general_expenses', 'expense_date', 'amount', $fromDate, $toDate);
    $salaryBy = financial_sum_by_date($pdo, 'employee_salaries', 'salary_date', 'amount', $fromDate, $toDate);

    $keys = array_unique(array_merge(array_keys($inflowBy), array_keys($supplierBy), array_keys($expenseBy), array_keys($salaryBy)));
    sort($keys);
    $out = [];
    foreach ($keys as $dateKey) {
        $groupKey = financial_bucket_key($dateKey, $bucket);
        if (!isset($out[$groupKey])) {
            $out[$groupKey] = [
                'key' => $groupKey,
                'label' => financial_bucket_label($groupKey, $bucket),
                'inflow' => 0.0,
                'expense' => 0.0,
                'supplier' => 0.0,
                'salary' => 0.0,
                'outflow' => 0.0,
                'net' => 0.0,
            ];
        }
        $out[$groupKey]['inflow'] += (float) ($inflowBy[$dateKey] ?? 0);
        $out[$groupKey]['supplier'] += (float) ($supplierBy[$dateKey] ?? 0);
        $out[$groupKey]['expense'] += (float) ($expenseBy[$dateKey] ?? 0);
        $out[$groupKey]['salary'] += (float) ($salaryBy[$dateKey] ?? 0);
    }
    foreach ($out as &$row) {
        $row['outflow'] = $row['expense'] + $row['supplier'] + $row['salary'];
        $row['net'] = $row['inflow'] - $row['outflow'];
    }
    unset($row);
    return array_values($out);
}

function financial_bucket_key(string $dateYmd, string $bucket): string
{
    $dt = new DateTimeImmutable($dateYmd);
    if ($bucket === 'week') {
        return $dt->modify('monday this week')->format('Y-m-d');
    }
    if ($bucket === 'month') {
        return $dt->format('Y-m-01');
    }
    return $dateYmd;
}

function financial_bucket_label(string $bucketKey, string $bucket): string
{
    $dt = new DateTimeImmutable($bucketKey);
    if ($bucket === 'week') {
        $end = $dt->modify('+6 days');
        return $dt->format('j M') . ' â€“ ' . $end->format('j M Y');
    }
    if ($bucket === 'month') {
        return i18n_month_short((int) $dt->format('n')) . ' ' . $dt->format('Y');
    }
    return format_date_pk($bucketKey);
}

/** @return array<string, float> date Y-m-d => sum */
function financial_sum_by_date(PDO $pdo, string $table, string $dateCol, string $amountCol, string $from, string $to): array
{
    if (!table_exists($pdo, $table)) {
        return [];
    }
    $st = $pdo->prepare("SELECT {$dateCol} AS d, COALESCE(SUM({$amountCol}), 0) AS t FROM {$table} WHERE {$dateCol} >= ? AND {$dateCol} <= ? GROUP BY {$dateCol}");
    $st->execute([$from, $to]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(string) $row['d']] = (float) $row['t'];
    }
    return $out;
}

/** Customer payments collected (orders / invoices). */
function financial_sum_order_payments_by_date(PDO $pdo, string $from, string $to): array
{
    if (!table_exists($pdo, 'payments')) {
        return [];
    }
    $st = $pdo->prepare('SELECT payment_date AS d, COALESCE(SUM(amount), 0) AS t FROM payments WHERE payment_date >= ? AND payment_date <= ? GROUP BY payment_date');
    $st->execute([$from, $to]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(string) $row['d']] = (float) $row['t'];
    }
    return $out;
}

/**
 * @return list<array{date: string, type: string, type_label: string, description: string, inflow: float, outflow: float, ref_id: int}>
 */
function financial_overview_transactions(PDO $pdo, string $from, string $to): array
{
    $rows = [];
    if (table_exists($pdo, 'payments')) {
        $st = $pdo->prepare(
            'SELECT p.id, p.payment_date AS tx_date, p.created_at, p.amount,
                COALESCE(s.id, 0) AS service_id, c.name AS customer_name
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN services s ON s.id = i.service_id
             INNER JOIN customers c ON c.id = i.customer_id
             WHERE p.payment_date >= ? AND p.payment_date <= ?
             ORDER BY p.payment_date DESC, p.id DESC'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $sid = (int) ($r['service_id'] ?? 0);
            $label = $sid > 0 ? 'ORD-' . $sid : __('dashboard.tx_order_payment');
            $refId = (int) $r['id'];
            $rows[] = [
                'date' => (string) $r['tx_date'],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'type' => 'inflow_order',
                'type_label' => __('dashboard.tx_type_order_payment'),
                'description' => $label . ' â€” ' . (string) ($r['customer_name'] ?? ''),
                'inflow' => (float) $r['amount'],
                'outflow' => 0.0,
                'ref_id' => $refId,
                'row_key' => financial_transaction_row_key('inflow_order', $refId),
            ];
        }
    }
    $rows = array_merge(
        $rows,
        financial_customer_opening_cash_transactions($pdo, $from, $to),
        financial_customer_opening_recorded_transactions($pdo, $from, $to),
        financial_supplier_purchase_transactions($pdo, $from, $to)
    );
    if (table_exists($pdo, 'supplier_payments')) {
        $st = $pdo->prepare(
            'SELECT sp.id, sp.payment_date AS tx_date, sp.created_at, sp.amount, sp.transaction_id, s.name AS supplier_name
             FROM supplier_payments sp
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             WHERE sp.payment_date >= ? AND sp.payment_date <= ?
             ORDER BY sp.payment_date DESC, sp.id DESC'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $refId = (int) $r['id'];
            $rows[] = [
                'date' => (string) $r['tx_date'],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'type' => 'outflow_supplier',
                'type_label' => __('dashboard.tx_type_supplier'),
                'description' => 'SP-' . (int) ($r['transaction_id'] ?? 0) . ' â€” ' . (string) ($r['supplier_name'] ?? ''),
                'inflow' => 0.0,
                'outflow' => (float) $r['amount'],
                'ref_id' => $refId,
                'row_key' => financial_transaction_row_key('outflow_supplier', $refId),
            ];
        }
    }
    if (table_exists($pdo, 'general_expenses')) {
        $st = $pdo->prepare(
            'SELECT id, expense_date AS tx_date, created_at, amount, description, category
             FROM general_expenses
             WHERE expense_date >= ? AND expense_date <= ?
             ORDER BY expense_date DESC, id DESC'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $refId = (int) $r['id'];
            $rows[] = [
                'date' => (string) $r['tx_date'],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'type' => 'outflow_expense',
                'type_label' => __('dashboard.tx_type_expense'),
                'description' => (string) ($r['description'] ?? '') . ' (' . (string) ($r['category'] ?? '') . ')',
                'inflow' => 0.0,
                'outflow' => (float) $r['amount'],
                'ref_id' => $refId,
                'row_key' => financial_transaction_row_key('outflow_expense', $refId),
            ];
        }
    }
    if (table_exists($pdo, 'employee_salaries')) {
        $st = $pdo->prepare(
            'SELECT id, salary_date AS tx_date, created_at, amount, employee_name
             FROM employee_salaries
             WHERE salary_date >= ? AND salary_date <= ?
             ORDER BY salary_date DESC, id DESC'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $refId = (int) $r['id'];
            $rows[] = [
                'date' => (string) $r['tx_date'],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'type' => 'outflow_salary',
                'type_label' => __('dashboard.tx_type_salary'),
                'description' => __('dashboard.tx_salary_for') . ' ' . (string) ($r['employee_name'] ?? ''),
                'inflow' => 0.0,
                'outflow' => (float) $r['amount'],
                'ref_id' => $refId,
                'row_key' => financial_transaction_row_key('outflow_salary', $refId),
            ];
        }
    }
    if (table_exists($pdo, 'cash_transactions')) {
        $st = $pdo->prepare(
            'SELECT id, transaction_date AS tx_date, created_at, inflow, outflow, category, description, is_opening
             FROM cash_transactions
             WHERE transaction_date >= ? AND transaction_date <= ?
             ORDER BY transaction_date DESC, id DESC'
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $inflow = (float) ($r['inflow'] ?? 0);
            $outflow = (float) ($r['outflow'] ?? 0);
            if ($inflow <= 0 && $outflow <= 0) {
                continue;
            }
            $refId = (int) $r['id'];
            $isOpening = (int) ($r['is_opening'] ?? 0) === 1;
            $type = $isOpening ? 'inflow_opening' : ($inflow > 0 ? 'inflow_cash' : 'outflow_cash');
            $category = trim((string) ($r['category'] ?? ''));
            $desc = trim((string) ($r['description'] ?? ''));
            if ($desc === '') {
                $desc = $category !== '' ? $category : __('cash.tx_manual');
            }
            $rows[] = [
                'date' => (string) $r['tx_date'],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'type' => $type,
                'type_label' => match ($type) {
                    'inflow_opening' => __('dashboard.tx_type_opening'),
                    'inflow_cash' => __('dashboard.tx_type_cash_in'),
                    default => __('dashboard.tx_type_cash_out'),
                },
                'description' => $desc,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'ref_id' => $refId,
                'row_key' => financial_transaction_row_key($type, $refId),
            ];
        }
    }

    return financial_sort_transactions_timeline($rows);
}

/**
 * @param list<array{date: string, ...}> $transactions
 * @return list<array{group_key: string, group_label: string, inflow: float, outflow: float, net: float, items: list<array>}>
 */
function financial_group_transactions_for_log(array $transactions, string $bucket): array
{
    $groups = [];
    foreach ($transactions as $tx) {
        $gk = financial_bucket_key((string) $tx['date'], $bucket);
        if (!isset($groups[$gk])) {
            $groups[$gk] = [
                'group_key' => $gk,
                'group_label' => financial_bucket_label($gk, $bucket),
                'inflow' => 0.0,
                'outflow' => 0.0,
                'net' => 0.0,
                'items' => [],
            ];
        }
        $groups[$gk]['inflow'] += (float) $tx['inflow'];
        $groups[$gk]['outflow'] += (float) $tx['outflow'];
        $groups[$gk]['items'][] = $tx;
    }
    foreach ($groups as &$g) {
        $g['net'] = $g['inflow'] - $g['outflow'];
    }
    unset($g);
    krsort($groups);
    return array_values($groups);
}

/** @param array{type?: string, direction?: string, q?: string} $filters */
function financial_filter_transactions(array $transactions, array $filters): array
{
    $type = trim((string) ($filters['type'] ?? ''));
    $direction = trim((string) ($filters['direction'] ?? 'all'));
    $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));

    return array_values(array_filter($transactions, static function (array $tx) use ($type, $direction, $q): bool {
        if ($type !== '' && ($tx['type'] ?? '') !== $type) {
            return false;
        }
        if ($direction === 'in' && (float) ($tx['inflow'] ?? 0) <= 0) {
            return false;
        }
        if ($direction === 'out' && (float) ($tx['outflow'] ?? 0) <= 0) {
            return false;
        }
        if ($q !== '') {
            $hay = mb_strtolower(
                (string) ($tx['description'] ?? '') . ' ' .
                (string) ($tx['type_label'] ?? '') . ' ' .
                (string) ($tx['date'] ?? '')
            );
            if (!str_contains($hay, $q)) {
                return false;
            }
        }
        return true;
    }));
}

/**
 * @return array{items: list<array>, total: int, page: int, per_page: int, total_pages: int}
 */
function financial_paginate_transactions(array $transactions, int $page, int $perPage): array
{
    $perPage = max(5, min(100, $perPage));
    $total = count($transactions);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice($transactions, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

function financial_transaction_row_key(string $type, int $refId): string
{
    return $type . ':' . $refId;
}

/** @return array<string, string>|null */
function financial_transaction_detail(PDO $pdo, string $type, int $refId): ?array
{
    if ($refId <= 0) {
        return null;
    }

    if (in_array($type, ['inflow_customer_opening', 'customer_opening_recorded'], true) && table_exists($pdo, 'ledger')) {
        $st = $pdo->prepare(
            'SELECT l.*, c.name AS customer_name, c.phone AS customer_phone
             FROM ledger l
             INNER JOIN customers c ON c.id = l.customer_id
             WHERE l.id = ? LIMIT 1'
        );
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $isPayment = $type === 'inflow_customer_opening';
        $amount = $isPayment ? (float) ($r['credit'] ?? 0) : (float) ($r['debit'] ?? 0);

        return [
            __('dashboard.detail_type') => $isPayment
                ? __('dashboard.tx_type_customer_opening_payment')
                : __('dashboard.tx_type_customer_opening_recorded'),
            __('dashboard.detail_reference') => 'LED-' . $refId,
            __('dashboard.detail_date') => format_date_pk((string) ($r['date'] ?? '')),
            __('dashboard.detail_amount') => format_currency($amount),
            __('dashboard.detail_customer') => (string) ($r['customer_name'] ?? ''),
            __('dashboard.detail_phone') => (string) ($r['customer_phone'] ?? '-') ?: '-',
            __('dashboard.field_description') => trim((string) ($r['description'] ?? '')) !== ''
                ? trim((string) $r['description'])
                : (string) __('common.none'),
        ];
    }

    if ($type === 'inflow_order' && table_exists($pdo, 'payments')) {
        $st = $pdo->prepare(
            'SELECT p.*, i.id AS invoice_id, i.total_amount, i.paid_amount, i.remaining_amount, i.status AS invoice_status,
                s.id AS service_id, s.date AS service_date, s.service_type, c.name AS customer_name, c.phone AS customer_phone
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN services s ON s.id = i.service_id
             INNER JOIN customers c ON c.id = i.customer_id
             WHERE p.id = ? LIMIT 1'
        );
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $sid = (int) ($r['service_id'] ?? 0);
        return [
            __('dashboard.detail_type') => __('dashboard.tx_type_order_payment'),
            __('dashboard.detail_reference') => $sid > 0 ? 'ORD-' . $sid : 'INV-' . (int) ($r['invoice_id'] ?? 0),
            __('dashboard.detail_date') => format_date_pk((string) ($r['payment_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency((float) ($r['amount'] ?? 0)),
            __('dashboard.detail_customer') => (string) ($r['customer_name'] ?? ''),
            __('dashboard.detail_phone') => (string) ($r['customer_phone'] ?? '-') ?: '-',
            __('dashboard.detail_invoice_total') => format_currency((float) ($r['total_amount'] ?? 0)),
            __('dashboard.detail_invoice_paid') => format_currency((float) ($r['paid_amount'] ?? 0)),
            __('dashboard.detail_invoice_remaining') => format_currency((float) ($r['remaining_amount'] ?? 0)),
            __('dashboard.detail_status') => payment_status_label(payment_status_from_amounts(
                (float) ($r['total_amount'] ?? 0),
                (float) ($r['paid_amount'] ?? 0)
            )),
            __('dashboard.detail_service_date') => format_date_pk((string) ($r['service_date'] ?? '')),
            __('dashboard.col_service') => service_type_label((string) ($r['service_type'] ?? 'refill')),
        ];
    }

    if ($type === 'outflow_supplier_purchase' && table_exists($pdo, 'supplier_transactions')) {
        $st = $pdo->prepare(
            'SELECT t.*, s.name AS supplier_name, s.phone AS supplier_phone, s.contact_person
             FROM supplier_transactions t
             INNER JOIN suppliers s ON s.id = t.supplier_id
             WHERE t.id = ? LIMIT 1'
        );
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }

        return [
            __('dashboard.detail_type') => __('dashboard.tx_type_supplier_purchase'),
            __('dashboard.detail_reference') => 'PUR-' . $refId,
            __('dashboard.detail_date') => format_date_pk((string) ($r['transaction_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency((float) ($r['total_amount'] ?? 0)),
            __('dashboard.detail_supplier') => (string) ($r['supplier_name'] ?? ''),
            __('dashboard.detail_phone') => (string) ($r['supplier_phone'] ?? '-') ?: '-',
            __('dashboard.detail_contact') => (string) ($r['contact_person'] ?? '-') ?: '-',
            __('dashboard.detail_purchase_paid') => format_currency((float) ($r['paid_amount'] ?? 0)),
            __('dashboard.detail_purchase_remaining') => format_currency((float) ($r['remaining_amount'] ?? 0)),
            __('dashboard.detail_status') => supplier_tx_payment_status_label((string) ($r['payment_status'] ?? '')),
            __('dashboard.field_payment_type') => supplier_payment_type_label((string) ($r['payment_type'] ?? '')),
        ];
    }

    if ($type === 'outflow_supplier' && table_exists($pdo, 'supplier_payments')) {
        $st = $pdo->prepare(
            'SELECT sp.*, s.name AS supplier_name, s.phone AS supplier_phone, s.contact_person,
                t.transaction_date, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status
             FROM supplier_payments sp
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             LEFT JOIN supplier_transactions t ON t.id = sp.transaction_id
             WHERE sp.id = ? LIMIT 1'
        );
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        return [
            __('dashboard.detail_type') => __('dashboard.tx_type_supplier'),
            __('dashboard.detail_reference') => 'SP-' . (int) ($r['transaction_id'] ?? 0),
            __('dashboard.detail_date') => format_date_pk((string) ($r['payment_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency((float) ($r['amount'] ?? 0)),
            __('dashboard.detail_supplier') => (string) ($r['supplier_name'] ?? ''),
            __('dashboard.detail_phone') => (string) ($r['supplier_phone'] ?? '-') ?: '-',
            __('dashboard.detail_contact') => (string) ($r['contact_person'] ?? '-') ?: '-',
            __('dashboard.field_payment_type') => supplier_payment_type_label((string) ($r['payment_type'] ?? '')),
            __('dashboard.detail_purchase_date') => format_date_pk((string) ($r['transaction_date'] ?? '')),
            __('dashboard.detail_purchase_total') => format_currency((float) ($r['total_amount'] ?? 0)),
            __('dashboard.detail_purchase_paid') => format_currency((float) ($r['paid_amount'] ?? 0)),
            __('dashboard.detail_purchase_remaining') => format_currency((float) ($r['remaining_amount'] ?? 0)),
            __('dashboard.detail_status') => supplier_tx_payment_status_label((string) ($r['payment_status'] ?? '')),
        ];
    }

    if ($type === 'outflow_expense' && table_exists($pdo, 'general_expenses')) {
        $st = $pdo->prepare('SELECT * FROM general_expenses WHERE id = ? LIMIT 1');
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $out = [
            __('dashboard.detail_type') => __('dashboard.tx_type_expense'),
            __('dashboard.detail_reference') => 'EXP-' . $refId,
            __('dashboard.detail_date') => format_date_pk((string) ($r['expense_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency((float) ($r['amount'] ?? 0)),
            __('dashboard.field_description') => (string) ($r['description'] ?? ''),
            __('dashboard.field_category') => (string) ($r['category'] ?? ''),
            __('dashboard.field_payment_type') => (string) ($r['payment_type'] ?? ''),
        ];
        $notes = trim((string) ($r['notes'] ?? ''));
        if ($notes !== '') {
            $out[__('dashboard.detail_notes')] = $notes;
        }
        return $out;
    }

    if ($type === 'outflow_salary' && table_exists($pdo, 'employee_salaries')) {
        $st = $pdo->prepare('SELECT * FROM employee_salaries WHERE id = ? LIMIT 1');
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $out = [
            __('dashboard.detail_type') => __('dashboard.tx_type_salary'),
            __('dashboard.detail_reference') => 'SAL-' . $refId,
            __('dashboard.detail_date') => format_date_pk((string) ($r['salary_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency((float) ($r['amount'] ?? 0)),
            __('dashboard.field_employee') => (string) ($r['employee_name'] ?? ''),
        ];
        $notes = trim((string) ($r['notes'] ?? ''));
        if ($notes !== '') {
            $out[__('dashboard.detail_notes')] = $notes;
        }
        return $out;
    }

    if (in_array($type, ['inflow_opening', 'inflow_cash', 'outflow_cash'], true) && table_exists($pdo, 'cash_transactions')) {
        $st = $pdo->prepare('SELECT * FROM cash_transactions WHERE id = ? LIMIT 1');
        $st->execute([$refId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $isOpening = (int) ($r['is_opening'] ?? 0) === 1;
        $typeLabel = $isOpening
            ? __('dashboard.tx_type_opening')
            : ((float) ($r['inflow'] ?? 0) > 0 ? __('dashboard.tx_type_cash_in') : __('dashboard.tx_type_cash_out'));
        $amount = (float) ($r['inflow'] ?? 0) > 0 ? (float) $r['inflow'] : (float) ($r['outflow'] ?? 0);
        return [
            __('dashboard.detail_type') => $typeLabel,
            __('dashboard.detail_reference') => ($isOpening ? 'OPEN-' : 'CASH-') . $refId,
            __('dashboard.detail_date') => format_date_pk((string) ($r['transaction_date'] ?? '')),
            __('dashboard.detail_amount') => format_currency($amount),
            __('dashboard.field_category') => (string) ($r['category'] ?? ''),
            __('dashboard.field_description') => (string) ($r['description'] ?? '') ?: '-',
        ];
    }

    return null;
}

/**
 * @param list<array> $transactions
 */
function financial_render_cash_log_pdf(PDO $pdo, array $transactions, string $fromDate, string $toDate, array $filterSummary): void
{
    $grouped = financial_group_transactions_for_log($transactions, 'day');
    $totalIn = 0.0;
    $totalOut = 0.0;
    foreach ($transactions as $tx) {
        $totalIn += (float) ($tx['inflow'] ?? 0);
        $totalOut += (float) ($tx['outflow'] ?? 0);
    }
    $rtl = i18n_is_rtl();
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="<?= e(i18n_locale()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
    <head>
        <meta charset="UTF-8">
        <title><?= e(__('dashboard.pdf_title')) ?></title>
        <?php if ($rtl): ?>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
        <?php endif; ?>
        <style>
            body { font-family: <?= $rtl ? "'Noto Naskh Arabic'," : '' ?> Arial, sans-serif; font-size: 11px; color: #0f172a; padding: 16px; }
            h1 { font-size: 18px; margin: 0 0 4px; }
            .muted { color: #64748b; margin-bottom: 12px; }
            .summary { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
            .summary span { border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 6px; }
            .day-head { background: #f1f5f9; padding: 8px 10px; font-weight: 700; margin-top: 12px; border: 1px solid #cbd5e1; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
            th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: start; }
            th { background: #f8fafc; font-size: 10px; text-transform: uppercase; }
            .num { text-align: end; }
            .in { color: #047857; }
            .out { color: #be123c; }
        </style>
    </head>
    <body onload="window.print()">
        <h1><?= e(__('app.name')) ?> â€” <?= e(__('dashboard.pdf_title')) ?></h1>
        <p class="muted"><?= e(format_date_pk($fromDate)) ?> â€“ <?= e(format_date_pk($toDate)) ?> Â· <?= e(__('dashboard.pdf_generated')) ?>: <?= e(date('d/m/Y H:i')) ?></p>
        <?php if ($filterSummary): ?>
            <p class="muted"><?= e(implode(' Â· ', $filterSummary)) ?></p>
        <?php endif; ?>
        <div class="summary">
            <span class="in"><?= e(__('dashboard.kpi_received')) ?>: <?= e(format_currency($totalIn)) ?></span>
            <span class="out"><?= e(__('dashboard.kpi_total_out')) ?>: <?= e(format_currency($totalOut)) ?></span>
            <span><strong><?= e(__('dashboard.kpi_net_cash')) ?>:</strong> <?= e(format_currency($totalIn - $totalOut)) ?></span>
        </div>
        <?php if (!$grouped): ?>
            <p><?= e(__('dashboard.no_cash_movements')) ?></p>
        <?php else: ?>
            <?php foreach ($grouped as $group): ?>
                <div class="day-head">
                    <?= e((string) $group['group_label']) ?>
                    â€” +<?= e(format_currency((float) $group['inflow'])) ?>
                    / âˆ’<?= e(format_currency((float) $group['outflow'])) ?>
                    / <?= e(__('dashboard.net')) ?>: <?= e(format_currency((float) $group['net'])) ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><?= e(__('dashboard.col_type')) ?></th>
                            <th><?= e(__('dashboard.col_description')) ?></th>
                            <th class="num"><?= e(__('dashboard.col_in')) ?></th>
                            <th class="num"><?= e(__('dashboard.col_out')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['items'] as $tx): ?>
                        <tr>
                            <td><?= e((string) $tx['type_label']) ?></td>
                            <td><?= e((string) $tx['description']) ?></td>
                            <td class="num in"><?= (float) $tx['inflow'] > 0 ? e(format_currency((float) $tx['inflow'])) : 'â€”' ?></td>
                            <td class="num out"><?= (float) $tx['outflow'] > 0 ? e(format_currency((float) $tx['outflow'])) : 'â€”' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

function ensure_cylinder_daka_tash_columns(PDO $pdo): void
{
    if (!table_exists($pdo, 'cylinder_stock_by_type')) {
        return;
    }
    if (column_exists($pdo, 'cylinder_stock_by_type', 'daka_qty')) {
        return;
    }
    $pdo->exec('ALTER TABLE cylinder_stock_by_type ADD COLUMN daka_qty INT NOT NULL DEFAULT 0 AFTER available');
    $pdo->exec('ALTER TABLE cylinder_stock_by_type ADD COLUMN tash_qty INT NOT NULL DEFAULT 0 AFTER daka_qty');
    $pdo->exec('UPDATE cylinder_stock_by_type SET daka_qty = available');
    $std = standard_cylinder_size();
    try {
        $empty = (int) $pdo->query('SELECT COALESCE(empty, 0) FROM cylinders ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($empty > 0) {
            $pdo->prepare('UPDATE cylinder_stock_by_type SET tash_qty = tash_qty + ? WHERE cylinder_type = ?')
                ->execute([$empty, $std]);
        }
    } catch (Throwable $e) {
        // cylinders table may not exist yet
    }
    sync_cylinders_aggregate_from_typed_stock($pdo);
}

function sync_cylinders_aggregate_from_typed_stock(PDO $pdo): void
{
    if (!table_exists($pdo, 'cylinder_stock_by_type') || !column_exists($pdo, 'cylinder_stock_by_type', 'daka_qty')) {
        return;
    }
    $sums = $pdo->query('SELECT COALESCE(SUM(daka_qty), 0), COALESCE(SUM(tash_qty), 0) FROM cylinder_stock_by_type')->fetch(PDO::FETCH_NUM);
    $daka = (int) ($sums[0] ?? 0);
    $tash = (int) ($sums[1] ?? 0);
    $row = $pdo->query('SELECT id FROM cylinders ORDER BY id ASC LIMIT 1')->fetch();
    if (!$row) {
        $pdo->prepare('INSERT INTO cylinders (total, available, issued, empty) VALUES (?, ?, 0, ?)')
            ->execute([$daka + $tash, $daka, $tash]);
        return;
    }
    $pdo->prepare('UPDATE cylinders SET total = ?, available = ?, empty = ? WHERE id = ?')
        ->execute([$daka + $tash, $daka, $tash, (int) $row['id']]);
}

/** Add empty cylinders (Tash) back into typed stock and sync aggregate cylinders.empty. */
function inventory_receive_empty_cylinders(PDO $pdo, int $qty, ?string $cylinderType = null): void
{
    if ($qty <= 0) {
        return;
    }
    $type = normalize_cylinder_type($cylinderType ?? standard_cylinder_size());
    adjust_cylinder_daka_tash($pdo, $type, 0, $qty);
}

function adjust_cylinder_daka_tash(PDO $pdo, string $cylinderType, int $dakaDelta, int $tashDelta): void
{
    ensure_cylinder_daka_tash_columns($pdo);
    $pdo->prepare(
        'INSERT INTO cylinder_stock_by_type (cylinder_type, total, available, daka_qty, tash_qty)
         VALUES (?, 0, 0, 0, 0)
         ON DUPLICATE KEY UPDATE cylinder_type = cylinder_type'
    )->execute([$cylinderType]);
    $pdo->prepare(
        'UPDATE cylinder_stock_by_type
         SET daka_qty = GREATEST(0, daka_qty + ?),
             tash_qty = GREATEST(0, tash_qty + ?),
             available = GREATEST(0, available + ?)
         WHERE cylinder_type = ?'
    )->execute([$dakaDelta, $tashDelta, $dakaDelta, $cylinderType]);
    sync_cylinders_aggregate_from_typed_stock($pdo);
}

/** Set absolute Daka (full) and Tash (empty) counts for a cylinder type (initial stock / physical count). */
function set_cylinder_daka_tash_stock(PDO $pdo, string $cylinderType, int $dakaQty, int $tashQty): void
{
    ensure_cylinder_daka_tash_columns($pdo);
    $dakaQty = max(0, $dakaQty);
    $tashQty = max(0, $tashQty);
    $total = $dakaQty + $tashQty;
    $pdo->prepare(
        'INSERT INTO cylinder_stock_by_type (cylinder_type, total, available, daka_qty, tash_qty)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             total = VALUES(total),
             available = VALUES(available),
             daka_qty = VALUES(daka_qty),
             tash_qty = VALUES(tash_qty)'
    )->execute([$cylinderType, $total, $dakaQty, $dakaQty, $tashQty]);
    sync_cylinders_aggregate_from_typed_stock($pdo);
}

function cylinder_daka_tash_totals(PDO $pdo): array
{
    ensure_cylinder_daka_tash_columns($pdo);
    if (!column_exists($pdo, 'cylinder_stock_by_type', 'daka_qty')) {
        $row = $pdo->query('SELECT COALESCE(SUM(available), 0) AS daka, 0 AS tash FROM cylinders')->fetch();
        return ['daka' => (int) ($row['daka'] ?? 0), 'tash' => (int) ($row['tash'] ?? 0)];
    }
    $row = $pdo->query('SELECT COALESCE(SUM(daka_qty), 0) AS daka, COALESCE(SUM(tash_qty), 0) AS tash FROM cylinder_stock_by_type')->fetch();
    return ['daka' => (int) ($row['daka'] ?? 0), 'tash' => (int) ($row['tash'] ?? 0)];
}

/** Daka/Tash counts for one cylinder type (defaults to standard_cylinder_size()). */
function cylinder_daka_tash_for_type(PDO $pdo, ?string $cylinderType = null): array
{
    $type = $cylinderType ?? standard_cylinder_size();
    ensure_cylinder_daka_tash_columns($pdo);
    if (!column_exists($pdo, 'cylinder_stock_by_type', 'daka_qty')) {
        return ['daka' => 0, 'tash' => 0];
    }
    $stmt = $pdo->prepare('SELECT COALESCE(daka_qty, 0), COALESCE(tash_qty, 0) FROM cylinder_stock_by_type WHERE cylinder_type = ?');
    $stmt->execute([$type]);
    $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

    return ['daka' => (int) ($row[0] ?? 0), 'tash' => (int) ($row[1] ?? 0)];
}

function cylinder_tash_available(PDO $pdo, string $cylinderType): int
{
    ensure_cylinder_daka_tash_columns($pdo);
    if (!column_exists($pdo, 'cylinder_stock_by_type', 'tash_qty')) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT tash_qty FROM cylinder_stock_by_type WHERE cylinder_type = ?');
    $stmt->execute([$cylinderType]);
    return max(0, (int) $stmt->fetchColumn());
}

function ensure_supplier_transaction_notes_column(PDO $pdo): void
{
    if (!table_exists($pdo, 'supplier_transactions')) {
        return;
    }
    if (column_exists($pdo, 'supplier_transactions', 'notes')) {
        return;
    }
    $after = column_exists($pdo, 'supplier_transactions', 'payment_status') ? 'payment_status' : 'remaining_amount';
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN notes TEXT NULL AFTER {$after}");
}

function supplier_transaction_received_qty(array $row): int
{
    $totalReceived = (int) ($row['total_received'] ?? 0);
    if ($totalReceived > 0) {
        return $totalReceived;
    }
    $quantity = (int) ($row['quantity'] ?? 0);
    if ($quantity > 0) {
        return $quantity;
    }

    return max(0, (int) round((float) ($row['inventory_quantity'] ?? 0)));
}

function supplier_payment_type_label(string $paymentType): string
{
    $paymentType = trim($paymentType);
    if ($paymentType === '') {
        return 'â€”';
    }
    $key = 'suppliers.pay_' . strtolower($paymentType);
    $label = __($key);

    return $label !== $key ? $label : $paymentType;
}

function supplier_bill_status_key(float $remaining, float $paid): string
{
    if ($remaining <= 0.00001) {
        return 'paid';
    }
    if ($paid > 0) {
        return 'partial';
    }

    return 'due';
}


