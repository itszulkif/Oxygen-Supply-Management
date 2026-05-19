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

if (($_GET['ajax'] ?? '') === 'cylinder_balance') {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int) ($_GET['customer_id'] ?? 0);
    if ($cid <= 0) {
        echo json_encode(['ok' => true, 'total_daka' => 0, 'total_tash' => 0, 'net' => 0, 'direction' => 'even'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $totalDaka = 0;
    $totalTash = 0;
    if (table_exists($pdo, 'service_cylinder_rows')) {
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(r.sent_qty), 0) AS total_daka,
                COALESCE(SUM(r.received_qty), 0) AS total_tash
             FROM service_cylinder_rows r
             INNER JOIN services s ON s.id = r.service_id
             WHERE s.customer_id = ?'
        );
        $st->execute([$cid]);
        $row = $st->fetch();
        if ($row) {
            $totalDaka = (int) ($row['total_daka'] ?? 0);
            $totalTash = (int) ($row['total_tash'] ?? 0);
        }
    } else {
        $st = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) AS total_daka FROM services WHERE customer_id = ?');
        $st->execute([$cid]);
        $totalDaka = (int) $st->fetchColumn();
    }
    $snap = customer_account_snapshot($pdo, $cid);
    $cylindersOwed = (int) ($snap['cylinders_owed'] ?? 0);
    $net = $totalTash - $totalDaka;
    $direction = $cylindersOwed > 0 ? 'customer_owes' : ($net > 0 ? 'we_owe' : ($net < 0 ? 'customer_owes' : 'even'));
    echo json_encode(array_merge([
        'ok' => true,
        'total_daka' => $totalDaka,
        'total_tash' => $totalTash,
        'net' => abs($net),
        'signed_net' => $net,
        'direction' => $direction,
        'cylinders_owed' => $cylindersOwed,
        'receivable' => (float) ($snap['receivable'] ?? 0),
    ], $snap), JSON_UNESCAPED_UNICODE);
    exit;
}

$stdCylinderSize = standard_cylinder_size();
$typedStock = [$stdCylinderSize => 0];
$typedPressure = [$stdCylinderSize => 0.0];
if (table_exists($pdo, 'cylinder_stock_by_type')) {
    $stockStmt = $pdo->prepare('SELECT available, available_pressure FROM cylinder_stock_by_type WHERE cylinder_type = ? LIMIT 1');
    $stockStmt->execute([$stdCylinderSize]);
    $typedStockRow = $stockStmt->fetch();
    if ($typedStockRow) {
        $typedStock[$stdCylinderSize] = max(0, (int) floor((float) ($typedStockRow['available'] ?? 0)));
        $typedPressure[$stdCylinderSize] = max(0, (float) ($typedStockRow['available_pressure'] ?? 0));
    }
}
$ops = new OxygenOpsService();
$deleteId = isset($_GET['delete']) ? (int) $_GET['delete'] : 0;
$printOrderId = isset($_GET['print_order']) ? (int) $_GET['print_order'] : 0;
$printOrderData = null;

