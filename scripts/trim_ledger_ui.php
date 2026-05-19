<?php

$f = file_get_contents(dirname(__DIR__) . '/modules/ledger.php');
$start = strpos($f, '<?php else: ?>');
$end = strpos($f, '<?php if ($entity === \'customer\' && $customerDetail): ?>', $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "UI block not found\n");
    exit(1);
}
$f = substr($f, 0, $start) . substr($f, $end);
$f = str_replace('<?php if ($entity === \'customer\' && $customerDetail): ?>', '<?php if ($customerDetail): ?>', $f);
$f = str_replace('?module=ledger&entity=customer&view_customer=', '?module=ledger&view_customer=', $f);
$f = str_replace('?module=ledger&entity=customer<?= i18n_lang_query() ?>', '?module=ledger<?= i18n_lang_query() ?>', $f);
$start2 = strpos($f, '<?php if ($entity === \'supplier\' && $supplierDetail): ?>');
if ($start2 !== false) {
    $end2 = strpos($f, '<script>', $start2);
    if ($end2 !== false) {
        $f = substr($f, 0, $start2) . substr($f, $end2);
    }
}
$f = preg_replace('/<\?php endif; \?>\s*<\?php endif; \?>\s*<\?php if \(\$ledgerScrollToPayment\)/', "<?php endif; ?>\n<?php if (\$ledgerScrollToPayment)", $f);
file_put_contents(dirname(__DIR__) . '/modules/ledger.php', $f);
echo "OK\n";
