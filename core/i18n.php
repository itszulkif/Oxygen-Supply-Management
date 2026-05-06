<?php

declare(strict_types=1);

/**
 * English / Pashto (پښتو) UI strings and locale handling.
 */
function i18n_init(): void
{
    if (!isset($_GET['lang'])) {
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['lang'])) {
            if (isset($_COOKIE['app_lang']) && $_COOKIE['app_lang'] === 'ps') {
                $_SESSION['lang'] = 'ps';
            } else {
                $_SESSION['lang'] = 'en';
            }
        }
        return;
    }
    $lang = (string) $_GET['lang'];
    if (!in_array($lang, ['en', 'ps'], true)) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['lang'] = $lang;
    }
    setcookie('app_lang', $lang, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function i18n_locale(): string
{
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['lang']) && $_SESSION['lang'] === 'ps') {
        return 'ps';
    }
    return (isset($_COOKIE['app_lang']) && $_COOKIE['app_lang'] === 'ps') ? 'ps' : 'en';
}

function i18n_is_rtl(): bool
{
    return i18n_locale() === 'ps';
}

/** Append to query strings, e.g. "?module=dashboard" . i18n_lang_query() */
function i18n_lang_query(): string
{
    return i18n_locale() === 'ps' ? '&lang=ps' : '';
}

/** Preserve current GET params and switch UI language. */
function i18n_switch_url(string $newLang): string
{
    $q = $_GET;
    $q['lang'] = $newLang;
    if (!isset($q['module']) || $q['module'] === '') {
        $q['module'] = 'dashboard';
    }
    return '?' . http_build_query($q);
}

/**
 * @param array<string, string> $replace
 */
function __(string $key, array $replace = []): string
{
    static $cache = [];
    $locale = i18n_locale();
    if (!isset($cache[$locale])) {
        $file = __DIR__ . '/../lang/' . $locale . '.php';
        $cache[$locale] = is_file($file) ? require $file : [];
    }
    $str = $cache[$locale][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace('{' . $k . '}', (string) $v, $str);
    }
    return $str;
}

function service_type_label(string $type): string
{
    $k = 'svc.' . strtolower($type);
    $t = __($k);
    return $t !== $k ? $t : ucfirst($type);
}

/** Cylinder size labels (supplier/inventory): Small / Medium / Large / Mixed */
function cylinder_size_label(string $size): string
{
    return match ($size) {
        'Small' => __('cyl.small'),
        'Medium' => __('cyl.medium'),
        'Large' => __('cyl.large'),
        'Mixed' => __('cyl.mixed'),
        default => $size,
    };
}

/** Supplier transaction payment_status (PAID / PARTIAL / DUE) */
function supplier_tx_payment_status_label(string $status): string
{
    $u = strtoupper(trim($status));
    return match ($u) {
        'PAID' => __('suppliers.status_paid'),
        'PARTIAL' => __('suppliers.status_partial'),
        'DUE' => __('suppliers.status_due'),
        default => $status,
    };
}

function i18n_month_short(int $month): string
{
    $month = max(1, min(12, $month));
    if (i18n_locale() === 'ps') {
        $ps = ['جنوري', 'فبروري', 'مارچ', 'اپریل', 'مۍ', 'جون', 'جولای', 'اګست', 'سپتمبر', 'اکتوبر', 'نومبر', 'دسمبر'];
        return $ps[$month - 1];
    }
    $en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return $en[$month - 1];
}
