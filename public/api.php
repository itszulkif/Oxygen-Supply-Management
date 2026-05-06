<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\CustomerController;
use App\Controllers\InvoiceController;
use App\Controllers\PaymentController;
use App\Controllers\ServiceController;
use App\Support\JsonResponse;
use App\Support\Request;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$resource = (string) ($_GET['resource'] ?? '');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$body = Request::body();

try {
    switch ($resource) {
        case 'customers':
            $controller = new CustomerController();
            if ($method === 'GET' && $id === null) {
                JsonResponse::send($controller->index());
                return;
            }
            if ($method === 'GET' && $id !== null) {
                JsonResponse::send($controller->show($id));
                return;
            }
            if ($method === 'POST') {
                JsonResponse::send($controller->store($body), 201);
                return;
            }
            if ($method === 'PUT' && $id !== null) {
                JsonResponse::send($controller->update($id, $body));
                return;
            }
            if ($method === 'DELETE' && $id !== null) {
                JsonResponse::send($controller->destroy($id));
                return;
            }
            break;

        case 'services':
            $controller = new ServiceController();
            if ($method === 'GET' && $id === null) {
                JsonResponse::send($controller->index());
                return;
            }
            if ($method === 'GET' && $id !== null) {
                JsonResponse::send($controller->show($id));
                return;
            }
            if ($method === 'POST') {
                JsonResponse::send($controller->store($body), 201);
                return;
            }
            if ($method === 'PUT' && $id !== null) {
                JsonResponse::send($controller->update($id, $body));
                return;
            }
            if ($method === 'DELETE' && $id !== null) {
                JsonResponse::send($controller->destroy($id));
                return;
            }
            break;

        case 'invoices':
            $controller = new InvoiceController();
            if ($method === 'GET' && $id === null) {
                JsonResponse::send($controller->index());
                return;
            }
            if ($method === 'GET' && $id !== null) {
                JsonResponse::send($controller->show($id));
                return;
            }
            break;

        case 'payments':
            $controller = new PaymentController();
            if ($method === 'GET') {
                JsonResponse::send($controller->index());
                return;
            }
            if ($method === 'POST') {
                JsonResponse::send($controller->store($body), 201);
                return;
            }
            break;
    }

    JsonResponse::send(['error' => 'Route not found'], 404);
} catch (\Throwable $e) {
    JsonResponse::send(['error' => $e->getMessage()], 500);
}
