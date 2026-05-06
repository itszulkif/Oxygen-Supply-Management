<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    public static function body(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '{}', true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }
}
