<?php

declare(strict_types=1);

$pdo = db();
ensure_financial_expense_tables($pdo);

$langQ = i18n_lang_query();
$defaultRange = financial_overview_period_range('daily');
$defaultFrom = $defaultRange['from']->format('Y-m-d');
$defaultTo = $defaultRange['to']->format('Y-m-d');

$logFrom = trim((string) ($_GET['log_from'] ?? ''));
$logTo = trim((string) ($_GET['log_to'] ?? ''));
if ($logFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logFrom)) {
    $logFrom = $defaultFrom;
}
if ($logTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logTo)) {
    $logTo = $defaultTo;
}
if ($logFrom > $logTo) {
    [$logFrom, $logTo] = [$logTo, $logFrom];
}

$logType = trim((string) ($_GET['log_type'] ?? ''));
$allowedLogTypes = [
    'inflow_order',
    'inflow_customer_opening',
    'customer_opening_recorded',
    'inflow_opening',
    'inflow_cash',
    'outflow_cash',
    'outflow_supplier_purchase',
    'outflow_supplier',
    'outflow_expense',
    'outflow_salary',
];
if ($logType !== '' && !in_array($logType, $allowedLogTypes, true)) {
    $logType = '';
}
$logDirection = trim((string) ($_GET['log_direction'] ?? 'all'));
if (!in_array($logDirection, ['all', 'in', 'out'], true)) {
    $logDirection = 'all';
}
$logQ = trim((string) ($_GET['log_q'] ?? ''));
$logPage = max(1, (int) ($_GET['log_page'] ?? 1));
$logPerPage = (int) ($_GET['log_per_page'] ?? 25);
if (!in_array($logPerPage, [10, 25, 50, 100], true)) {
    $logPerPage = 25;
}

$logFilterParams = static function (array $extra = []) use ($logFrom, $logTo, $logType, $logDirection, $logQ, $logPage, $logPerPage): array {
    $base = [
        'module' => 'cash_log',
        'log_from' => $logFrom,
        'log_to' => $logTo,
        'log_direction' => $logDirection,
        'log_per_page' => (string) $logPerPage,
    ];
    if ($logType !== '') {
        $base['log_type'] = $logType;
    }
    if ($logQ !== '') {
        $base['log_q'] = $logQ;
    }
    if ($logPage > 1) {
        $base['log_page'] = (string) $logPage;
    }
    if (i18n_locale() === 'ps') {
        $base['lang'] = 'ps';
    }
    return array_merge($base, $extra);
};

$buildLogUrl = static function (array $extra = []) use ($logFilterParams): string {
    $params = array_merge($logFilterParams(), $extra);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
};

