<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/db.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relative) . '.php';
    $file = __DIR__ . '/' . $relativePath;
    if (file_exists($file)) {
        require_once $file;
    }
});