if ($deleteId > 0) {
    try {
        $ops->deleteServiceWithAutomation($deleteId);
        header('Location: ?module=orders&toast=' . urlencode(__('toast.order_deleted')) . i18n_lang_query());
    } catch (Throwable $e) {
        header('Location: ?module=orders&toast=' . urlencode(__('toast.delete_failed')) . i18n_lang_query());
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sizes = $_POST['size'] ?? [];
    $sentRows = $_POST['sent'] ?? [];
    $receivedRows = $_POST['received'] ?? [];
    $rateRows = $_POST['rate'] ?? [];
    $totalPriceRows = $_POST['total_price'] ?? [];
    $cylinderRows = [];
    $rowCount = max(count($sizes), count($sentRows), count($receivedRows), count($rateRows), count($totalPriceRows));
    for ($i = 0; $i < $rowCount; $i++) {
        $rowSize = trim((string) ($sizes[$i] ?? ''));
        $cylinderRows[] = [
            'size' => normalize_cylinder_type($rowSize !== '' ? $rowSize : $stdCylinderSize),
            'sent' => max(0, (int) ($sentRows[$i] ?? 0)),
            'received' => max(0, (int) ($receivedRows[$i] ?? 0)),
            'rate' => max(0, (float) ($rateRows[$i] ?? 0)),
            'total_price' => max(0, (float) ($totalPriceRows[$i] ?? 0)),
        ];
    }
    try {
        $payload = [
            'customer_id' => (int) request_value('customer_id', '0'),
            'date' => request_value('date', date('Y-m-d')),
            'service_charges' => 0,
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

$preselectedCustomer = null;
$preselectCustomerId = (int) ($_GET['customer_id'] ?? 0);
if ($preselectCustomerId > 0) {
    $preselectStmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = ? LIMIT 1');
    $preselectStmt->execute([$preselectCustomerId]);
    $row = $preselectStmt->fetch();
    if ($row) {
        $preselectedCustomer = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'phone' => (string) $row['phone'],
        ];
    }
}

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
                <span class="text-xs text-slate-600"><?= e(__('services.sale_basis')) ?></span>
                <select name="sale_basis" id="saleBasisField" class="border rounded-lg p-2 w-full">
                    <option value="quantity"><?= e(__('services.sale_basis_quantity')) ?></option>
                    <option value="psi"><?= e(__('services.sale_basis_psi')) ?></option>
                </select>
            </label>
        </div>
        <div id="customerCylinderBalance" class="hidden rounded-lg border px-3 py-3 text-sm" role="status" aria-live="polite"></div>
        <div id="stockValidationMessage" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
        <div class="overflow-x-auto border rounded-lg">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2 col-sent"><?= e(__('services.sent')) ?></th><th class="text-left p-2 col-received"><?= e(__('services.received')) ?></th><th class="text-left p-2 col-baqi"><?= e(__('services.baqi')) ?></th><th class="text-left p-2 col-rate"><?= e(__('services.rate')) ?></th><th class="text-left p-2 col-total"><?= e(__('services.total_gas')) ?></th><th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody id="cylinderRowsBody"></tbody>
            </table>
        </div>
        <button type="button" id="addRowBtn" class="bg-slate-100 rounded-lg px-3 py-2 text-sm w-full sm:w-auto"><?= e(__('services.add_row')) ?></button>
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
        'psiTotalRequired' => __('services.js_psi_total_required'),
        'basisQuantity' => __('services.sale_basis_quantity'),
        'basisPsi' => __('services.sale_basis_psi'),
        'pressureAdded' => __('services.pressure_added'),
        'pricePerUnit' => __('services.price_per_unit'),
        'totalPrice' => __('services.total_price'),
        'cylinderBalanceTitle' => __('services.cylinder_balance_title'),
        'cylinderOutstandingLabel' => __('services.cylinder_balance_outstanding_label'),
        'cylinderPaymentLabel' => __('services.cylinder_balance_payment_label'),
        'openingBalanceLabel' => __('customers.opening_balance'),
        'openingCylindersLabel' => __('customers.opening_cylinders'),
        'receivableLabel' => __('ledger.net_receivable'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const stockByType = <?= json_encode($typedStock, JSON_UNESCAPED_UNICODE) ?>;
    const pressureByType = <?= json_encode($typedPressure, JSON_UNESCAPED_UNICODE) ?>;
    const printOrderData = <?= json_encode($printOrderData, JSON_UNESCAPED_UNICODE) ?>;
    const preselectedCustomer = <?= json_encode($preselectedCustomer, JSON_UNESCAPED_UNICODE) ?>;
    const standardSize = <?= json_encode($stdCylinderSize, JSON_UNESCAPED_UNICODE) ?>;
    const body = document.getElementById('cylinderRowsBody');
    const form = document.getElementById('orderForm');
    const addBtn = document.getElementById('addRowBtn');
    const customerField = document.getElementById('customerIdField');
    const customerSearchField = document.getElementById('customerSearchField');
    const customerSearchResults = document.getElementById('customerSearchResults');
    let customerSearchTimer = null;
    const saleBasisField = document.getElementById('saleBasisField');
    const paidAmountField = document.getElementById('paidAmountField');
    const previousBalancePreview = document.getElementById('previousBalancePreview');
    const totalBillPreview = document.getElementById('totalBillPreview');
    const grandTotalPreview = document.getElementById('grandTotalPreview');
    const remainingBalancePreview = document.getElementById('remainingBalancePreview');
    const stockValidationMessage = document.getElementById('stockValidationMessage');
    const customerCylinderBalance = document.getElementById('customerCylinderBalance');
    let previousBalance = 0;
    let cylinderBalanceHistory = null;
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
                : Number(row.total_amount || 0).toFixed(2);
            const basis = escapeHtml(row.billing_basis === 'psi' ? 'PSI' : '<?= e(__('services.sale_basis_quantity')) ?>');
            return `<tr><td>${basis}<br><small>${qtyLabel}</small></td><td style="text-align:right;">${Number(row.total_amount || 0).toFixed(2)}</td></tr>`;
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

    const isPsiSaleBasis = () => saleBasisField instanceof HTMLSelectElement && saleBasisField.value === 'psi';

    const rowHtml = () => `
        <tr class="border-t border-slate-100">
            <input type="hidden" name="size[]" value="${standardSize}">
            <td class="p-2 col-sent"><input name="sent[]" type="number" min="0" value="0" class="border rounded-lg p-2 w-full row-sent"></td>
            <td class="p-2 col-received"><input name="received[]" type="number" min="0" value="0" class="border rounded-lg p-2 w-full row-received"></td>
            <td class="p-2 col-baqi"><input type="text" readonly value="0" class="border rounded-lg p-2 w-full bg-slate-50 row-baqi"></td>
            <td class="p-2 col-rate"><input name="rate[]" type="number" min="0" step="0.01" value="0" class="border rounded-lg p-2 w-full row-rate"></td>
            <td class="p-2 col-total"><input name="total_price[]" type="number" min="0" step="0.01" value="0" class="border rounded-lg p-2 w-full row-total-price"><input type="text" readonly value="${fmt(0)}" class="border rounded-lg p-2 w-full bg-slate-50 row-total hidden"></td>
            <td class="p-2"><button type="button" class="px-2 py-1 rounded bg-rose-100 text-rose-700 text-xs remove-row-btn">${i18n.remove}</button></td>
        </tr>`;

    const addRow = () => {
        body.insertAdjacentHTML('beforeend', rowHtml());
        recalc();
    };

    const recalc = () => {
        let refillTotal = 0;
        const globalPsiMode = isPsiSaleBasis();
        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const baqiEl = tr.querySelector('.row-baqi');
            const rateEl = tr.querySelector('.row-rate');
            const totalEl = tr.querySelector('.row-total');
            const totalPriceEl = tr.querySelector('.row-total-price');
            if (!(sentEl instanceof HTMLInputElement) || !(receivedEl instanceof HTMLInputElement) || !(baqiEl instanceof HTMLInputElement) || !(rateEl instanceof HTMLInputElement) || !(totalEl instanceof HTMLInputElement) || !(totalPriceEl instanceof HTMLInputElement)) return;
            const sent = Number(sentEl.value || 0);
            const received = Number(receivedEl.value || 0);
            const rate = Number(rateEl.value || 0);
            const saleUnits = sent > 0 ? sent : received;
            const rowBasis = globalPsiMode ? 'psi' : 'quantity';
            const baqi = globalPsiMode ? 0 : projectedOutstandingCylinders(tr);
            let total = rowBasis === 'psi'
                ? Math.max(0, Number(totalPriceEl.value || 0))
                : Math.max(0, rate);
            if (rowBasis !== 'psi') {
                totalPriceEl.value = total.toFixed(2);
            }
            baqiEl.value = String(baqi);
            totalEl.value = fmt(total);
            refillTotal += total;
        });
        const paidAmount = Number(paidAmountField.value || 0);
        const totalBill = refillTotal;
        const grandTotal = totalBill + previousBalance;
        const remaining = Math.max(0, grandTotal - paidAmount);
        previousBalancePreview.value = fmt(previousBalance);
        totalBillPreview.value = fmt(totalBill);
        grandTotalPreview.value = fmt(grandTotal);
        remainingBalancePreview.value = fmt(remaining);
        validateStock();
        updateCylinderBalancePanel();
    };

    const validateStock = () => {
        const isPsi = isPsiSaleBasis();
        let requested = 0;
        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            if (!(sentEl instanceof HTMLInputElement)) return;
            requested += Number(sentEl.value || 0);
        });

        const errors = [];
        const available = Number(stockByType[standardSize] || 0);
        if (isPsi) {
            // PSI billing uses total price only; no cylinder count stock check.
        } else if (requested > available) {
            errors.push(i18n.stockLine.replace(/\{req\}/g, String(requested)).replace(/\{avail\}/g, String(available)));
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


    const sumFormCylinders = () => {
        let daka = 0;
        let tash = 0;
        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            if (sentEl instanceof HTMLInputElement) daka += Number(sentEl.value || 0);
            if (receivedEl instanceof HTMLInputElement) tash += Number(receivedEl.value || 0);
        });
        return { daka, tash };
    };

    /** Outstanding cylinder count after applying history + form rows up to and including throughRow. */
    const projectedOutstandingCylinders = (throughRow = null) => {
        let owed = cylinderBalanceHistory?.ok ? Number(cylinderBalanceHistory.cylinders_owed ?? 0) : 0;
        const rows = [...body.querySelectorAll('tr')];
        for (const tr of rows) {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const sent = sentEl instanceof HTMLInputElement ? Number(sentEl.value || 0) : 0;
            const received = receivedEl instanceof HTMLInputElement ? Number(receivedEl.value || 0) : 0;
            owed += sent - received;
            if (throughRow === tr) break;
        }
        return Math.max(0, owed);
    };

    const renderCylinderBalanceSummary = () => {
        const cylCount = projectedOutstandingCylinders();
        const payAmount = fmt(previousBalance);
        const h = cylinderBalanceHistory;
        const lines = [];
        if (h?.ok && h.show_opening_balance) {
            const ob = fmt(h.opening_balance_remaining ?? 0);
            lines.push(`<p class="text-slate-700 flex flex-wrap items-baseline gap-x-1.5"><span>${escapeHtml(i18n.openingBalanceLabel)}:</span> <span class="font-semibold tabular-nums">${escapeHtml(ob)}</span></p>`);
        }
        if (h?.ok && h.show_opening_cylinders) {
            const oc = String(h.opening_cylinders_remaining ?? 0);
            lines.push(`<p class="text-slate-700 flex flex-wrap items-baseline gap-x-1.5"><span>${escapeHtml(i18n.openingCylindersLabel)}:</span> <span class="font-semibold tabular-nums">${escapeHtml(oc)}</span></p>`);
        }
        lines.push(`<p class="text-slate-700 flex flex-wrap items-baseline gap-x-1.5"><span>${escapeHtml(i18n.cylinderOutstandingLabel)}</span> <span class="text-red-600 font-bold text-base tabular-nums">${escapeHtml(String(cylCount))}</span></p>`);
        lines.push(`<p class="text-slate-700 flex flex-wrap items-baseline gap-x-1.5"><span>${escapeHtml(i18n.receivableLabel)}</span> <span class="text-red-600 font-bold text-base tabular-nums">${escapeHtml(payAmount)}</span></p>`);
        return `<div class="space-y-2">${lines.join('')}</div>`;
    };

    const updateCylinderBalancePanel = () => {
        if (!(customerCylinderBalance instanceof HTMLElement)) return;
        if (!cylinderBalanceHistory || !cylinderBalanceHistory.ok) {
            customerCylinderBalance.classList.add('hidden');
            customerCylinderBalance.replaceChildren();
            return;
        }
        customerCylinderBalance.className = 'rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm';
        customerCylinderBalance.innerHTML = `<p class="font-semibold text-slate-900 mb-2">${escapeHtml(i18n.cylinderBalanceTitle)}</p>${renderCylinderBalanceSummary()}`;
        customerCylinderBalance.classList.remove('hidden');
    };

    const hideCylinderBalancePanel = () => {
        cylinderBalanceHistory = null;
        if (customerCylinderBalance instanceof HTMLElement) {
            customerCylinderBalance.classList.add('hidden');
            customerCylinderBalance.replaceChildren();
        }
    };

    const fetchCylinderBalance = async () => {
        const customerId = Number(customerField.value || 0);
        if (!customerId) {
            hideCylinderBalancePanel();
            return;
        }
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'services');
            u.searchParams.set('ajax', 'cylinder_balance');
            u.searchParams.set('customer_id', String(customerId));
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            cylinderBalanceHistory = data && data.ok ? data : null;
        } catch (_) {
            cylinderBalanceHistory = null;
        }
        updateCylinderBalancePanel();
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
            previousBalance = Number(data.receivable ?? data.balance ?? 0);
        } catch (_) {
            previousBalance = 0;
        }
        recalc();
        await fetchCylinderBalance();
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

    addBtn.addEventListener('click', addRow);
    customerSearchField.addEventListener('input', () => {
        customerField.value = '';
        previousBalance = 0;
        hideCylinderBalancePanel();
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
    document.addEventListener('click', (e) => {
        if (e.target instanceof Node && !customerSearchField.contains(e.target) && !customerSearchResults.contains(e.target)) {
            hideCustomerResults();
        }
    });
    if (paidAmountField) paidAmountField.addEventListener('input', recalc);
    const setPsiModeUi = () => {
        const isPsi = isPsiSaleBasis();
        const toggleCols = (selector, hidden) => {
            document.querySelectorAll(selector).forEach((el) => {
                if (!(el instanceof HTMLElement)) return;
                el.classList.toggle('hidden', hidden);
            });
        };
        toggleCols('.col-sent, .col-received, .col-baqi', isPsi);
        const rateHeaders = document.querySelectorAll('th.col-rate');
        const totalHeaders = document.querySelectorAll('th.col-total');
        rateHeaders.forEach((el) => { if (el instanceof HTMLElement) el.textContent = isPsi ? i18n.pricePerUnit : <?= json_encode(__('services.rate'), JSON_UNESCAPED_UNICODE) ?>; });
        totalHeaders.forEach((el) => { if (el instanceof HTMLElement) el.textContent = isPsi ? i18n.totalPrice : <?= json_encode(__('services.total_gas'), JSON_UNESCAPED_UNICODE) ?>; });

        body.querySelectorAll('tr').forEach((tr) => {
            const sentEl = tr.querySelector('.row-sent');
            const receivedEl = tr.querySelector('.row-received');
            const rateEl = tr.querySelector('.row-rate');
            const rowTotalEl = tr.querySelector('.row-total');
            const rowTotalPriceEl = tr.querySelector('.row-total-price');
            if (sentEl instanceof HTMLInputElement) {
                sentEl.disabled = isPsi;
                if (isPsi) sentEl.value = '0';
            }
            if (receivedEl instanceof HTMLInputElement) {
                receivedEl.disabled = isPsi;
                if (isPsi) receivedEl.value = '0';
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
            setPsiModeUi();
            recalc();
        });
    }
    body.addEventListener('input', (event) => {
        const t = event.target;
        if (t instanceof HTMLElement && (t.classList.contains('row-sent') || t.classList.contains('row-received') || t.classList.contains('row-rate') || t.classList.contains('row-total-price'))) {
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
        const globalPsiMode = isPsiSaleBasis();
        let hasMissingPsiTotal = false;
        body.querySelectorAll('tr').forEach((tr) => {
            const totalPriceEl = tr.querySelector('.row-total-price');
            if (!(totalPriceEl instanceof HTMLInputElement)) return;
            const totalPrice = Number(totalPriceEl.value || 0);
            if (globalPsiMode && totalPrice <= 0) {
                hasMissingPsiTotal = true;
            }
        });
        if (hasMissingPsiTotal) {
            event.preventDefault();
            window.alert(i18n.psiTotalRequired);
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
    if (preselectedCustomer && preselectedCustomer.id) {
        selectCustomer(preselectedCustomer);
    }
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.services'), $content);
