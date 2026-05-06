<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/layout.php';

auth_start();
i18n_init();

$module = $_GET['module'] ?? 'dashboard';
$publicModules = ['login', 'logout'];
$allowedModules = [
    'login',
    'logout',
    'dashboard',
    'suppliers',
    'customers',
    'services',
    'ledger',
    'reports',
    'customer_reports',
    'supplier_reports',
    'suppliers_list',
    'supplier_purchases',
    'supplier_payments',
    'settings',
];

if (!in_array($module, $allowedModules, true)) {
    $module = 'dashboard';
}

if (!in_array($module, $publicModules, true)) {
    auth_require_login();
}

$moduleFile = __DIR__ . '/../modules/' . $module . '.php';
if (!file_exists($moduleFile)) {
    http_response_code(404);
    exit('Module not found');
}

require $moduleFile;
