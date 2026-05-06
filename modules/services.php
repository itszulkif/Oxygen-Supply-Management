<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\OxygenOpsService;

$pdo = db();

if (($_GET['ajax'] ?? '') === 'search_customers') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode(['ok' => true, 'customers' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $like = '%' . $q . '%';
    $st = $pdo->prepare('SELECT id, name, phone FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC LIMIT 30');
    $st->execute([$like, $like]);
    echo json_encode(['ok' => true, 'customers' => $st->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

$typedStockRows = [];
if (table_exists($pdo, 'cylinder_stock_by_type')) {
    $typedStockRows = $pdo->query("SELECT cylinder_type, available, available_pressure FROM cylinder_stock_by_type ORDER BY FIELD(cylinder_type,'Small','Medium','Large')")->fetchAll();
}
$typedStock = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
$typedPressure = ['Small' => 0.0, 'Medium' => 0.0, 'Large' => 0.0];
foreach ($typedStockRows as $typedStockRow) {
    $type = (string) ($typedStockRow['cylinder_type'] ?? '');
    if (!array_key_exists($type, $typedStock)) {
        continue;
    }
    $typedStock[$type] = max(0, (int) floor((float) ($typedStockRow['available'] ?? 0)));
    $typedPressure[$type] = max(0, (float) ($typedStockRow['available_pressure'] ?? 0));
}
$ops = new OxygenOpsService();
$deleteId = isset($_GET['delete']) ? (int) $_GET['delete'] : 0;
$serviceType = strtolower(trim((string) ($_GET['service_type'] ?? '')));
$search = trim((string) ($_GET['search'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$printOrderId = isset($_GET['print_order']) ? (int) $_GET['print_order'] : 0;
$printOrderData = null;

if ($deleteId > 0) {
    try {
        $ops->deleteServiceWithAutomation($deleteId);
        header('Location: ?module=services&toast=' . urlencode(__('toast.order_deleted')) . i18n_lang_query());
    } catch (Throwable $e) {
        header('Location: ?module=services&toast=' . urlencode(__('toast.delete_failed')) . i18n_lang_query());
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sizes = $_POST['size'] ?? [];
    $sentRows = $_POST['sent'] ?? [];
    $receivedRows = $_POST['received'] ?? [];
    $rateRows = $_POST['rate'] ?? [];
    $salePressureRows = $_POST['sale_pressure'] ?? [];
    $billingBasisRows = $_POST['billing_basis'] ?? [];
    $pressureAddedRows = $_POST['pressure_added'] ?? [];
    $totalPriceRows = $_POST['total_price'] ?? [];
    $cylinderRows = [];
    $rowCount = max(count($sizes), count($sentRows), count($receivedRows), count($rateRows), count($salePressureRows), count($billingBasisRows), count($pressureAddedRows), count($totalPriceRows));
    for ($i = 0; $i < $rowCount; $i++) {
        $cylinderRows[] = [
            'size' => (string) ($sizes[$i] ?? ''),
            'sent' => max(0, (int) ($sentRows[$i] ?? 0)),
            'received' => max(0, (int) ($receivedRows[$i] ?? 0)),
            'rate' => max(0, (float) ($rateRows[$i] ?? 0)),
            'sale_pressure' => max(0, (float) ($salePressureRows[$i] ?? 0)),
            'billing_basis' => (string) ($billingBasisRows[$i] ?? ''),
            'pressure_added' => max(0, (float) ($pressureAddedRows[$i] ?? 0)),
            'total_price' => max(0, (float) ($totalPriceRows[$i] ?? 0)),
        ];
    }
    try {
        $payload = [
            'customer_id' => (int) request_value('customer_id', '0'),
            'date' => request_value('date', date('Y-m-d')),
            'service_charges' => max(0, (float) request_value('service_charges', '0')),
            'paid_amount' => max(0, (float) request_value('paid_amount', '0')),
            'description' => request_value('description', 'Cylinder exchange & gas refill'),
            'sale_basis' => request_value('sale_basis', 'quantity'),
            'cylinder_rows' => $cylinderRows,
        ];
        $saved = $ops->addServiceWithAutomation($payload);
        $savedServiceId = (int) ($saved['service_id'] ?? 0);
        $target = '?module=services&toast=' . urlencode(__('toast.order_saved')) . i18n_lang_query();
        if ($savedServiceId > 0) {
            $target = '?module=services&print_order=' . $savedServiceId . '&toast=' . urlencode(__('toast.order_saved')) . i18n_lang_query();
        }
        header('Location: ' . $target);
    } catch (Throwable $e) {
        header('Location: ?module=services&toast=' . urlencode($e->getMessage()) . i18n_lang_query());
    }
    exit;
}

if ($printOrderId > 0) {
    $printStmt = $pdo->prepare(
        "SELECT s.id, s.date, s.service_type, s.total_bill, s.service_charges, s.previous_balance, s.grand_total, s.paid_amount, s.remaining_balance, s.notes, c.name AS customer_name
         FROM services s
         INNER JOIN customers c ON c.id = s.customer_id
         WHERE s.id = ?
         LIMIT 1"
    );
    $printStmt->execute([$printOrderId]);
    $printOrder = $printStmt->fetch();
    if ($printOrder) {
        $printRowsStmt = $pdo->prepare(
            "SELECT cylinder_size, sent_qty, received_qty, sale_units, baqi_qty, rate, billing_basis, sale_pressure, sold_pressure_total, total_amount
             FROM service_cylinder_rows
             WHERE service_id = ?
             ORDER BY id ASC"
        );
        $printRowsStmt->execute([$printOrderId]);
        $printOrderData = [
            'id' => (int) $printOrder['id'],
            'date' => (string) $printOrder['date'],
            'customer_name' => (string) ($printOrder['customer_name'] ?? ''),
            'service_type' => (string) ($printOrder['service_type'] ?? 'refill'),
            'total_bill' => (float) ($printOrder['total_bill'] ?? 0),
            'service_charges' => (float) ($printOrder['service_charges'] ?? 0),
            'previous_balance' => (float) ($printOrder['previous_balance'] ?? 0),
            'grand_total' => (float) ($printOrder['grand_total'] ?? 0),
            'paid_amount' => (float) ($printOrder['paid_amount'] ?? 0),
            'remaining_balance' => (float) ($printOrder['remaining_balance'] ?? 0),
            'description' => (string) ($printOrder['notes'] ?? ''),
            'rows' => $printRowsStmt->fetchAll(),
        ];
    }
}

$sql = "SELECT s.*, c.name AS customer_name,
       COALESCE(s.grand_total, COALESCE(i.total_amount, (s.quantity*s.price))) AS total_amount,
       COALESCE(s.paid_amount, COALESCE(i.paid_amount, 0)) AS paid_amount,
       COALESCE(s.remaining_balance, COALESCE(i.remaining_amount, 0)) AS remaining_balance,
       COALESCE(
         (SELECT GROUP_CONCAT(CONCAT(r.cylinder_size, ':', r.sent_qty, '/', r.received_qty, '/', r.baqi_qty, ' @ ', ROUND(r.sale_pressure, 2), ' Bar') SEPARATOR ' | ')
          FROM service_cylinder_rows r WHERE r.service_id = s.id),
         ''
       ) AS cylinder_summary
       FROM services s
       INNER JOIN customers c ON c.id = s.customer_id
       LEFT JOIN invoices i ON i.service_id = s.id
       WHERE 1=1";
$params = [];
if ($serviceType !== '') { $sql .= " AND s.service_type = ?"; $params[] = $serviceType; }
if ($search !== '') { $sql .= " AND c.name LIKE ?"; $params[] = '%' . $search . '%'; }
if ($from !== '') { $sql .= " AND s.date >= ?"; $params[] = $from; }
if ($to !== '') { $sql .= " AND s.date <= ?"; $params[] = $to; }
$sql .= " ORDER BY s.id DESC LIMIT 120";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

ob_start();
?>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold"><?= e(__('services.new_order_title')) ?></h3>
        <?php if (is_array($printOrderData)): ?>
            <button type="button" id="printLastOrderBtn" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white">
                <?= e(__('common.print')) ?> (80mm)
            </button>
        <?php endif; ?>
    </div>
    <form method="post" id="orderForm" class="space-y-4">
        <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-3">
            <input type="hidden" name="service_type" value="refill">
            <label class="block relative">
                <span class="text-xs text-slate-600"><?= e(__('services.customer')) ?></span>
                <input type="hidden" name="customer_id" id="customerIdField" value="">
                <input type="search" id="customerSearchField" autocomplete="off" placeholder="<?= e(__('services.search_customer_ph')) ?>" class="border rounded-lg p-2 w-full">
                <div id="customerSearchResults" class="hidden absolute z-20 left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg text-sm"></div>
            </label>
            <label class="block">
                <span class="text-xs text-slate-600"><?= e(__('services.date')) ?></span>
                <input name="date" type="date" value="<?= date('Y-m-d') ?>" required class="border rounded-lg p-2 w-full">
            </label>
            <label class="block">
                <span class="text-xs text-slate-600"><?= e(__('services.service_charges')) ?></span>
                <input name="service_charges" id="serviceChargesField" type="number" step="0.01" min="0" class="border rounded-lg p-2 w-full" value="0">
            </label>
            <label class="block">
                <span class="text-xs text-slate-600"><?= e(__('services.default_rate')) ?></span>
                <input id="defaultRateField" type="number" step="0.01" min="0" class="border rounded-lg p-2 w-full" placeholder="<?= e(__('services.default_rate_ph')) ?>">
            </label>
            <label class="block">
                <span class="text-xs text-slate-600"><?= e(__('services.sale_basis')) ?></span>
                <select name="sale_basis" id="saleBasisField" class="border rounded-lg p-2 w-full">
                    <option value="quantity"><?= e(__('services.sale_basis_quantity')) ?></option>
                    <option value="psi"><?= e(__('services.sale_basis_psi')) ?></option>
                </select>
            </label>
        </div>
        <div id="stockValidationMessage" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
        <div class="overflow-x-auto border rounded-lg">
            <table class="w-full text-sm min-w-[900px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2 col-size"><?= e(__('services.size')) ?></th><th class="text-left p-2 col-sent"><?= e(__('services.sent')) ?></th><th class="text-left p-2 col-received"><?= e(__('services.received')) ?></th><th class="text-left p-2 col-baqi"><?= e(__('services.baqi')) ?></th><th class="text-left p-2 col-basis"><?= e(__('services.billing_basis')) ?></th><th class="text-left p-2 col-pressure"><?= e(__('services.sale_pressure')) ?></th><th class="text-left p-2 col-rate"><?= e(__('services.rate')) ?></th><th class="text-left p-2 col-total"><?= e(__('services.total_gas')) ?></th><th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody id="cylinderRowsBody"></tbody>
            </table>
        </div>
        <button type="button" id="addSizeRowBtn" class="bg-slate-100 rounded-lg px-3 py-2 text-sm w-full sm:w-auto"><?= e(__('services.add_row')) ?></button>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.total_bill')) ?></span><input id="totalBillPreview" readonly class="border rounded-lg p-2 w-full bg-slate-50"></label>
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.prev_balance')) ?></span><input id="previousBalancePreview" readonly class="border rounded-lg p-2 w-full bg-slate-50"></label>
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.grand_total')) ?></span><input id="grandTotalPreview" readonly class="border rounded-lg p-2 w-full bg-slate-50"></label>
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.paid_amount')) ?></span><input name="paid_amount" id="paidAmountField" type="number" step="0.01" min="0" class="border rounded-lg p-2 w-full" value="0"></label>
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.rem_balance')) ?></span><input id="remainingBalancePreview" readonly class="border rounded-lg p-2 w-full bg-slate-50"></label>
            <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.description')) ?></span><input name="description" value="<?= e(__('services.desc_default')) ?>" class="border rounded-lg p-2 w-full"></label>
        </div>
        <button class="bg-primary text-white rounded-lg py-2 px-4 w-full sm:w-auto"><?= e(__('services.save_order')) ?></button>
    </form>
</section>
<section class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200">
        <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2">
            <input type="hidden" name="module" value="services">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <select name="service_type" class="border rounded-lg px-3 py-2 text-sm"><option value=""><?= e(__('services.filter_service')) ?></option><option value="refill" <?= $serviceType === 'refill' ? 'selected' : '' ?>><?= e(__('services.filter_refill')) ?></option></select>
            <div class="relative">
                <input id="servicesFilterSearchField" name="search" value="<?= e($search) ?>" autocomplete="off" placeholder="<?= e(__('services.customer_filter_ph')) ?>" class="border rounded-lg px-3 py-2 text-sm w-full">
                <div id="servicesFilterSearchResults" class="hidden absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg text-sm"></div>
            </div>
            <input type="date" name="from" value="<?= e($from) ?>" class="border rounded-lg px-3 py-2 text-sm">
            <input type="date" name="to" value="<?= e($to) ?>" class="border rounded-lg px-3 py-2 text-sm">
            <button class="bg-info text-white rounded-lg px-3 py-2 text-sm w-full"><?= e(__('services.filter')) ?></button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[980px] lg:min-w-[1250px]">
            <thead class="bg-slate-50"><tr><th data-sort class="text-left p-3"><?= e(__('services.col_order')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_customer')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_date')) ?></th><th class="text-left p-3"><?= e(__('services.col_cylinders')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_total_bill')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_prev_bal')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_grand_total')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_paid')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_remaining')) ?></th><th data-sort class="text-left p-3"><?= e(__('services.col_status')) ?></th><th class="text-left p-3"><?= e(__('common.actions')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="11" class="p-4 text-slate-500"><?= e(__('services.no_orders')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $row): $status = payment_status_from_amounts((float) $row['total_amount'], (float) $row['paid_amount']); ?>
                <tr data-row="true" class="border-t border-slate-100">
                    <td class="p-3">ORD-<?= (int) $row['id'] ?></td>
                    <td class="p-3"><?= e((string) $row['customer_name']) ?></td>
                    <td class="p-3"><?= e(format_date_pk((string) $row['date'])) ?></td>
                    <td class="p-3 text-xs text-slate-700"><?= e((string) ($row['cylinder_summary'] !== '' ? $row['cylinder_summary'] : '-')) ?></td>
                    <td data-value="<?= (float) ($row['total_bill'] ?? 0) ?>" class="p-3"><?= e(format_currency((float) ($row['total_bill'] ?? 0))) ?></td>
                    <td data-value="<?= (float) ($row['previous_balance'] ?? 0) ?>" class="p-3"><?= e(format_currency((float) ($row['previous_balance'] ?? 0))) ?></td>
                    <td data-value="<?= (float) $row['total_amount'] ?>" class="p-3"><?= e(format_currency((float) $row['total_amount'])) ?></td>
                    <td data-value="<?= (float) $row['paid_amount'] ?>" class="p-3"><?= e(format_currency((float) $row['paid_amount'])) ?></td>
                    <td data-value="<?= (float) $row['remaining_balance'] ?>" class="p-3"><?= e(format_currency((float) $row['remaining_balance'])) ?></td>
                    <td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= $status === 'Paid' ? 'status-paid' : ($status === 'Partial' ? 'status-partial' : 'status-due') ?>"><?= e(payment_status_label($status)) ?></span></td>
                    <td class="p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?= e('?module=services&print_order=' . (int) $row['id'] . i18n_lang_query()) ?>" class="inline-flex rounded bg-slate-100 px-2 py-1 text-xs text-slate-700"><?= e(__('common.print')) ?></a>
                            <a href="<?= e('?module=services&delete=' . (int) $row['id'] . i18n_lang_query()) ?>" data-confirm="<?= e(__('services.confirm_delete')) ?>" class="inline-flex rounded bg-rose-100 px-2 py-1 text-xs text-rose-700"><?= e(__('services.delete')) ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
(() => {
    const i18n = <?= json_encode([
        'currency' => __('currency.symbol'),
        'stockOut' => __('services.js_stock_out'),
        'stockLabel' => __('services.js_stock_label'),
        'insufficient' => __('services.js_insufficient'),
        'stockLine' => __('services.js_stock_line'),
        'pressureLine' => __('services.js_pressure_line'),
        'noCustomers' => __('services.js_no_customers'),
        'remove' => __('services.remove'),
        'salePressureRequired' => __('services.js_sale_pressure_required'),
        'basisQuantity' => __('services.sale_basis_quantity'),
        'basisPsi' => __('services.sale_basis_psi'),
        'pressureAdded' => __('services.pressure_added'),
        'pricePerUnit' => __('services.price_per_unit'),
        'totalPrice' => __('services.total_price'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const stockByType = <?= json_encode($typedStock, JSON_UNESCAPED_UNICODE) ?>;
    const pressureByType = <?= json_encode($typedPressure, JSON_UNESCAPED_UNICODE) ?>;
    const printOrderData = <?= json_encode($printOrderData, JSON_UNESCAPED_UNICODE) ?>;
    const sizeOrder = ['Small', 'Medium', 'Large'];
    const body = document.getElementById('cylinderRowsBody');
    const form = document.getElementById('orderForm');
    const addBtn = document.getElementById('addSizeRowBtn');
    const customerField = document.getElementById('customerIdField');
    const customerSearchField = document.getElementById('customerSearchField');
    const customerSearchResults = document.getElementById('customerSearchResults');
    const filterSearchField = document.getElementById('servicesFilterSearchField');
    const filterSearchResults = document.getElementById('servicesFilterSearchResults');
    let customerSearchTimer = null;
    let filterSearchTimer = null;
    const defaultRateField = document.getElementById('defaultRateField');
    const saleBasisField = document.getElementById('saleBasisField');
    const serviceChargesField = document.getElementById('serviceChargesField');
    const paidAmountField = document.getElementById('paidAmountField');
    const previousBalancePreview = document.getElementById('previousBalancePreview');
    const totalBillPreview = document.getElementById('totalBillPreview');
    const grandTotalPreview = document.getElementById('grandTotalPreview');
    const remainingBalancePreview = document.getElementById('remainingBalancePreview');
    const stockValidationMessage = document.getElementById('stockValidationMessage');
    let previousBalance = 0;
    const printLastOrderBtn = document.getElementById('printLastOrderBtn');

    const fmt = (v) => `${i18n.currency} ${Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const printThermalOrder = (order) => {
        if (!order || typeof order !== 'object') return;
        const rows = Array.isArray(order.rows) ? order.rows : [];
        const rowsHtml = rows.map((row) => {
            const qtyLabel = row.billing_basis === 'psi'
                ? `PSI ${Number(row.sold_pressure_total || 0).toFixed(2)}`
                : `${Number(row.sale_units || 0)} x ${Number(row.rate || 0).toFixed(2)}`;
            const left = `${escapeHtml(row.cylinder_size || '')} (${escapeHtml(row.billing_basis || 'quantity')})`;
            return `<tr><td>${left}<br><small>${qtyLabel}</small></td><td style="text-align:right;">${Number(row.total_amount || 0).toFixed(2)}</td></tr>`;
        }).join('');
        const html = `<!doctype html><html><head><meta charset="utf-8"><title>Order #${Number(order.id || 0)}</title><style>
            @page { size: 80mm auto; margin: 4mm; }
            body { width: 72mm; margin: 0 auto; font: 12px/1.35 Arial, sans-serif; color: #111; }
            .center { text-align: center; }
            .sep { border-top: 1px dashed #777; margin: 6px 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 2px 0; vertical-align: top; }
            .totals td { padding-top: 3px; }
            .bold { font-weight: 700; }
        </style></head><body>
            <div class="center bold">Oxygen Order Receipt</div>
            <div class="center">ORD-${Number(order.id || 0)} | ${escapeHtml(order.date || '')}</div>
            <div class="sep"></div>
            <div><span class="bold">Customer:</span> ${escapeHtml(order.customer_name || '-')}</div>
            <div><span class="bold">Type:</span> ${escapeHtml(order.service_type || 'refill')}</div>
            <div><span class="bold">Description:</span> ${escapeHtml(order.description || '-')}</div>
            <div class="sep"></div>
            <table>${rowsHtml}</table>
            <div class="sep"></div>
            <table class="totals">
              <tr><td>Total Bill</td><td style="text-align:right;">${Number(order.total_bill || 0).toFixed(2)}</td></tr>
              <tr><td>Previous</td><td style="text-align:right;">${Number(order.previous_balance || 0).toFixed(2)}</td></tr>
              <tr><td class="bold">Grand Total</td><td style="text-align:right;" class="bold">${Number(order.grand_total || 0).toFixed(2)}</td></tr>
              <tr><td>Paid</td><td style="text-align:right;">${Number(order.paid_amount || 0).toFixed(2)}</td></tr>
              <tr><td>Remaining</td><td style="text-align:right;">${Number(order.remaining_balance || 0).toFixed(2)}</td></tr>
            </table>
            <div class="sep"></div>
            <div class="center">Thank you</div>
            <script>window.print();<\/script>
        </body></html>`;
        const printWindow = window.open('', '_blank', 'width=420,height=760');
        if (!printWindow) return;
        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
    };

    const firstAvailableSize = () => {
        const hit = sizeOrder.find((size) => Number(stockByType[size] || 0) > 0);
        return hit || 'Small';
    };

    const sizeOptionsHtml = () => {
        const selected = firstAvailableSize();
        return sizeOrder.map((size) => {
            const available = Number(stockByType[size] || 0);
            const availablePressure = Number(pressureByType[size] || 0);
            const disabled = available <= 0 ? 'disabled' : '';
            const selectedAttr = size === selected ? 'selected' : '';
            const pressureLabel = `${availablePressure.toFixed(2)} Bar`;
            const label = available <= 0
                ? `${size} ${i18n.stockOut}`
                : `${size} (${i18n.stockLabel} ${available}, PSI ${pressureLabel})`;
            return `<option value="${size}" ${selectedAttr} ${disabled}>${label}</option>`;
        }).join('');
    };

    const basisOptionsHtml = () => {
        const globalPsiMode = saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';
        if (!globalPsiMode) {
            return `<option value="quantity" selected>${i18n.basisQuantity}</option>`;
        }
        return `
            <option value="quantity">${i18n.basisQuantity}</option>
            <option value="psi" selected>${i18n.basisPsi}</option>
        `;
    };

    const rowHtml = () => `
        <tr class="border-t border-slate-100">
            <td class="p-2 col-size">
                <select name="size[]" class="border rounded-lg p-2 w-full">
                    ${sizeOptionsHtml()}
                </select>
            </td>
            <td class="p-2 col-sent"><input name="sent[]" type="number" min="0" value="0" class="border rounded-lg p-2 w-full row-sent"></td>
            <td class="p-2 col-received"><input name="received[]" type="number" min="0" value="0" class="border rounded-lg p-2 w-full row-received"></td>
            <td class="p-2 col-baqi"><input type="text" readonly value="0" class="border rounded-lg p-2 w-full bg-slate-50 row-baqi"></td>
            <td class="p-2 col-basis">
                <select name="billing_basis[]" class="border rounded-lg p-2 w-full row-basis">
                    ${basisOptionsHtml()}
                </select>
            </td>
            <td class="p-2 col-pressure">
                <input name="sale_pressure[]" type="number" min="0" step="0.01" value="0" class="border rounded-lg p-2 w-full row-sale-pressure" placeholder="Bar">
                <input name="pressure_added[]" type="hidden" value="0" class="row-pressure-added">
            </td>
            <td class="p-2 col-rate"><input name="rate[]" type="number" min="0" step="0.01" value="0" class="border rounded-lg p-2 w-full row-rate"></td>
            <td class="p-2 col-total"><input name="total_price[]" type="number" min="0" step="0.01" value="0" class="border rounded-lg p-2 w-full row-total-price"><input type="text" readonly value="${fmt(0)}" class="border rounded-lg p-2 w-full bg-slate-50 row-total hidden"></td>
            <td class="p-2"><button type="button" class="px-2 py-1 rounded bg-rose-100 text-rose-700 text-xs remove-row-btn">${i18n.remove}</button></td>
        </tr>`;

    const addRow = () => {
        body.insertAdjacentHTML('beforeend', rowHtml());
        applyDefaultRateToLastRow();
        recalc();
    };

    const applyDefaultRateToLastRow = () => {
        const rows = body.querySelectorAll('tr');
        const last = rows[rows.length - 1];
        if (!last) return;
        const rateEl = last.querySelector('.row-rate');
        const basisEl = last.querySelector('.row-basis');
        if (!(rateEl instanceof HTMLInputElement)) return;
        const defaultRate = Number(defaultRateField.value || 0);
        if (defaultRate > 0) rateEl.value = String(defaultRate);
        if (basisEl instanceof HTMLSelectElement && saleBasisField instanceof HTMLSelectElement) {
            basisEl.value = saleBasisField.value === 'psi' ? 'psi' : 'quantity';
            if (saleBasisField.value !== 'psi') {
                basisEl.innerHTML = `<option value="quantity" selected>${i18n.basisQuantity}</option>`;
            }
        }
    };

    const recalc = () => {
        let refillTotal = 0;
        const globalPsiMode = saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';
        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const baqiEl = tr.querySelector('.row-baqi');
            const rateEl = tr.querySelector('.row-rate');
            const totalEl = tr.querySelector('.row-total');
            const totalPriceEl = tr.querySelector('.row-total-price');
            const basisEl = tr.querySelector('.row-basis');
            const pressureEl = tr.querySelector('.row-sale-pressure');
            const pressureAddedEl = tr.querySelector('.row-pressure-added');
            if (!(sentEl instanceof HTMLInputElement) || !(receivedEl instanceof HTMLInputElement) || !(baqiEl instanceof HTMLInputElement) || !(rateEl instanceof HTMLInputElement) || !(totalEl instanceof HTMLInputElement) || !(totalPriceEl instanceof HTMLInputElement) || !(basisEl instanceof HTMLSelectElement) || !(pressureEl instanceof HTMLInputElement) || !(pressureAddedEl instanceof HTMLInputElement)) return;
            const sent = Number(sentEl.value || 0);
            const received = Number(receivedEl.value || 0);
            const rate = Number(rateEl.value || 0);
            const salePressure = Number(pressureEl.value || 0);
            const saleUnits = sent > 0 ? sent : received;
            const baqi = sent - received;
            const rowBasis = globalPsiMode ? 'psi' : (basisEl.value === 'psi' ? 'psi' : 'quantity');
            const psiUnits = rowBasis === 'psi' ? salePressure : 0;
            pressureAddedEl.value = String(psiUnits);
            let total = rowBasis === 'psi'
                ? Math.max(0, Number(totalPriceEl.value || 0))
                : Math.max(0, saleUnits * rate);
            if (rowBasis !== 'psi') {
                totalPriceEl.value = total.toFixed(2);
            }
            baqiEl.value = String(baqi);
            totalEl.value = fmt(total);
            refillTotal += total;
        });
        const serviceCharges = Number(serviceChargesField.value || 0);
        const paidAmount = Number(paidAmountField.value || 0);
        const totalBill = refillTotal + serviceCharges;
        const grandTotal = totalBill + previousBalance;
        const remaining = Math.max(0, grandTotal - paidAmount);
        previousBalancePreview.value = fmt(previousBalance);
        totalBillPreview.value = fmt(totalBill);
        grandTotalPreview.value = fmt(grandTotal);
        remainingBalancePreview.value = fmt(remaining);
        validateStock();
    };

    const validateStock = () => {
        const isPsi = saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';
        const requestedByType = { Small: 0, Medium: 0, Large: 0 };
        const requestedPressureByType = { Small: 0, Medium: 0, Large: 0 };
        body.querySelectorAll('tr').forEach((tr) => {
            const sizeEl = tr.querySelector('select[name="size[]"]');
            const sentEl = tr.querySelector('.row-sent');
            const pressureEl = tr.querySelector('.row-sale-pressure');
            if (!(sizeEl instanceof HTMLSelectElement) || !(sentEl instanceof HTMLInputElement) || !(pressureEl instanceof HTMLInputElement)) return;
            const size = sizeEl.value;
            if (!Object.prototype.hasOwnProperty.call(requestedByType, size)) return;
            requestedByType[size] += Number(sentEl.value || 0);
            if (isPsi) {
                requestedPressureByType[size] += Number(pressureEl.value || 0);
            }
        });

        const errors = [];
        if (isPsi) {
            sizeOrder.forEach((size) => {
                const requestedPressure = Number(requestedPressureByType[size] || 0);
                const availablePressure = Number(pressureByType[size] || 0);
                if (requestedPressure > availablePressure) {
                    errors.push(i18n.pressureLine.replace(/\{size\}/g, size).replace(/\{req\}/g, String(requestedPressure.toFixed(2))).replace(/\{avail\}/g, String(availablePressure.toFixed(2))));
                }
            });
        } else {
            sizeOrder.forEach((size) => {
                const requested = Number(requestedByType[size] || 0);
                const available = Number(stockByType[size] || 0);
                if (requested > available) {
                    errors.push(i18n.stockLine.replace(/\{size\}/g, size).replace(/\{req\}/g, String(requested)).replace(/\{avail\}/g, String(available)));
                }
            });
        }

        if (errors.length) {
            stockValidationMessage.classList.remove('hidden');
            stockValidationMessage.textContent = `${i18n.insufficient} ${errors.join(' | ')}`;
            return false;
        }
        stockValidationMessage.classList.add('hidden');
        stockValidationMessage.textContent = '';
        return true;
    };

    const fetchBalance = async () => {
        previousBalance = 0;
        recalc();
        const customerId = Number(customerField.value || 0);
        if (!customerId) return;
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'ledger');
            u.searchParams.set('customer_id', String(customerId));
            u.searchParams.set('ajax', 'balance');
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            previousBalance = Number(data.balance || 0);
        } catch (_) {
            previousBalance = 0;
        }
        recalc();
    };

    const hideCustomerResults = () => {
        customerSearchResults.classList.add('hidden');
        customerSearchResults.replaceChildren();
    };

    const selectCustomer = (c) => {
        customerField.value = String(c.id);
        customerSearchField.value = `${c.name} — ${c.phone}`;
        hideCustomerResults();
        fetchBalance();
    };

    const runCustomerSearch = async () => {
        const q = customerSearchField.value.trim();
        if (q.length < 1) {
            hideCustomerResults();
            return;
        }
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'services');
            u.searchParams.set('ajax', 'search_customers');
            u.searchParams.set('q', q);
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const list = Array.isArray(data.customers) ? data.customers : [];
            customerSearchResults.replaceChildren();
            if (!list.length) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-slate-500';
                empty.textContent = i18n.noCustomers;
                customerSearchResults.appendChild(empty);
            } else {
                list.forEach((c) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-3 py-2 hover:bg-slate-50 border-b border-slate-100 last:border-0';
                    btn.textContent = `${c.name} — ${c.phone}`;
                    btn.addEventListener('mousedown', (e) => e.preventDefault());
                    btn.addEventListener('click', () => selectCustomer(c));
                    customerSearchResults.appendChild(btn);
                });
            }
            customerSearchResults.classList.remove('hidden');
        } catch (_) {
            hideCustomerResults();
        }
    };

    const hideFilterResults = () => {
        if (!(filterSearchResults instanceof HTMLElement)) return;
        filterSearchResults.classList.add('hidden');
        filterSearchResults.replaceChildren();
    };

    const runFilterSearch = async () => {
        if (!(filterSearchField instanceof HTMLInputElement) || !(filterSearchResults instanceof HTMLElement)) return;
        const q = filterSearchField.value.trim();
        if (q.length < 1) {
            hideFilterResults();
            return;
        }
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'services');
            u.searchParams.set('ajax', 'search_customers');
            u.searchParams.set('q', q);
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const list = Array.isArray(data.customers) ? data.customers : [];
            filterSearchResults.replaceChildren();
            if (!list.length) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-slate-500';
                empty.textContent = i18n.noCustomers;
                filterSearchResults.appendChild(empty);
            } else {
                list.forEach((c) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-3 py-2 hover:bg-slate-50 border-b border-slate-100 last:border-0';
                    btn.textContent = `${c.name} — ${c.phone}`;
                    btn.addEventListener('mousedown', (e) => e.preventDefault());
                    btn.addEventListener('click', () => {
                        filterSearchField.value = c.name;
                        hideFilterResults();
                    });
                    filterSearchResults.appendChild(btn);
                });
            }
            filterSearchResults.classList.remove('hidden');
        } catch (_) {
            hideFilterResults();
        }
    };

    addBtn.addEventListener('click', addRow);
    customerSearchField.addEventListener('input', () => {
        customerField.value = '';
        previousBalance = 0;
        recalc();
        clearTimeout(customerSearchTimer);
        customerSearchTimer = window.setTimeout(runCustomerSearch, 250);
    });
    customerSearchField.addEventListener('focus', () => {
        if (customerSearchField.value.trim().length >= 1) {
            runCustomerSearch();
        }
    });
    customerSearchField.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideCustomerResults();
    });
    if (filterSearchField instanceof HTMLInputElement) {
        filterSearchField.addEventListener('input', () => {
            clearTimeout(filterSearchTimer);
            filterSearchTimer = window.setTimeout(runFilterSearch, 250);
        });
        filterSearchField.addEventListener('focus', () => {
            if (filterSearchField.value.trim().length >= 1) {
                runFilterSearch();
            }
        });
        filterSearchField.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideFilterResults();
        });
    }
    document.addEventListener('click', (e) => {
        if (e.target instanceof Node && !customerSearchField.contains(e.target) && !customerSearchResults.contains(e.target)) {
            hideCustomerResults();
        }
        if (
            filterSearchField instanceof HTMLInputElement &&
            filterSearchResults instanceof HTMLElement &&
            e.target instanceof Node &&
            !filterSearchField.contains(e.target) &&
            !filterSearchResults.contains(e.target)
        ) {
            hideFilterResults();
        }
    });
    defaultRateField.addEventListener('input', () => {
        const rate = Number(defaultRateField.value || 0);
        body.querySelectorAll('.row-rate').forEach((el) => {
            if (el instanceof HTMLInputElement) {
                el.value = String(rate);
            }
        });
        recalc();
    });
    [serviceChargesField, paidAmountField].forEach((el) => el.addEventListener('input', recalc));
    const setPsiModeUi = () => {
        const isPsi = saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';
        const toggleCols = (selector, hidden) => {
            document.querySelectorAll(selector).forEach((el) => {
                if (!(el instanceof HTMLElement)) return;
                el.classList.toggle('hidden', hidden);
            });
        };
        toggleCols('.col-sent, .col-received, .col-baqi, .col-basis, .col-rate', isPsi);
        const pressureHeaders = document.querySelectorAll('th.col-pressure');
        const rateHeaders = document.querySelectorAll('th.col-rate');
        const totalHeaders = document.querySelectorAll('th.col-total');
        pressureHeaders.forEach((el) => { if (el instanceof HTMLElement) el.textContent = isPsi ? i18n.pressureAdded : <?= json_encode(__('services.sale_pressure'), JSON_UNESCAPED_UNICODE) ?>; });
        rateHeaders.forEach((el) => { if (el instanceof HTMLElement) el.textContent = isPsi ? i18n.pricePerUnit : <?= json_encode(__('services.rate'), JSON_UNESCAPED_UNICODE) ?>; });
        totalHeaders.forEach((el) => { if (el instanceof HTMLElement) el.textContent = isPsi ? i18n.totalPrice : <?= json_encode(__('services.total_gas'), JSON_UNESCAPED_UNICODE) ?>; });

        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const basisEl = tr.querySelector('.row-basis');
            const pressureEl = tr.querySelector('.row-sale-pressure');
            const sizeEl = tr.querySelector('select[name="size[]"]');
            const rowTotalEl = tr.querySelector('.row-total');
            const rowTotalPriceEl = tr.querySelector('.row-total-price');
            if (basisEl instanceof HTMLSelectElement) {
                basisEl.value = isPsi ? 'psi' : basisEl.value;
            }
            if (sentEl instanceof HTMLInputElement) {
                sentEl.disabled = isPsi;
                if (isPsi) sentEl.value = '0';
            }
            if (receivedEl instanceof HTMLInputElement) {
                receivedEl.disabled = isPsi;
                if (isPsi) receivedEl.value = '0';
            }
            if (pressureEl instanceof HTMLInputElement) {
                pressureEl.placeholder = isPsi ? 'Bar' : 'Bar';
                const selectedSize = sizeEl instanceof HTMLSelectElement ? sizeEl.value : 'Small';
                const maxPressure = Number(pressureByType[selectedSize] || 0);
                pressureEl.max = isPsi ? String(maxPressure) : '';
            }
            if (rowTotalEl instanceof HTMLInputElement && rowTotalPriceEl instanceof HTMLInputElement) {
                rowTotalEl.classList.toggle('hidden', isPsi);
                rowTotalPriceEl.classList.toggle('hidden', !isPsi);
                rowTotalPriceEl.readOnly = !isPsi;
            }
        });
    };

    if (saleBasisField instanceof HTMLSelectElement) {
        saleBasisField.addEventListener('change', () => {
            const nextBasis = saleBasisField.value === 'psi' ? 'psi' : 'quantity';
            body.querySelectorAll('.row-basis').forEach((el) => {
                if (el instanceof HTMLSelectElement) {
                    if (nextBasis === 'psi') {
                        el.innerHTML = `
                            <option value="quantity">${i18n.basisQuantity}</option>
                            <option value="psi" selected>${i18n.basisPsi}</option>
                        `;
                    } else {
                        el.innerHTML = `<option value="quantity" selected>${i18n.basisQuantity}</option>`;
                    }
                    el.value = nextBasis;
                }
            });
            setPsiModeUi();
            recalc();
        });
    }
    body.addEventListener('input', (event) => {
        const t = event.target;
        if (t instanceof HTMLElement && (t.classList.contains('row-sent') || t.classList.contains('row-received') || t.classList.contains('row-rate') || t.classList.contains('row-sale-pressure') || t.classList.contains('row-total-price'))) {
            recalc();
        }
    });
    body.addEventListener('change', (event) => {
        const t = event.target;
        if (t instanceof HTMLElement && (t.matches('select[name="size[]"]') || t.matches('select[name="billing_basis[]"]'))) {
            setPsiModeUi();
            recalc();
        }
    });
    body.addEventListener('click', (event) => {
        const t = event.target;
        if (!(t instanceof HTMLElement) || !t.classList.contains('remove-row-btn')) return;
        const row = t.closest('tr');
        if (row) row.remove();
        if (!body.querySelector('tr')) addRow();
        recalc();
    });
    form.addEventListener('submit', (event) => {
        if (!Number(customerField.value || 0)) {
            event.preventDefault();
            customerSearchField.focus();
            return;
        }
        const globalPsiMode = saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';
        let hasMissingPressure = false;
        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const pressureEl = tr.querySelector('.row-sale-pressure');
            const totalPriceEl = tr.querySelector('.row-total-price');
            const basisEl = tr.querySelector('.row-basis');
            if (!(sentEl instanceof HTMLInputElement) || !(receivedEl instanceof HTMLInputElement) || !(pressureEl instanceof HTMLInputElement) || !(totalPriceEl instanceof HTMLInputElement) || !(basisEl instanceof HTMLSelectElement)) return;
            const sent = Number(sentEl.value || 0);
            const received = Number(receivedEl.value || 0);
            const saleUnits = sent > 0 ? sent : received;
            const pressure = Number(pressureEl.value || 0);
            const totalPrice = Number(totalPriceEl.value || 0);
            if ((globalPsiMode || basisEl.value === 'psi') && pressure <= 0) {
                hasMissingPressure = true;
            }
            if ((globalPsiMode || basisEl.value === 'psi') && totalPrice <= 0) {
                hasMissingPressure = true;
            }
            if (!globalPsiMode && basisEl.value === 'psi' && saleUnits <= 0) {
                hasMissingPressure = true;
            }
        });
        if (hasMissingPressure) {
            event.preventDefault();
            window.alert(i18n.salePressureRequired);
            return;
        }
        if (validateStock()) return;
        event.preventDefault();
    });

    if (printLastOrderBtn && printOrderData) {
        printLastOrderBtn.addEventListener('click', () => printThermalOrder(printOrderData));
    }

    addRow();
    setPsiModeUi();
    recalc();
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.services'), $content);
