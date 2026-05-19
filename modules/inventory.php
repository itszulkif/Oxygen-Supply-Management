<?php

$pdo = db();
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status_filter'] ?? ''));
$hasNewInventoryTable = table_exists($pdo, 'cylinders');
$hasLegacyCylinders = table_exists($pdo, 'inventory_cylinders');
$hasLegacyMovements = table_exists($pdo, 'inventory_movements');
$hasServicesTable = table_exists($pdo, 'services');
$hasTypedStockTable = table_exists($pdo, 'cylinder_stock_by_type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $total = (int) request_value('total', '0');
    $available = (int) request_value('available', '0');
    $issued = (int) request_value('issued', '0');
    $empty = (int) request_value('empty', '0');
    if ($hasNewInventoryTable) {
        $existing = $pdo->query('SELECT id FROM cylinders ORDER BY id ASC LIMIT 1')->fetch();
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE cylinders SET total = ?, available = ?, issued = ?, empty = ? WHERE id = ?');
            $stmt->execute([$total, $available, $issued, $empty, (int) $existing['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cylinders (total, available, issued, empty) VALUES (?, ?, ?, ?)');
            $stmt->execute([$total, $available, $issued, $empty]);
        }
    }
    redirect_to('inventory');
}

if ($hasNewInventoryTable) {
    $stock = $pdo->query('SELECT * FROM cylinders ORDER BY id ASC LIMIT 1')->fetch();
    $stock = $stock ?: ['total' => 0, 'available' => 0, 'issued' => 0, 'empty' => 0];
    $totalCylinders = (int) $stock['total'];
    $availableCylinders = (int) $stock['available'];
    $issuedCylinders = (int) $stock['issued'];
    $emptyCylinders = (int) $stock['empty'];
} elseif ($hasLegacyCylinders) {
    $totalCylinders = (int) $pdo->query('SELECT COUNT(*) FROM inventory_cylinders')->fetchColumn();
    $availableCylinders = (int) $pdo->query("SELECT COUNT(*) FROM inventory_cylinders WHERE status = 'Available'")->fetchColumn();
    $issuedCylinders = (int) $pdo->query("SELECT COUNT(*) FROM inventory_cylinders WHERE status = 'In Use'")->fetchColumn();
    $emptyCylinders = (int) $pdo->query("SELECT COUNT(*) FROM inventory_cylinders WHERE status = 'Maintenance'")->fetchColumn();
    $stock = [
        'total' => $totalCylinders,
        'available' => $availableCylinders,
        'issued' => $issuedCylinders,
        'empty' => $emptyCylinders,
    ];
} else {
    $stock = ['total' => 0, 'available' => 0, 'issued' => 0, 'empty' => 0];
    $totalCylinders = 0;
    $availableCylinders = 0;
    $issuedCylinders = 0;
    $emptyCylinders = 0;
}

if ($hasServicesTable) {
    $movements = $pdo->query('SELECT id, service_type AS movement_type, quantity, date AS created_at FROM services ORDER BY id DESC LIMIT 10')->fetchAll();
} elseif ($hasLegacyMovements) {
    $movements = $pdo->query('SELECT id, movement_type, quantity, created_at FROM inventory_movements ORDER BY id DESC LIMIT 10')->fetchAll();
} else {
    $movements = [];
}

ob_start();
?>
<?php
$cylinderStockCount = 0;
$cylinderStockPressure = 0.0;
if ($hasTypedStockTable) {
    $stdType = standard_cylinder_size();
    $typedStmt = $pdo->prepare('SELECT available, available_pressure FROM cylinder_stock_by_type WHERE cylinder_type = ? LIMIT 1');
    $typedStmt->execute([$stdType]);
    $typedRow = $typedStmt->fetch();
    if ($typedRow) {
        $cylinderStockCount = max(0, (int) round((float) ($typedRow['available'] ?? 0)));
        $cylinderStockPressure = max(0, (float) ($typedRow['available_pressure'] ?? 0));
    } else {
        $sumStmt = $pdo->query('SELECT COALESCE(SUM(available), 0) AS c, COALESCE(SUM(available_pressure), 0) AS p FROM cylinder_stock_by_type');
        $sumRow = $sumStmt->fetch();
        $cylinderStockCount = max(0, (int) round((float) ($sumRow['c'] ?? 0)));
        $cylinderStockPressure = max(0, (float) ($sumRow['p'] ?? 0));
    }
} else {
    $cylinderStockCount = max(0, $availableCylinders);
}
?>
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500">Total Cylinders</p>
        <p class="text-2xl font-bold text-oxygenDeep"><?= $totalCylinders ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500">Available</p>
        <p class="text-2xl font-bold text-emerald-600"><?= $availableCylinders ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500">Issued</p>
        <p class="text-2xl font-bold text-sky-600"><?= $issuedCylinders ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-sm text-slate-500">Empty</p>
        <p class="text-2xl font-bold text-amber-600"><?= $emptyCylinders ?></p>
    </div>
</section>
<section class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
    <h3 class="font-semibold mb-3"><?= e(__('cyl.standard')) ?> — <?= e(__('suppliers.stock_daka')) ?></h3>
    <div class="rounded-lg border border-slate-200 p-3 max-w-sm">
        <p class="text-xl font-bold <?= $cylinderStockCount < 10 ? 'text-danger' : 'text-primary' ?>"><?= (int) $cylinderStockCount ?> <?= e(__('cyl.standard')) ?></p>
        <?php if ($hasTypedStockTable): ?>
        <p class="text-xs text-slate-600 mt-1"><?= e(number_format($cylinderStockPressure, 2)) ?> Bar</p>
        <?php endif; ?>
        <?php if ($cylinderStockCount < 10): ?><p class="text-xs text-danger">Low stock alert (&lt; 10)</p><?php endif; ?>
    </div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-3">Update Cylinder Stock</h3>
        <?php if ($hasNewInventoryTable): ?>
            <form method="post" class="space-y-3">
                <input name="total" type="number" min="0" value="<?= (int) $stock['total'] ?>" required placeholder="Total" class="w-full border rounded-lg p-2">
                <input name="available" type="number" min="0" value="<?= (int) $stock['available'] ?>" required placeholder="Available" class="w-full border rounded-lg p-2">
                <input name="issued" type="number" min="0" value="<?= (int) $stock['issued'] ?>" required placeholder="Issued" class="w-full border rounded-lg p-2">
                <input name="empty" type="number" min="0" value="<?= (int) $stock['empty'] ?>" required placeholder="Empty" class="w-full border rounded-lg p-2">
                <button class="w-full bg-oxygen text-white rounded-lg py-2">Save Stock</button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-600">
                Stock totals are currently read-only because the new `cylinders` table is not present yet.
                Import the latest schema to enable direct stock editing.
            </p>
        <?php endif; ?>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold mb-3">Auto Movement Notice</h3>
        <p class="text-sm text-slate-600">
            Inventory now auto-updates when service is created (non-refill services reduce available stock and increase issued stock).
        </p>
    </div>
</section>

<section class="grid grid-cols-1 gap-5">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Cylinder Status Table</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[420px]">
                <thead class="bg-slate-50">
                    <tr><th class="text-left p-3">Status</th><th class="text-left p-3">Count</th></tr>
                </thead>
                <tbody>
                    <tr class="border-t border-slate-100"><td class="p-3">Available</td><td class="p-3"><?= $availableCylinders ?></td></tr>
                    <tr class="border-t border-slate-100"><td class="p-3">Issued</td><td class="p-3"><?= $issuedCylinders ?></td></tr>
                    <tr class="border-t border-slate-100"><td class="p-3">Empty</td><td class="p-3"><?= $emptyCylinders ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 font-semibold">Movement Log</div>
        <form method="get" class="p-3 grid grid-cols-1 sm:grid-cols-4 gap-2 border-b border-slate-200">
            <input type="hidden" name="module" value="inventory">
            <input name="search" value="<?= e($search) ?>" placeholder="Search service type" class="border rounded-lg px-3 py-2 text-sm">
            <select name="status_filter" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">All types</option>
                <option value="rental" <?= $statusFilter === 'rental' ? 'selected' : '' ?>>Rental</option>
                <option value="delivery" <?= $statusFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="wholesale" <?= $statusFilter === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
                <option value="refill" <?= $statusFilter === 'refill' ? 'selected' : '' ?>>Refill</option>
            </select>
            <button class="bg-sky-600 text-white rounded-lg px-3 py-2 text-sm">Filter</button>
        </form>
        <div class="overflow-x-auto">
        <table data-sortable="true" class="w-full text-sm min-w-[680px]">
            <thead class="bg-slate-50"><tr><th data-sort class="text-left p-3">Date</th><th data-sort class="text-left p-3">Action</th><th data-sort class="text-left p-3">Qty</th><th data-sort class="text-left p-3">Reference</th></tr></thead>
            <tbody>
                <?php if (!$movements): ?><tr><td colspan="4" class="p-4 text-slate-500">No movement logs found yet.</td></tr><?php endif; ?>
                <?php foreach ($movements as $row): ?>
                    <?php
                    if ($search !== '' && stripos((string) $row['movement_type'], $search) === false) {
                        continue;
                    }
                    if ($statusFilter !== '' && strtolower((string) $row['movement_type']) !== strtolower($statusFilter)) {
                        continue;
                    }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><?= e(format_date_pk((string) $row['created_at'])) ?></td>
                        <td class="p-3"><?= e($row['movement_type']) ?></td>
                        <td class="p-3"><?= (int) $row['quantity'] ?></td>
                        <td class="p-3">ORD-<?= (int) ($row['id'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
render_layout('Inventory', $content);