if (($_GET['ajax'] ?? '') === 'log_feed') {
    header('Content-Type: application/json; charset=utf-8');
    $feedFrom = trim((string) ($_GET['log_from'] ?? ''));
    $feedTo = trim((string) ($_GET['log_to'] ?? ''));
    if ($feedFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $feedFrom)) {
        $feedFrom = $defaultFrom;
    }
    if ($feedTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $feedTo)) {
        $feedTo = $defaultTo;
    }
    if ($feedFrom > $feedTo) {
        [$feedFrom, $feedTo] = [$feedTo, $feedFrom];
    }
    $feedType = trim((string) ($_GET['log_type'] ?? ''));
    if ($feedType !== '' && !in_array($feedType, $allowedLogTypes, true)) {
        $feedType = '';
    }
    $feedDirection = trim((string) ($_GET['log_direction'] ?? 'all'));
    if (!in_array($feedDirection, ['all', 'in', 'out'], true)) {
        $feedDirection = 'all';
    }
    $feedQ = trim((string) ($_GET['log_q'] ?? ''));
    $feedPage = max(1, (int) ($_GET['log_page'] ?? 1));
    $feedPerPage = (int) ($_GET['log_per_page'] ?? 25);
    if (!in_array($feedPerPage, [10, 25, 50, 100], true)) {
        $feedPerPage = 25;
    }
    $feed = financial_build_cash_log_feed($pdo, $feedFrom, $feedTo, $feedType, $feedDirection, $feedQ, $feedPage, $feedPerPage);
    $items = financial_cash_log_feed_json_items($feed['items']);
    echo json_encode([
        'ok' => true,
        'items' => $items,
        'pagination' => $feed['pagination'],
        'summary' => $feed['summary'],
        'range_text' => format_date_pk($feedFrom) . ' – ' . format_date_pk($feedTo),
        'summary_text' => __('dashboard.log_results_summary', [
            'total' => (string) $feed['summary']['total'],
            'in' => $feed['summary']['in_formatted'],
            'out' => $feed['summary']['out_formatted'],
            'net' => $feed['summary']['net_formatted'],
        ]),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['ajax'] ?? '') === 'tx_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $txType = trim((string) ($_GET['type'] ?? ''));
    $txId = (int) ($_GET['id'] ?? 0);
    $detail = financial_transaction_detail($pdo, $txType, $txId);
    if (!$detail) {
        echo json_encode(['ok' => false, 'message' => __('dashboard.detail_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'detail' => $detail], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'export_cash_log_pdf') {
    $allLogTx = financial_overview_transactions($pdo, $logFrom, $logTo);
    $filteredLogTx = financial_filter_transactions($allLogTx, [
        'type' => $logType,
        'direction' => $logDirection,
        'q' => $logQ,
    ]);
    $summary = [];
    if ($logType !== '') {
        $summary[] = __('dashboard.filter_type') . ': ' . match ($logType) {
            'inflow_order' => __('dashboard.tx_type_order_payment'),
            'inflow_customer_opening' => __('dashboard.tx_type_customer_opening_payment'),
            'customer_opening_recorded' => __('dashboard.tx_type_customer_opening_recorded'),
            'inflow_opening' => __('dashboard.tx_type_opening'),
            'inflow_cash' => __('dashboard.tx_type_cash_in'),
            'outflow_cash' => __('dashboard.tx_type_cash_out'),
            'outflow_supplier_purchase' => __('dashboard.tx_type_supplier_purchase'),
            'outflow_supplier' => __('dashboard.tx_type_supplier'),
            'outflow_expense' => __('dashboard.tx_type_expense'),
            'outflow_salary' => __('dashboard.tx_type_salary'),
            default => $logType,
        };
    }
    if ($logDirection !== 'all') {
        $summary[] = __('dashboard.filter_direction') . ': ' . ($logDirection === 'in' ? __('dashboard.filter_direction_in') : __('dashboard.filter_direction_out'));
    }
    if ($logQ !== '') {
        $summary[] = __('dashboard.filter_search') . ': ' . $logQ;
    }
    financial_render_cash_log_pdf($pdo, $filteredLogTx, $logFrom, $logTo, $summary);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['cash_action'] ?? ''));
    $redirectExtra = ['log_page' => '1'];
    foreach (['log_from', 'log_to', 'log_type', 'log_direction', 'log_q', 'log_per_page'] as $logKey) {
        if (isset($_POST[$logKey]) && (string) $_POST[$logKey] !== '') {
            $redirectExtra[$logKey] = (string) $_POST[$logKey];
        }
    }
    $redirect = $buildLogUrl($redirectExtra);

    if ($action === 'delete_expense') {
        $id = (int) ($_POST['expense_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM general_expenses WHERE id = ?')->execute([$id]);
        }
        header('Location: ' . $redirect . '&msg=expense_deleted');
        exit;
    }
    if ($action === 'delete_salary') {
        $id = (int) ($_POST['salary_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM employee_salaries WHERE id = ?')->execute([$id]);
        }
        header('Location: ' . $redirect . '&msg=salary_deleted');
        exit;
    }
}

$logFeed = financial_build_cash_log_feed($pdo, $logFrom, $logTo, $logType, $logDirection, $logQ, $logPage, $logPerPage);
$logPagination = $logFeed['pagination'];
$logSummaryText = __('dashboard.log_results_summary', [
    'total' => (string) $logFeed['summary']['total'],
    'in' => $logFeed['summary']['in_formatted'],
    'out' => $logFeed['summary']['out_formatted'],
    'net' => $logFeed['summary']['net_formatted'],
]);
$logInitialPayload = [
    'ok' => true,
    'items' => financial_cash_log_feed_json_items($logFeed['items']),
    'pagination' => $logFeed['pagination'],
    'summary' => $logFeed['summary'],
    'range_text' => format_date_pk($logFrom) . ' – ' . format_date_pk($logTo),
    'summary_text' => $logSummaryText,
];

$flashMsg = match ((string) ($_GET['msg'] ?? '')) {
    'expense_deleted' => __('dashboard.msg_expense_deleted'),
    'salary_deleted' => __('dashboard.msg_salary_deleted'),
    default => '',
};
$flashErr = '';

$logRangeText = format_date_pk($logFrom) . ' – ' . format_date_pk($logTo);
$txDetailBaseUrl = $buildLogUrl(['ajax' => 'tx_detail', 'log_page' => null]);
$cashBookUrl = '?module=cash' . $langQ;

$logHiddenFields = static function () use ($logFrom, $logTo, $logType, $logDirection, $logQ, $logPerPage): void {
    ?>
    <input type="hidden" name="log_from" value="<?= e($logFrom) ?>">
    <input type="hidden" name="log_to" value="<?= e($logTo) ?>">
    <input type="hidden" name="log_direction" value="<?= e($logDirection) ?>">
    <input type="hidden" name="log_per_page" value="<?= (int) $logPerPage ?>">
    <?php if ($logType !== ''): ?><input type="hidden" name="log_type" value="<?= e($logType) ?>"><?php endif; ?>
    <?php if ($logQ !== ''): ?><input type="hidden" name="log_q" value="<?= e($logQ) ?>"><?php endif; ?>
    <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
    <?php
};

ob_start();
?>
<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 pb-8 px-1 sm:px-0">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900"><?= e(__('cash_log.page_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl"><?= e(__('cash_log.page_intro')) ?></p>
            <p class="text-xs text-slate-400 mt-2"><?= e(__('cash_log.range_label')) ?>: <span id="cashLogRangeText" class="font-medium text-slate-600"><?= e($logRangeText) ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="<?= e($buildLogUrl(['action' => 'export_cash_log_pdf', 'log_page' => null])) ?>" target="_blank" rel="noopener" class="btn btn-primary text-sm w-full sm:w-auto"><?= e(__('dashboard.btn_export_pdf')) ?></a>
            <a href="<?= e($cashBookUrl) ?>" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('cash_log.back_to_cash')) ?></a>
        </div>
    </div>

    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= e($flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= e($flashErr) ?></div>
    <?php endif; ?>

    <section class="app-card overflow-hidden" aria-labelledby="cash-tx-log">
        <div class="px-3 sm:px-6 py-4 border-b border-slate-100">
            <h2 id="cash-tx-log" class="text-lg font-semibold text-slate-800"><?= e(__('dashboard.section_daily_log')) ?></h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= e(__('cash_log.timeline_hint')) ?></p>

            <form id="cashLogFilterForm" method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mt-4 p-3 rounded-xl bg-slate-50 border border-slate-100">
                <input type="hidden" name="module" value="cash_log">
                <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_from')) ?></span>
                    <input type="date" name="log_from" value="<?= e($logFrom) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_to')) ?></span>
                    <input type="date" name="log_to" value="<?= e($logTo) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_type')) ?></span>
                    <select name="log_type" class="app-input mt-1">
                        <option value=""><?= e(__('dashboard.filter_all_types')) ?></option>
                        <option value="inflow_order" <?= $logType === 'inflow_order' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_order_payment')) ?></option>
                        <option value="inflow_customer_opening" <?= $logType === 'inflow_customer_opening' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_customer_opening_payment')) ?></option>
                        <option value="customer_opening_recorded" <?= $logType === 'customer_opening_recorded' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_customer_opening_recorded')) ?></option>
                        <option value="inflow_opening" <?= $logType === 'inflow_opening' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_opening')) ?></option>
                        <option value="outflow_supplier_purchase" <?= $logType === 'outflow_supplier_purchase' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_supplier_purchase')) ?></option>
                        <option value="outflow_supplier" <?= $logType === 'outflow_supplier' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_supplier')) ?></option>
                        <option value="outflow_expense" <?= $logType === 'outflow_expense' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_expense')) ?></option>
                        <option value="outflow_salary" <?= $logType === 'outflow_salary' ? 'selected' : '' ?>><?= e(__('dashboard.tx_type_salary')) ?></option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_direction')) ?></span>
                    <select name="log_direction" class="app-input mt-1">
                        <option value="all" <?= $logDirection === 'all' ? 'selected' : '' ?>><?= e(__('dashboard.filter_all_directions')) ?></option>
                        <option value="in" <?= $logDirection === 'in' ? 'selected' : '' ?>><?= e(__('dashboard.filter_direction_in')) ?></option>
                        <option value="out" <?= $logDirection === 'out' ? 'selected' : '' ?>><?= e(__('dashboard.filter_direction_out')) ?></option>
                    </select>
                </label>
                <label class="block sm:col-span-2 xl:col-span-1">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_search')) ?></span>
                    <input type="search" name="log_q" value="<?= e($logQ) ?>" placeholder="<?= e(__('dashboard.filter_search_ph')) ?>" class="app-input mt-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600"><?= e(__('dashboard.filter_per_page')) ?></span>
                    <select name="log_per_page" class="app-input mt-1">
                        <?php foreach ([10, 25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $logPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2 sm:col-span-2 xl:col-span-2">
                    <button type="submit" class="btn btn-primary flex-1"><?= e(__('dashboard.btn_apply_filters')) ?></button>
                    <a href="<?= e($buildLogUrl(['log_from' => $defaultFrom, 'log_to' => $defaultTo, 'log_direction' => 'all', 'log_type' => null, 'log_q' => null, 'log_page' => null])) ?>" class="btn btn-soft text-center"><?= e(__('dashboard.btn_reset_filters')) ?></a>
                </div>
            </form>

            <p id="cashLogSummaryText" class="text-xs text-slate-500 mt-3"><?= e($logSummaryText) ?></p>
        </div>

        <div id="cashLogEmpty" class="hidden p-6 text-slate-500 text-sm"><?= e(__('dashboard.no_cash_movements')) ?></div>

        <div id="cashLogMobileList" class="md:hidden divide-y divide-slate-100 border-t border-slate-100 transition-opacity duration-200"></div>

        <div class="hidden md:block overflow-x-auto border-t border-slate-100">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-slate-50">
                    <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                        <th class="p-3"><?= e(__('dashboard.col_time')) ?></th>
                        <th class="p-3"><?= e(__('dashboard.col_type')) ?></th>
                        <th class="p-3"><?= e(__('dashboard.col_description')) ?></th>
                        <th class="p-3 text-end"><?= e(__('dashboard.col_in')) ?></th>
                        <th class="p-3 text-end"><?= e(__('dashboard.col_out')) ?></th>
                        <th class="p-3 text-end"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="cashLogTableBody" class="transition-opacity duration-200"></tbody>
            </table>
        </div>

        <nav id="cashLogPagination" class="px-3 sm:px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row flex-wrap items-center justify-between gap-3 <?= $logPagination['total_pages'] <= 1 ? 'hidden' : '' ?>" aria-label="<?= e(__('dashboard.pagination_label')) ?>">
            <p id="cashLogPaginationText" class="text-sm text-slate-600 text-center sm:text-start">
                <?= e(__('dashboard.pagination_page', [
                    'page' => (string) $logPagination['page'],
                    'pages' => (string) $logPagination['total_pages'],
                    'total' => (string) $logPagination['total'],
                ])) ?>
            </p>
            <div class="flex flex-wrap gap-2 justify-center">
                <button type="button" id="cashLogPrevBtn" class="btn btn-soft text-sm" <?= $logPagination['page'] <= 1 ? 'disabled' : '' ?>><?= e(__('dashboard.pagination_prev')) ?></button>
                <button type="button" id="cashLogNextBtn" class="btn btn-soft text-sm" <?= $logPagination['page'] >= $logPagination['total_pages'] ? 'disabled' : '' ?>><?= e(__('dashboard.pagination_next')) ?></button>
            </div>
        </nav>
    </section>

    <div id="txDetailModal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-tx-modal-close></div>
        <div class="absolute inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg max-h-[90vh] sm:max-h-[85vh] overflow-hidden pointer-events-auto flex flex-col" role="dialog" aria-modal="true" aria-labelledby="txDetailTitle">
                <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 shrink-0">
                    <h3 id="txDetailTitle" class="text-lg font-semibold text-slate-900"><?= e(__('dashboard.detail_title')) ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-2xl leading-none p-1" data-tx-modal-close aria-label="<?= e(__('common.close')) ?>">&times;</button>
                </div>
                <div id="txDetailBody" class="p-4 sm:p-5 overflow-y-auto text-sm space-y-3 flex-1 min-h-0"></div>
                <div class="px-4 sm:px-5 py-3 border-t border-slate-100 flex justify-end shrink-0">
                    <button type="button" class="btn btn-soft w-full sm:w-auto" data-tx-modal-close><?= e(__('common.close')) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('cashLogFilterForm');
    const mobileList = document.getElementById('cashLogMobileList');
    const tableBody = document.getElementById('cashLogTableBody');
    const emptyEl = document.getElementById('cashLogEmpty');
    const summaryEl = document.getElementById('cashLogSummaryText');
    const rangeEl = document.getElementById('cashLogRangeText');
    const paginationNav = document.getElementById('cashLogPagination');
    const paginationText = document.getElementById('cashLogPaginationText');
    const prevBtn = document.getElementById('cashLogPrevBtn');
    const nextBtn = document.getElementById('cashLogNextBtn');
    if (!form || !mobileList || !tableBody) return;

    const badgeMap = <?= json_encode([
        'inflow_order' => 'bg-emerald-100 text-emerald-800',
        'inflow_customer_opening' => 'bg-emerald-100 text-emerald-900',
        'customer_opening_recorded' => 'bg-sky-50 text-sky-900',
        'inflow_opening' => 'bg-sky-100 text-sky-800',
        'inflow_cash' => 'bg-teal-100 text-teal-800',
        'outflow_cash' => 'bg-orange-100 text-orange-800',
        'outflow_supplier_purchase' => 'bg-violet-100 text-violet-900',
        'outflow_supplier' => 'bg-indigo-100 text-indigo-800',
        'outflow_salary' => 'bg-amber-100 text-amber-900',
    ], JSON_UNESCAPED_UNICODE) ?>;
    const defaultBadge = 'bg-slate-100 text-slate-700';
    const labels = {
        view: <?= json_encode(__('dashboard.link_view'), JSON_UNESCAPED_UNICODE) ?>,
        delete: <?= json_encode(__('common.delete'), JSON_UNESCAPED_UNICODE) ?>,
        confirmDelete: <?= json_encode(__('dashboard.confirm_delete'), JSON_UNESCAPED_UNICODE) ?>,
        pagination: <?= json_encode(__('dashboard.pagination_page'), JSON_UNESCAPED_UNICODE) ?>,
        prev: <?= json_encode(__('dashboard.pagination_prev'), JSON_UNESCAPED_UNICODE) ?>,
        next: <?= json_encode(__('dashboard.pagination_next'), JSON_UNESCAPED_UNICODE) ?>,
    };
    const langPs = <?= i18n_locale() === 'ps' ? 'true' : 'false' ?>;

    let currentPage = <?= (int) $logPagination['page'] ?>;
    let loadSeq = 0;
    let searchTimer = null;

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const badgeClass = (type) => badgeMap[type] || defaultBadge;

    const hiddenFieldsHtml = () => {
        const fd = new FormData(form);
        const parts = [];
        ['log_from', 'log_to', 'log_direction', 'log_per_page', 'log_type', 'log_q'].forEach((name) => {
            const val = (fd.get(name) || '').toString().trim();
            if (val !== '') {
                parts.push('<input type="hidden" name="' + escapeHtml(name) + '" value="' + escapeHtml(val) + '">');
            }
        });
        if (langPs) {
            parts.push('<input type="hidden" name="lang" value="ps">');
        }
        return parts.join('');
    };

    const deleteFormHtml = (action, idField, id) =>
        '<form method="post" class="inline ms-1" onsubmit="return confirm(' + JSON.stringify(labels.confirmDelete) + ');">' +
        '<input type="hidden" name="cash_action" value="' + escapeHtml(action) + '">' +
        '<input type="hidden" name="' + escapeHtml(idField) + '" value="' + id + '">' +
        hiddenFieldsHtml() +
        '<button type="submit" class="text-xs text-rose-600 hover:underline">' + escapeHtml(labels.delete) + '</button></form>';

    const actionCellHtml = (tx) => {
        let html = '<button type="button" class="btn btn-soft text-xs py-1 px-2 tx-view-btn" data-tx-type="' + escapeHtml(tx.type) + '" data-tx-id="' + tx.ref_id + '">' + escapeHtml(labels.view) + '</button>';
        if (tx.type === 'outflow_expense') {
            html += deleteFormHtml('delete_expense', 'expense_id', tx.ref_id);
        } else if (tx.type === 'outflow_salary') {
            html += deleteFormHtml('delete_salary', 'salary_id', tx.ref_id);
        }
        return html;
    };

    const renderMobile = (items) => {
        if (!items.length) {
            mobileList.innerHTML = '';
            return;
        }
        mobileList.innerHTML = items.map((tx) => {
            const inflow = tx.inflow > 0 ? '<span class="text-emerald-700 font-medium">+ ' + escapeHtml(tx.inflow_formatted) + '</span>' : '';
            const outflow = tx.outflow > 0 ? '<span class="text-rose-600 font-medium">- ' + escapeHtml(tx.outflow_formatted) + '</span>' : '';
            return '<article class="p-4 space-y-2">' +
                '<div class="flex flex-wrap items-center justify-between gap-2">' +
                '<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ' + badgeClass(tx.type) + '">' + escapeHtml(tx.type_label) + '</span>' +
                '<span class="text-xs text-slate-500 tabular-nums">' + escapeHtml(tx.time_formatted) + '</span></div>' +
                '<p class="text-sm text-slate-800">' + escapeHtml(tx.description) + '</p>' +
                '<div class="flex flex-wrap gap-3 text-sm tabular-nums">' + inflow + outflow + '</div>' +
                '<div class="flex flex-wrap gap-2 pt-1">' + actionCellHtml(tx) + '</div></article>';
        }).join('');
    };

    const renderDesktop = (items) => {
        if (!items.length) {
            tableBody.innerHTML = '';
            return;
        }
        tableBody.innerHTML = items.map((tx) =>
            '<tr class="border-t border-slate-50 hover:bg-slate-50/80">' +
            '<td class="p-3 whitespace-nowrap text-slate-600 tabular-nums">' + escapeHtml(tx.time_formatted) + '</td>' +
            '<td class="p-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ' + badgeClass(tx.type) + '">' + escapeHtml(tx.type_label) + '</span></td>' +
            '<td class="p-3 text-slate-800 max-w-xs truncate" title="' + escapeHtml(tx.description) + '">' + escapeHtml(tx.description) + '</td>' +
            '<td class="p-3 text-end tabular-nums text-emerald-700 font-medium">' + (tx.inflow_formatted ? escapeHtml(tx.inflow_formatted) : '—') + '</td>' +
            '<td class="p-3 text-end tabular-nums text-rose-600 font-medium">' + (tx.outflow_formatted ? escapeHtml(tx.outflow_formatted) : '—') + '</td>' +
            '<td class="p-3 text-end whitespace-nowrap">' + actionCellHtml(tx) + '</td></tr>'
        ).join('');
    };

    const paginationLabel = (page, pages, total) =>
        labels.pagination
            .replace('{page}', String(page))
            .replace('{pages}', String(pages))
            .replace('{total}', String(total));

    const applyFeed = (data) => {
        const items = data.items || [];
        const pag = data.pagination || {};
        currentPage = pag.page || 1;
        const hasItems = items.length > 0;
        emptyEl?.classList.toggle('hidden', hasItems);
        mobileList.classList.toggle('hidden', !hasItems);
        if (hasItems) {
            renderMobile(items);
            renderDesktop(items);
        } else {
            mobileList.innerHTML = '';
            tableBody.innerHTML = '';
        }
        if (summaryEl && data.summary_text) summaryEl.textContent = data.summary_text;
        if (rangeEl && data.range_text) rangeEl.textContent = data.range_text;
        const totalPages = pag.total_pages || 1;
        if (paginationNav) {
            paginationNav.classList.toggle('hidden', totalPages <= 1);
        }
        if (paginationText) {
            paginationText.textContent = paginationLabel(pag.page || 1, totalPages, pag.total || 0);
        }
        if (prevBtn) {
            prevBtn.disabled = (pag.page || 1) <= 1;
            prevBtn.textContent = labels.prev;
        }
        if (nextBtn) {
            nextBtn.disabled = (pag.page || 1) >= totalPages;
            nextBtn.textContent = labels.next;
        }
    };

    const syncUrl = () => {
        const u = new URL(window.location.href);
        const fd = new FormData(form);
        u.searchParams.set('module', 'cash_log');
        ['log_from', 'log_to', 'log_type', 'log_direction', 'log_q', 'log_per_page'].forEach((key) => {
            const val = (fd.get(key) || '').toString().trim();
            if (val !== '') {
                u.searchParams.set(key, val);
            } else {
                u.searchParams.delete(key);
            }
        });
        if (currentPage > 1) {
            u.searchParams.set('log_page', String(currentPage));
        } else {
            u.searchParams.delete('log_page');
        }
        if (langPs) {
            u.searchParams.set('lang', 'ps');
        }
        history.replaceState(null, '', u.pathname + u.search);
    };

    const loadFeed = async (page, opts = {}) => {
        const seq = ++loadSeq;
        const silent = !!opts.silent;
        if (!silent) {
            mobileList.classList.add('opacity-60');
            tableBody.classList.add('opacity-60');
        }
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'cash_log');
            u.searchParams.set('ajax', 'log_feed');
            const fd = new FormData(form);
            ['log_from', 'log_to', 'log_type', 'log_direction', 'log_q', 'log_per_page'].forEach((key) => {
                const val = (fd.get(key) || '').toString();
                if (val !== '') u.searchParams.set(key, val);
                else u.searchParams.delete(key);
            });
            u.searchParams.set('log_page', String(page));
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (seq !== loadSeq || !data.ok) return;
            applyFeed(data);
            syncUrl();
        } catch (_) { /* ignore */ }
        finally {
            if (seq === loadSeq) {
                mobileList.classList.remove('opacity-60');
                tableBody.classList.remove('opacity-60');
            }
        }
    };

    applyFeed(<?= json_encode($logInitialPayload, JSON_UNESCAPED_UNICODE) ?>);

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        currentPage = 1;
        loadFeed(1);
    });

    form.querySelectorAll('input[type="date"], select').forEach((el) => {
        el.addEventListener('change', () => {
            currentPage = 1;
            loadFeed(1);
        });
    });

    const searchInput = form.querySelector('input[name="log_q"]');
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadFeed(1);
        }, 400);
    });

    prevBtn?.addEventListener('click', () => {
        if (currentPage > 1) loadFeed(currentPage - 1);
    });
    nextBtn?.addEventListener('click', () => {
        loadFeed(currentPage + 1);
    });

    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadFeed(currentPage, { silent: true });
        }
    }, 30000);

    if (window.OxygenFinance?.onUpdated) {
        window.OxygenFinance.onUpdated(() => {
            if (document.visibilityState === 'visible') {
                loadFeed(currentPage, { silent: true });
            }
        });
    }
})();
</script>
<script>
(function () {
    const modal = document.getElementById('txDetailModal');
    const body = document.getElementById('txDetailBody');
    if (!modal || !body) return;

    const detailBase = <?= json_encode($txDetailBaseUrl, JSON_UNESCAPED_UNICODE) ?>;

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        body.innerHTML = '';
    };

    modal.querySelectorAll('[data-tx-modal-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.tx-view-btn');
        if (!btn) return;

        const type = btn.getAttribute('data-tx-type') || '';
        const id = btn.getAttribute('data-tx-id') || '';
        if (!type || !id) return;

        body.innerHTML = '<p class="text-slate-500"><?= e(__('dashboard.detail_loading')) ?></p>';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');

        try {
            const sep = detailBase.includes('?') ? '&' : '?';
            const res = await fetch(
                detailBase + sep + 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id),
                { headers: { 'Accept': 'application/json' } }
            );
            const data = await res.json();
            if (!data.ok || !data.detail) {
                body.innerHTML = '<p class="text-rose-600">' + escapeHtml(data.message || <?= json_encode(__('dashboard.detail_not_found'), JSON_UNESCAPED_UNICODE) ?>) + '</p>';
                return;
            }
            body.innerHTML = Object.entries(data.detail).map(([label, value]) =>
                '<div class="flex flex-col gap-1 border-b border-slate-100 pb-3 last:border-0">' +
                '<span class="text-slate-500 font-medium text-xs uppercase tracking-wide">' + escapeHtml(label) + '</span>' +
                '<span class="text-slate-900 break-words">' + escapeHtml(value ?? '') + '</span></div>'
            ).join('');
        } catch {
            body.innerHTML = '<p class="text-rose-600"><?= e(__('dashboard.detail_error')) ?></p>';
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.cash_log'), $content);
