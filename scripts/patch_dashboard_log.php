<?php

declare(strict_types=1);

$dashboardPath = dirname(__DIR__) . '/modules/dashboard.php';
$sectionPath = dirname(__DIR__) . '/modules/_dash_log_section.html';

$file = file_get_contents($dashboardPath);
$section = file_get_contents($sectionPath);
if ($file === false || $section === false) {
    fwrite(STDERR, "Cannot read input files\n");
    exit(1);
}

$startMarker = '    <section class="app-card overflow-hidden" aria-labelledby="dash-tx-log">';
$start = strpos($file, $startMarker);
if ($start === false) {
    fwrite(STDERR, "Start marker not found\n");
    exit(1);
}

$endMarkers = ["\r\n</div>\r\n\r\n<script", "\n</div>\n\n<script", "\r\n</div>\n\n<script"];
$end = false;
foreach ($endMarkers as $marker) {
    $pos = strpos($file, $marker, $start);
    if ($pos !== false) {
        $end = $pos;
        break;
    }
}
if ($end === false) {
    fwrite(STDERR, "End marker not found\n");
    exit(1);
}

$newFile = substr($file, 0, $start) . rtrim($section) . substr($file, $end);
file_put_contents($dashboardPath, $newFile);
echo "Patched dashboard.php\n";
