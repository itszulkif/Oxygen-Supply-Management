<?php

$f = file_get_contents(dirname(__DIR__) . '/modules/ledger.php');
$markers = [
    "if (false && (\$export === 'supplier_detail_pdf'",
    "if ((\$export === 'supplier_detail_pdf'",
];
$start = false;
foreach ($markers as $m) {
    $pos = strpos($f, $m);
    if ($pos !== false) {
        $start = $pos;
        break;
    }
}
$end = strpos($f, '$customerDetail = null;', $start ?: 0);
if ($start === false || $end === false) {
    fwrite(STDERR, "markers not found start=$start end=$end\n");
    exit(1);
}
file_put_contents(dirname(__DIR__) . '/modules/ledger.php', substr($f, 0, $start) . substr($f, $end));
echo "OK\n";
