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

require_once __DIR__ . '/i18n.php';
