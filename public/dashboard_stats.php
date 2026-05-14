<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $customers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $services = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if (table_exists($pdo, 'cylinders')) {
        $available = (int) $pdo->query('SELECT COALESCE(SUM(available),0) FROM cylinders')->fetchColumn();
    } elseif (table_exists($pdo, 'inventory_cylinders')) {
        $available = (int) $pdo->query("SELECT COUNT(*) FROM inventory_cylinders WHERE status = 'Available'")->fetchColumn();
    } else {
        $available = 0;
    }
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status <> 'Paid'")->fetchColumn();
    $orderExpr = service_order_amount_expr($pdo);
    $todaySales = (float) $pdo->query("SELECT COALESCE(SUM({$orderExpr}),0) FROM services s LEFT JOIN invoices i ON i.service_id = s.id WHERE s.date = CURDATE()")->fetchColumn();
    $pendingAmount = (float) $pdo->query("SELECT COALESCE(SUM(remaining_amount),0) FROM invoices WHERE status <> 'Paid'")->fetchColumn();

    echo json_encode([
        'customers' => $customers,
        'services' => $services,
        'available_cylinders' => $available,
        'unpaid_invoices' => $pending,
        'today_sales' => $todaySales,
        'pending_amount' => $pendingAmount,
        'updated_at' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load stats'], JSON_UNESCAPED_UNICODE);
}
