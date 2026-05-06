<?php

$pdo = db();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(140) NOT NULL,
        phone VARCHAR(30) NULL,
        address TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS supplier_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        transaction_date DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supplier_tx_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS supplier_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_date DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supplier_pay_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS supplier_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        debit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        credit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        entry_date DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supplier_ledger_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cylinder_stock_by_type (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cylinder_type ENUM('Small','Medium','Large') NOT NULL UNIQUE,
        total INT NOT NULL DEFAULT 0,
        available INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS supplier_refill_breakdown (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        status_label VARCHAR(50) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        inventory_qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supplier_refill_tx FOREIGN KEY (transaction_id) REFERENCES supplier_transactions(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS refill_discrepancy (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        transaction_id INT NOT NULL,
        sent_quantity INT NOT NULL DEFAULT 0,
        received_fully INT NOT NULL DEFAULT 0,
        difference_quantity INT NOT NULL DEFAULT 0,
        notes VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_refill_discrepancy_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_refill_discrepancy_tx FOREIGN KEY (transaction_id) REFERENCES supplier_transactions(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS supplier_pending_cylinders (
        supplier_id INT NOT NULL,
        cylinder_type ENUM('Small','Medium','Large') NOT NULL,
        pending_count INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (supplier_id, cylinder_type),
        CONSTRAINT fk_supplier_pending_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    )
");

if (!column_exists($pdo, 'suppliers', 'contact_person')) {
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN contact_person VARCHAR(120) NULL AFTER name");
}
if (!column_exists($pdo, 'suppliers', 'email')) {
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN email VARCHAR(150) NULL AFTER phone");
}
if (!column_exists($pdo, 'suppliers', 'opening_balance')) {
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER address");
}
if (!column_exists($pdo, 'supplier_transactions', 'cylinder_type')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN cylinder_type ENUM('Small','Medium','Large') NOT NULL DEFAULT 'Small' AFTER supplier_id");
}
if (!column_exists($pdo, 'supplier_transactions', 'total_amount')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price");
}
if (!column_exists($pdo, 'supplier_transactions', 'paid_amount')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
}
if (!column_exists($pdo, 'supplier_transactions', 'remaining_amount')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER paid_amount");
}
if (!column_exists($pdo, 'supplier_transactions', 'payment_type')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN payment_type ENUM('Cash','Bank','Credit') NOT NULL DEFAULT 'Credit' AFTER remaining_amount");
}
if (!column_exists($pdo, 'supplier_transactions', 'payment_status')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN payment_status ENUM('PAID','PARTIAL','DUE') NOT NULL DEFAULT 'DUE' AFTER payment_type");
}
if (!column_exists($pdo, 'supplier_transactions', 'sent_quantity')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_quantity INT NOT NULL DEFAULT 0 AFTER supplier_id");
}
if (!column_exists($pdo, 'supplier_transactions', 'date_sent')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN date_sent DATE NULL AFTER sent_quantity");
}
if (!column_exists($pdo, 'supplier_transactions', 'total_received')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN total_received INT NOT NULL DEFAULT 0 AFTER quantity");
}
if (!column_exists($pdo, 'supplier_transactions', 'inventory_quantity')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN inventory_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_received");
}
if (!column_exists($pdo, 'supplier_transactions', 'received_fully_quantity')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN received_fully_quantity INT NOT NULL DEFAULT 0 AFTER inventory_quantity");
}
if (!column_exists($pdo, 'supplier_transactions', 'received_pressure_total')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN received_pressure_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER received_fully_quantity");
}
if (!column_exists($pdo, 'supplier_transactions', 'sent_pressure')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_pressure DECIMAL(12,2) NULL DEFAULT NULL AFTER date_sent");
}
if (!column_exists($pdo, 'supplier_transactions', 'sent_qty_small')) {
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_qty_small INT NOT NULL DEFAULT 0 AFTER sent_pressure");
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_qty_medium INT NOT NULL DEFAULT 0 AFTER sent_qty_small");
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_qty_large INT NOT NULL DEFAULT 0 AFTER sent_qty_medium");
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_pressure_small DECIMAL(12,2) NULL DEFAULT NULL AFTER sent_qty_large");
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_pressure_medium DECIMAL(12,2) NULL DEFAULT NULL AFTER sent_pressure_small");
    $pdo->exec("ALTER TABLE supplier_transactions ADD COLUMN sent_pressure_large DECIMAL(12,2) NULL DEFAULT NULL AFTER sent_pressure_medium");
}
try {
    $pdo->exec("ALTER TABLE supplier_transactions MODIFY COLUMN cylinder_type ENUM('Small','Medium','Large','Mixed') NOT NULL DEFAULT 'Small'");
} catch (Throwable $e) {
    // ignore if already migrated or DB limitation
}
$pdo->exec("UPDATE supplier_transactions SET
    sent_qty_small = CASE cylinder_type WHEN 'Small' THEN sent_quantity ELSE 0 END,
    sent_qty_medium = CASE cylinder_type WHEN 'Medium' THEN sent_quantity ELSE 0 END,
    sent_qty_large = CASE cylinder_type WHEN 'Large' THEN sent_quantity ELSE 0 END
    WHERE (COALESCE(sent_qty_small,0) + COALESCE(sent_qty_medium,0) + COALESCE(sent_qty_large,0)) = 0
    AND sent_quantity > 0");
if (!column_exists($pdo, 'supplier_refill_breakdown', 'refill_cylinder_type')) {
    $pdo->exec("ALTER TABLE supplier_refill_breakdown ADD COLUMN refill_cylinder_type ENUM('Small','Medium','Large') NULL AFTER transaction_id");
}
if (!column_exists($pdo, 'supplier_refill_breakdown', 'pressure_received')) {
    $pdo->exec("ALTER TABLE supplier_refill_breakdown ADD COLUMN pressure_received DECIMAL(12,2) NULL DEFAULT NULL AFTER quantity");
}
if (!column_exists($pdo, 'supplier_refill_breakdown', 'line_unit_price')) {
    $pdo->exec("ALTER TABLE supplier_refill_breakdown ADD COLUMN line_unit_price DECIMAL(12,2) NULL DEFAULT NULL AFTER pressure_received");
}
if (!column_exists($pdo, 'cylinder_stock_by_type', 'available_pressure')) {
    $pdo->exec("ALTER TABLE cylinder_stock_by_type ADD COLUMN available_pressure DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER available");
}
if (!column_exists($pdo, 'supplier_payments', 'transaction_id')) {
    $pdo->exec("ALTER TABLE supplier_payments ADD COLUMN transaction_id INT NULL AFTER supplier_id");
}
if (!column_exists($pdo, 'supplier_payments', 'payment_type')) {
    $pdo->exec("ALTER TABLE supplier_payments ADD COLUMN payment_type ENUM('Cash','Bank','Credit') NOT NULL DEFAULT 'Cash' AFTER amount");
}
if (!column_exists($pdo, 'supplier_ledger', 'reference_type')) {
    $pdo->exec("ALTER TABLE supplier_ledger ADD COLUMN reference_type VARCHAR(40) NULL AFTER balance");
}
if (!column_exists($pdo, 'supplier_ledger', 'reference_id')) {
    $pdo->exec("ALTER TABLE supplier_ledger ADD COLUMN reference_id INT NULL AFTER reference_type");
}
if (!column_exists($pdo, 'supplier_ledger', 'description')) {
    $pdo->exec("ALTER TABLE supplier_ledger ADD COLUMN description VARCHAR(255) NULL AFTER reference_id");
}
$ledgerDescMeta = $pdo->query("SHOW COLUMNS FROM supplier_ledger LIKE 'description'")->fetch();
if ($ledgerDescMeta && stripos((string) ($ledgerDescMeta['Type'] ?? ''), 'varchar(255)') !== false) {
    try {
        $pdo->exec('ALTER TABLE supplier_ledger MODIFY COLUMN description VARCHAR(2000) NULL');
    } catch (Throwable $e) {
        // ignore
    }
}
$stockByTypeTotalMeta = $pdo->query("SHOW COLUMNS FROM cylinder_stock_by_type LIKE 'total'")->fetch();
if ($stockByTypeTotalMeta && stripos((string) ($stockByTypeTotalMeta['Type'] ?? ''), 'decimal') === false) {
    $pdo->exec("ALTER TABLE cylinder_stock_by_type MODIFY total DECIMAL(12,2) NOT NULL DEFAULT 0.00, MODIFY available DECIMAL(12,2) NOT NULL DEFAULT 0.00");
}
$cylindersTotalMeta = $pdo->query("SHOW COLUMNS FROM cylinders LIKE 'total'")->fetch();
if ($cylindersTotalMeta && stripos((string) ($cylindersTotalMeta['Type'] ?? ''), 'decimal') === false) {
    $pdo->exec("ALTER TABLE cylinders MODIFY total DECIMAL(12,2) NOT NULL DEFAULT 0.00, MODIFY available DECIMAL(12,2) NOT NULL DEFAULT 0.00");
}

$seedTypes = ['Small', 'Medium', 'Large'];
$seedStmt = $pdo->prepare("INSERT IGNORE INTO cylinder_stock_by_type (cylinder_type, total, available) VALUES (?, 0, 0)");
foreach ($seedTypes as $type) {
    $seedStmt->execute([$type]);
}

$jsonResponse = static function (bool $ok, string $message, array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
};

$supplierBalance = static function (PDO $pdo, int $supplierId): float {
    $stmt = $pdo->prepare('SELECT balance FROM supplier_ledger WHERE supplier_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$supplierId]);
    return (float) ($stmt->fetch()['balance'] ?? 0);
};

$paymentStatus = static function (float $total, float $paid): string {
    if ($total > 0 && $paid >= $total) {
        return 'PAID';
    }
    if ($paid > 0) {
        return 'PARTIAL';
    }
    return 'DUE';
};

/** @param array<string,int> $sentByType @param array<string,int> $recvByType */
$adjustSupplierPending = static function (PDO $pdo, int $supplierId, array $sentByType, array $recvByType, int $direction): void {
    $types = ['Small', 'Medium', 'Large'];
    foreach ($types as $t) {
        $sentPortion = (int) ($sentByType[$t] ?? 0);
        $recv = (int) ($recvByType[$t] ?? 0);
        $net = $sentPortion - $recv;
        if ($net === 0) {
            continue;
        }
        $delta = $direction * $net;
        $ins = $pdo->prepare('INSERT INTO supplier_pending_cylinders (supplier_id, cylinder_type, pending_count) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE pending_count = GREATEST(0, pending_count + ?)');
        $ins->execute([$supplierId, $t, $delta, $delta]);
    }
};

/** @return array{Small:int,Medium:int,Large:int} */
$sentByTypeFromTransactionRow = static function (array $row): array {
    $s = (int) ($row['sent_qty_small'] ?? 0);
    $m = (int) ($row['sent_qty_medium'] ?? 0);
    $l = (int) ($row['sent_qty_large'] ?? 0);
    if ($s + $m + $l > 0) {
        return ['Small' => $s, 'Medium' => $m, 'Large' => $l];
    }
    $legacy = (int) ($row['sent_quantity'] ?? 0);
    $t = (string) ($row['cylinder_type'] ?? 'Small');
    if ($t === 'Mixed') {
        return ['Small' => 0, 'Medium' => 0, 'Large' => 0];
    }
    return [
        'Small' => $t === 'Small' ? $legacy : 0,
        'Medium' => $t === 'Medium' ? $legacy : 0,
        'Large' => $t === 'Large' ? $legacy : 0,
    ];
};

$formatPurchaseLedgerDescription = static function (PDO $pdo, int $purchaseId) use ($sentByTypeFromTransactionRow): string {
    $stmt = $pdo->prepare('SELECT * FROM supplier_transactions WHERE id = ? LIMIT 1');
    $stmt->execute([$purchaseId]);
    $t = $stmt->fetch();
    if (!$t) {
        return "Purchase #SP-{$purchaseId}";
    }
    $sentBy = $sentByTypeFromTransactionRow($t);
    $sentParts = [];
    foreach (['Small', 'Medium', 'Large'] as $sz) {
        $q = (int) ($sentBy[$sz] ?? 0);
        if ($q <= 0) {
            continue;
        }
        $pk = 'sent_pressure_' . strtolower($sz);
        $pr = $t[$pk] ?? null;
        $psi = ($pr !== null && $pr !== '' && (float) $pr > 0) ? (' @ ' . rtrim(rtrim((string) $pr, '0'), '.') . ' PSI') : '';
        $sentParts[] = "{$sz} (Qty: {$q}{$psi})";
    }
    $recvStmt = $pdo->prepare('SELECT refill_cylinder_type, quantity, pressure_received, line_unit_price FROM supplier_refill_breakdown WHERE transaction_id = ? ORDER BY id ASC');
    $recvStmt->execute([$purchaseId]);
    $recvParts = [];
    foreach ($recvStmt->fetchAll() as $r) {
        $sz = (string) ($r['refill_cylinder_type'] ?? '');
        if ($sz === '') {
            $sz = (string) ($t['cylinder_type'] ?? 'Small');
        }
        $q = (int) ($r['quantity'] ?? 0);
        if ($q <= 0) {
            continue;
        }
        $pr = $r['pressure_received'] ?? null;
        $psi = ($pr !== null && $pr !== '' && (float) $pr > 0) ? (' @ ' . rtrim(rtrim((string) $pr, '0'), '.') . ' PSI') : '';
        $pu = (float) ($r['line_unit_price'] ?? 0);
        $priceBit = $pu > 0 ? (' @ ' . format_currency($pu) . '/u') : '';
        $recvParts[] = "{$sz} (Qty: {$q}{$psi}{$priceBit})";
    }
    $head = "Purchase #SP-{$purchaseId}";
    $chunks = [];
    if ($sentParts) {
        $chunks[] = 'Sent: ' . implode(' | ', $sentParts);
    }
    if ($recvParts) {
        $chunks[] = 'Received: ' . implode(' | ', $recvParts);
    }
    $avg = (float) ($t['unit_price'] ?? 0);
    if ($avg > 0) {
        $chunks[] = 'Avg unit: ' . format_currency($avg);
    }
    $out = $chunks ? ($head . ' — ' . implode(' — ', $chunks)) : $head;
    if (strlen($out) > 1990) {
        $out = substr($out, 0, 1987) . '…';
    }
    return $out;
};

/** @return array<string, string> display_* keys for history modal */
$enrichSupplierHistoryPurchaseRow = static function (array $t) use ($sentByTypeFromTransactionRow): array {
    $sentBy = $sentByTypeFromTransactionRow($t);
    $sentChunks = [];
    $sentPsiOnly = [];
    foreach (['Small', 'Medium', 'Large'] as $sz) {
        $q = (int) ($sentBy[$sz] ?? 0);
        if ($q <= 0) {
            continue;
        }
        $pk = 'sent_pressure_' . strtolower($sz);
        $pr = $t[$pk] ?? null;
        $psiNum = ($pr !== null && $pr !== '') ? (float) $pr : 0.0;
        $psiLabel = $psiNum > 0 ? (rtrim(rtrim((string) $psiNum, '0'), '.') . ' PSI') : '—';
        $szLabel = cylinder_size_label($sz);
        $sentChunks[] = $szLabel . ' (' . __('suppliers.break_qty') . ": {$q}, " . __('suppliers.break_pressure') . ": {$psiLabel})";
        if ($psiNum > 0) {
            $sentPsiOnly[] = cylinder_size_label($sz) . ': ' . $psiLabel;
        }
    }
    $recvChunks = [];
    $recvPsiOnly = [];
    $raw = (string) ($t['breakdown_data'] ?? '');
    $ctype = (string) ($t['cylinder_type'] ?? 'Small');
    if ($raw !== '') {
        foreach (explode('||', $raw) as $entry) {
            if ($entry === '') {
                continue;
            }
            $p = explode('::', $entry);
            if (count($p) >= 6) {
                $sz = trim((string) ($p[0] ?? '')) !== '' ? trim((string) $p[0]) : $ctype;
                $qty = (int) ($p[2] ?? 0);
                $psi = (float) ($p[3] ?? 0);
                $unit = (float) ($p[4] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $psiLabel = $psi > 0 ? (rtrim(rtrim((string) $psi, '0'), '.') . ' PSI') : '—';
                $szDisp = cylinder_size_label($sz);
                $uBit = $unit > 0 ? (', ' . __('suppliers.break_unit') . ': ' . format_currency($unit)) : '';
                $recvChunks[] = $szDisp . ' (' . __('suppliers.break_qty') . ": {$qty}, " . __('suppliers.break_pressure') . ": {$psiLabel}{$uBit})";
                if ($psi > 0) {
                    $recvPsiOnly[] = $szDisp . ': ' . rtrim(rtrim((string) $psi, '0'), '.') . ' PSI';
                }
            } elseif (count($p) >= 3) {
                $qty = (int) ($p[1] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $recvChunks[] = cylinder_size_label($ctype) . ' (' . __('suppliers.break_qty') . ": {$qty}, " . __('suppliers.break_pressure') . ': —)';
            }
        }
    }
    $legacySp = $t['sent_pressure'] ?? null;
    $legacySentPsi = ($legacySp !== null && $legacySp !== '' && (float) $legacySp > 0)
        ? (rtrim(rtrim((string) (float) $legacySp, '0'), '.') . ' PSI') : '';
    return array_merge($t, [
        'display_size_breakdown' => implode(' | ', array_filter([
            $sentChunks ? (__('suppliers.label_dispatch') . ': ' . implode(' | ', $sentChunks)) : '',
            $recvChunks ? (__('suppliers.label_receipt') . ': ' . implode(' | ', $recvChunks)) : '',
        ])),
        'display_sent_pressure' => $sentPsiOnly ? implode('; ', $sentPsiOnly) : ($legacySentPsi !== '' ? $legacySentPsi : '—'),
        'display_recv_pressure' => $recvPsiOnly ? implode('; ', $recvPsiOnly) : '—',
        'display_unit_price' => __('currency.symbol') . ' ' . number_format((float) ($t['unit_price'] ?? 0), 2) . ' ' . __('suppliers.price_avg_suffix'),
    ]);
};

/** @return array<string,float> size => summed inventory qty */
$loadBreakdownInventoryByType = static function (PDO $pdo, int $transactionId): array {
    $stmt = $pdo->prepare("SELECT COALESCE(b.refill_cylinder_type, t.cylinder_type) AS ctype,
        SUM(b.inventory_qty) AS q
        FROM supplier_refill_breakdown b
        INNER JOIN supplier_transactions t ON t.id = b.transaction_id
        WHERE b.transaction_id = ?
        GROUP BY COALESCE(b.refill_cylinder_type, t.cylinder_type)");
    $stmt->execute([$transactionId]);
    $out = ['Small' => 0.0, 'Medium' => 0.0, 'Large' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        $ct = (string) ($row['ctype'] ?? '');
        if (!isset($out[$ct])) {
            continue;
        }
        $out[$ct] += (float) ($row['q'] ?? 0);
    }
    return $out;
};

/** @return array<string,float> size => summed received pressure (qty * pressure_received) */
$loadBreakdownPressureByType = static function (PDO $pdo, int $transactionId): array {
    $stmt = $pdo->prepare("SELECT COALESCE(b.refill_cylinder_type, t.cylinder_type) AS ctype,
        SUM(COALESCE(b.inventory_qty, b.quantity, 0) * COALESCE(b.pressure_received, 0)) AS p
        FROM supplier_refill_breakdown b
        INNER JOIN supplier_transactions t ON t.id = b.transaction_id
        WHERE b.transaction_id = ?
        GROUP BY COALESCE(b.refill_cylinder_type, t.cylinder_type)");
    $stmt->execute([$transactionId]);
    $out = ['Small' => 0.0, 'Medium' => 0.0, 'Large' => 0.0];
    foreach ($stmt->fetchAll() as $row) {
        $ct = (string) ($row['ctype'] ?? '');
        if (!isset($out[$ct])) {
            continue;
        }
        $out[$ct] += (float) ($row['p'] ?? 0);
    }
    return $out;
};

$rebuildSupplierLedger = static function (PDO $pdo, int $supplierId) use ($formatPurchaseLedgerDescription): void {
    $supplierStmt = $pdo->prepare("SELECT id, opening_balance, created_at FROM suppliers WHERE id = ? LIMIT 1");
    $supplierStmt->execute([$supplierId]);
    $supplier = $supplierStmt->fetch();
    if (!$supplier) {
        return;
    }
    $openingBalance = max(0, (float) ($supplier['opening_balance'] ?? 0));
    $events = [];
    if ($openingBalance > 0) {
        $events[] = [
            'date' => substr((string) ($supplier['created_at'] ?? date('Y-m-d')), 0, 10),
            'type' => 'opening_balance',
            'debit' => $openingBalance,
            'credit' => 0.0,
            'reference_type' => 'opening_balance',
            'reference_id' => (int) $supplierId,
            'description' => 'Opening balance',
            'sort' => 0,
            'id' => (int) $supplierId,
        ];
    }
    $purchaseStmt = $pdo->prepare("SELECT id, transaction_date, total_amount FROM supplier_transactions WHERE supplier_id = ? ORDER BY transaction_date ASC, id ASC");
    $purchaseStmt->execute([$supplierId]);
    foreach ($purchaseStmt->fetchAll() as $row) {
        $purchaseId = (int) ($row['id'] ?? 0);
        $events[] = [
            'date' => (string) ($row['transaction_date'] ?? date('Y-m-d')),
            'type' => 'purchase',
            'debit' => (float) ($row['total_amount'] ?? 0),
            'credit' => 0.0,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'description' => $formatPurchaseLedgerDescription($pdo, $purchaseId),
            'sort' => 1,
            'id' => $purchaseId,
        ];
    }
    $paymentStmt = $pdo->prepare("SELECT id, transaction_id, payment_date, amount, payment_type FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date ASC, id ASC");
    $paymentStmt->execute([$supplierId]);
    foreach ($paymentStmt->fetchAll() as $row) {
        $paymentId = (int) ($row['id'] ?? 0);
        $transactionId = (int) ($row['transaction_id'] ?? 0);
        $payType = (string) ($row['payment_type'] ?? 'Cash');
        $amtStr = format_currency((float) ($row['amount'] ?? 0));
        $events[] = [
            'date' => (string) ($row['payment_date'] ?? date('Y-m-d')),
            'type' => 'payment',
            'debit' => 0.0,
            'credit' => (float) ($row['amount'] ?? 0),
            'reference_type' => 'payment',
            'reference_id' => $paymentId,
            'description' => "Installment #SP-{$transactionId} ({$payType}) — {$amtStr} toward refill purchase",
            'sort' => 2,
            'id' => $paymentId,
        ];
    }
    usort($events, static function (array $a, array $b): int {
        $cmpDate = strcmp((string) $a['date'], (string) $b['date']);
        if ($cmpDate !== 0) return $cmpDate;
        if ((int) $a['sort'] !== (int) $b['sort']) return ((int) $a['sort'] <=> (int) $b['sort']);
        return ((int) $a['id'] <=> (int) $b['id']);
    });
    $pdo->prepare("DELETE FROM supplier_ledger WHERE supplier_id = ?")->execute([$supplierId]);
    $insertStmt = $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, debit, credit, balance, reference_type, reference_id, description, entry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $balance = 0.0;
    foreach ($events as $event) {
        $debit = (float) ($event['debit'] ?? 0);
        $credit = (float) ($event['credit'] ?? 0);
        $balance += $debit - $credit;
        $insertStmt->execute([
            $supplierId,
            $debit,
            $credit,
            $balance,
            $event['reference_type'],
            (int) $event['reference_id'],
            $event['description'],
            $event['date'],
        ]);
    }
};

$loadSuppliersTable = static function (PDO $pdo, string $search = ''): array {
    $sql = "SELECT s.id, s.name, s.contact_person, s.phone, s.email, s.address, s.opening_balance,
        COALESCE((SELECT SUM(t.total_amount) FROM supplier_transactions t WHERE t.supplier_id = s.id),0) AS purchases,
        COALESCE((SELECT SUM(p.amount) FROM supplier_payments p WHERE p.supplier_id = s.id),0) AS paid
        FROM suppliers s";
    $params = [];
    if ($search !== '') {
        $sql .= " WHERE s.name LIKE ? OR s.phone LIKE ? OR s.contact_person LIKE ?";
        $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
    }
    $sql .= " ORDER BY s.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$loadPurchaseRows = static function (PDO $pdo, string $fromDate = '', string $toDate = '', int $supplierId = 0): array {
    $sql = "SELECT t.id, t.supplier_id, s.name AS supplier_name, t.cylinder_type, t.quantity, t.sent_quantity, t.date_sent,
        t.sent_pressure, t.sent_qty_small, t.sent_qty_medium, t.sent_qty_large,
        t.sent_pressure_small, t.sent_pressure_medium, t.sent_pressure_large,
        t.total_received, t.inventory_quantity, t.received_fully_quantity, t.unit_price, t.total_amount,
        t.paid_amount, t.remaining_amount, t.payment_type, t.payment_status, t.transaction_date,
        (SELECT GROUP_CONCAT(CONCAT(
            COALESCE(b.refill_cylinder_type, ''), '::', COALESCE(b.status_label, ''), '::', b.quantity, '::',
            COALESCE(b.pressure_received, 0), '::', COALESCE(b.line_unit_price, 0), '::', b.inventory_qty
            ) ORDER BY b.id ASC SEPARATOR '||')
            FROM supplier_refill_breakdown b WHERE b.transaction_id = t.id) AS breakdown_data
        FROM supplier_transactions t
        INNER JOIN suppliers s ON s.id = t.supplier_id
        WHERE 1=1";
    $params = [];
    if ($fromDate !== '') {
        $sql .= " AND t.transaction_date >= ?";
        $params[] = $fromDate;
    }
    if ($toDate !== '') {
        $sql .= " AND t.transaction_date <= ?";
        $params[] = $toDate;
    }
    if ($supplierId > 0) {
        $sql .= " AND t.supplier_id = ?";
        $params[] = $supplierId;
    }
    $sql .= " ORDER BY t.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$loadPaymentHistory = static function (PDO $pdo, string $period = 'weekly', int $supplierId = 0): array {
    $days = $period === 'monthly' ? 30 : 7;
    $startDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $sql = "SELECT t.id AS transaction_id, t.total_amount, t.transaction_date, t.supplier_id, s.name AS supplier_name,
        COALESCE(SUM(p.amount), 0) AS paid_total,
        MIN(p.payment_date) AS first_payment_date,
        MAX(p.payment_date) AS last_payment_date,
        COUNT(p.id) AS installment_count,
        GROUP_CONCAT(CONCAT(DATE_FORMAT(p.payment_date, '%Y-%m-%d'), ':', FORMAT(p.amount, 2)) ORDER BY p.payment_date ASC SEPARATOR '|') AS installment_breakdown
        FROM supplier_transactions t
        INNER JOIN suppliers s ON s.id = t.supplier_id
        LEFT JOIN supplier_payments p ON p.transaction_id = t.id
        WHERE (p.payment_date >= ? OR (p.payment_date IS NULL AND t.transaction_date >= ?))";
    $params = [$startDate, $startDate];
    if ($supplierId > 0) {
        $sql .= " AND t.supplier_id = ?";
        $params[] = $supplierId;
    }
    $sql .= " GROUP BY t.id, t.total_amount, t.transaction_date, t.supplier_id, s.name
        ORDER BY COALESCE(MAX(p.payment_date), t.transaction_date) DESC, t.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['outstanding_balance'] = max(0, (float) $row['total_amount'] - (float) $row['paid_total']);
    }
    unset($row);
    return $rows;
};

$loadPendingBySupplier = static function (PDO $pdo): array {
    $map = [];
    $rows = $pdo->query("SELECT supplier_id, cylinder_type, pending_count FROM supplier_pending_cylinders")->fetchAll();
    foreach ($rows as $r) {
        $sid = (int) ($r['supplier_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        if (!isset($map[$sid])) {
            $map[$sid] = ['Small' => 0, 'Medium' => 0, 'Large' => 0, 'total' => 0];
        }
        $t = (string) ($r['cylinder_type'] ?? 'Small');
        if (!isset($map[$sid][$t])) {
            continue;
        }
        $map[$sid][$t] = (int) ($r['pending_count'] ?? 0);
    }
    foreach ($map as $sid => $entry) {
        $map[$sid]['total'] = (int) $entry['Small'] + (int) $entry['Medium'] + (int) $entry['Large'];
    }
    return $map;
};

$loadTotalSentBySupplier = static function (PDO $pdo): array {
    $out = [];
    $rows = $pdo->query("SELECT supplier_id, COALESCE(SUM(sent_quantity),0) AS total_sent FROM supplier_transactions GROUP BY supplier_id")->fetchAll();
    foreach ($rows as $r) {
        $out[(int) ($r['supplier_id'] ?? 0)] = (int) ($r['total_sent'] ?? 0);
    }
    return $out;
};

$buildPayload = static function (PDO $pdo, string $search = '', string $fromDate = '', string $toDate = '', int $supplierFilter = 0, string $paymentPeriod = 'weekly', int $paymentSupplierFilter = 0) use ($loadSuppliersTable, $loadPurchaseRows, $loadPaymentHistory, $loadPendingBySupplier, $loadTotalSentBySupplier): array {
    $supplierCount = (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
    $totalPurchases = (float) $pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM supplier_transactions')->fetchColumn();
    $totalPaid = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM supplier_payments')->fetchColumn();
    $pendingPayments = max(0, $totalPurchases - $totalPaid);
    $rows = $loadSuppliersTable($pdo, $search);
    $supplierOptions = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();
    $purchaseRows = $loadPurchaseRows($pdo, $fromDate, $toDate, $supplierFilter);
    $paymentHistory = $loadPaymentHistory($pdo, $paymentPeriod, $paymentSupplierFilter);
    $periodPaid = 0.0;
    $periodOutstanding = 0.0;
    foreach ($paymentHistory as $entry) {
        $periodPaid += (float) ($entry['paid_total'] ?? 0);
        $periodOutstanding += (float) ($entry['outstanding_balance'] ?? 0);
    }
    $hasTotalPressure = column_exists($pdo, 'cylinder_stock_by_type', 'total_pressure');
    $typedPressureExpr = $hasTotalPressure ? 'GREATEST(COALESCE(total_pressure, 0), COALESCE(available_pressure, 0))' : 'COALESCE(available_pressure, 0)';
    $typedStock = $pdo->query("SELECT cylinder_type, total, available, {$typedPressureExpr} AS total_pressure, COALESCE(available_pressure, 0) AS available_pressure FROM cylinder_stock_by_type ORDER BY FIELD(cylinder_type,'Small','Medium','Large')")->fetchAll();
    $stockTotals = $pdo->query('SELECT COALESCE(SUM(total),0) AS total, COALESCE(SUM(available),0) AS available FROM cylinders')->fetch() ?: ['total' => 0, 'available' => 0];
    $stockPressureTotals = $pdo->query("SELECT COALESCE(SUM({$typedPressureExpr}),0) AS total_pressure, COALESCE(SUM(available_pressure),0) AS available_pressure FROM cylinder_stock_by_type")->fetch() ?: ['total_pressure' => 0, 'available_pressure' => 0];
    return [
        'summary' => [
            'suppliers' => $supplierCount,
            'purchases' => $totalPurchases,
            'pending' => $pendingPayments,
            'paid' => $totalPaid,
        ],
        'suppliers' => $rows,
        'supplierOptions' => $supplierOptions,
        'pendingBySupplier' => $loadPendingBySupplier($pdo),
        'totalSentBySupplier' => $loadTotalSentBySupplier($pdo),
        'purchases' => $purchaseRows,
        'paymentHistory' => $paymentHistory,
        'paymentSummary' => [
            'period' => $paymentPeriod,
            'paid' => $periodPaid,
            'outstanding' => $periodOutstanding,
        ],
        'stockByType' => $typedStock,
        'stockTotals' => [
            'total' => (float) $stockTotals['total'],
            'available' => (float) $stockTotals['available'],
            'total_pressure' => (float) ($stockPressureTotals['total_pressure'] ?? 0),
            'available_pressure' => (float) ($stockPressureTotals['available_pressure'] ?? 0),
        ],
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'export_purchases_csv')) {
    $fromDate = trim((string) ($_GET['from_date'] ?? ''));
    $toDate = trim((string) ($_GET['to_date'] ?? ''));
    $supplierId = (int) ($_GET['supplier_filter'] ?? 0);
    $rows = $loadPurchaseRows($pdo, $fromDate, $toDate, $supplierId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="supplier-purchases-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, [
        __('suppliers.csv_id'),
        __('suppliers.csv_supplier'),
        __('suppliers.csv_cylinder_type'),
        __('suppliers.csv_quantity'),
        __('suppliers.csv_unit_price'),
        __('suppliers.csv_total'),
        __('suppliers.csv_paid'),
        __('suppliers.csv_remaining'),
        __('suppliers.csv_payment_type'),
        __('suppliers.csv_status'),
        __('suppliers.csv_date'),
    ]);
    $payKey = static function (string $pt): string {
        $k = 'suppliers.pay_' . strtolower($pt);
        $t = __($k);
        return $t !== $k ? $t : $pt;
    };
    foreach ($rows as $r) {
        fputcsv($out, [
            (int) $r['id'],
            $r['supplier_name'],
            cylinder_size_label((string) $r['cylinder_type']),
            (int) $r['quantity'],
            number_format((float) $r['unit_price'], 2, '.', ''),
            number_format((float) $r['total_amount'], 2, '.', ''),
            number_format((float) $r['paid_amount'], 2, '.', ''),
            number_format((float) $r['remaining_amount'], 2, '.', ''),
            $payKey((string) ($r['payment_type'] ?? '')),
            supplier_tx_payment_status_label((string) $r['payment_status']),
            $r['transaction_date'],
        ]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'print_purchase_invoice')) {
    $purchaseId = (int) ($_GET['purchase_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT t.*, s.name AS supplier_name, s.contact_person, s.phone, s.address
        FROM supplier_transactions t
        INNER JOIN suppliers s ON s.id = t.supplier_id
        WHERE t.id = ?");
    $stmt->execute([$purchaseId]);
    $row = $stmt->fetch();
    if (!$row) {
        exit(__('suppliers.print_invoice_not_found'));
    }
    $rtl = i18n_is_rtl();
    ?>
    <!doctype html>
    <html lang="<?= e(i18n_locale()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>"><head><meta charset="UTF-8"><title><?= e(__('suppliers.print_title')) ?></title>
    <?php if ($rtl): ?>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>body{font-family:<?= $rtl ? "'Noto Naskh Arabic'," : '' ?>Arial,sans-serif;padding:20px;color:#0f172a}h1{margin:0 0 8px}table{width:100%;border-collapse:collapse;margin-top:14px}td,th{border:1px solid #cbd5e1;padding:8px;text-align:start}.muted{color:#64748b}</style>
    </head><body onload="window.print()">
    <h1><?= e(__('suppliers.print_title')) ?></h1>
    <p class="muted"><?= e(__('suppliers.print_reference')) ?> #SP-<?= (int) $row['id'] ?> | <?= e(__('suppliers.print_date')) ?>: <?= e(format_date_pk((string) $row['transaction_date'])) ?>
        <?php if (isset($row['sent_pressure']) && $row['sent_pressure'] !== null && $row['sent_pressure'] !== ''): ?> | <?= e(__('suppliers.print_sent_pressure')) ?>: <?= e((string) $row['sent_pressure']) ?> PSI<?php endif; ?></p>
    <h3><?= e((string) $row['supplier_name']) ?></h3>
    <p><?= e(__('suppliers.print_contact')) ?>: <?= e((string) ($row['contact_person'] ?? '-')) ?> | <?= e(__('suppliers.print_phone')) ?>: <?= e((string) ($row['phone'] ?? '-')) ?></p>
    <table>
        <tr><th><?= e(__('suppliers.print_col_type')) ?></th><th><?= e(__('suppliers.print_col_qty')) ?></th><th><?= e(__('suppliers.print_col_unit_price')) ?></th><th><?= e(__('suppliers.print_col_total')) ?></th><th><?= e(__('suppliers.print_col_paid')) ?></th><th><?= e(__('suppliers.print_col_remaining')) ?></th><th><?= e(__('suppliers.print_col_status')) ?></th></tr>
        <tr>
            <td><?= e(cylinder_size_label((string) $row['cylinder_type'])) ?></td>
            <td><?= (int) $row['quantity'] ?></td>
            <td><?= e(format_currency((float) $row['unit_price'])) ?></td>
            <td><?= e(format_currency((float) $row['total_amount'])) ?></td>
            <td><?= e(format_currency((float) $row['paid_amount'])) ?></td>
            <td><?= e(format_currency((float) $row['remaining_amount'])) ?></td>
            <td><?= e(supplier_tx_payment_status_label((string) $row['payment_status'])) ?></td>
        </tr>
    </table>
    </body></html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = request_value('action');
    try {
        if ($action === 'fetch_dashboard') {
            $search = request_value('search');
            $fromDate = request_value('from_date');
            $toDate = request_value('to_date');
            $supplierFilter = (int) request_value('supplier_filter', '0');
            $paymentPeriod = request_value('payment_period', 'weekly');
            $paymentSupplierFilter = (int) request_value('payment_supplier_filter', '0');
            $jsonResponse(true, __('suppliers.ajax_dashboard_loaded'), ['data' => $buildPayload($pdo, $search, $fromDate, $toDate, $supplierFilter, $paymentPeriod, $paymentSupplierFilter)]);
        }

        if ($action === 'fetch_payment_history') {
            $paymentPeriod = request_value('payment_period', 'weekly');
            $paymentSupplierFilter = (int) request_value('payment_supplier_filter', '0');
            $allowedPeriods = ['weekly', 'monthly'];
            if (!in_array($paymentPeriod, $allowedPeriods, true)) {
                $paymentPeriod = 'weekly';
            }
            $rows = $loadPaymentHistory($pdo, $paymentPeriod, $paymentSupplierFilter);
            $paid = 0.0;
            $outstanding = 0.0;
            foreach ($rows as $entry) {
                $paid += (float) ($entry['paid_total'] ?? 0);
                $outstanding += (float) ($entry['outstanding_balance'] ?? 0);
            }
            $jsonResponse(true, __('suppliers.ajax_payment_history_loaded'), [
                'paymentHistory' => $rows,
                'paymentSummary' => [
                    'period' => $paymentPeriod,
                    'paid' => $paid,
                    'outstanding' => $outstanding,
                ],
            ]);
        }

        if ($action === 'add_supplier') {
            $name = request_value('name');
            $contactPerson = request_value('contact_person');
            $phone = request_value('phone');
            $email = request_value('email');
            $address = request_value('address');
            $openingBalance = max(0, (float) request_value('opening_balance', '0'));
            if ($name === '' || $contactPerson === '' || $phone === '') {
                $jsonResponse(false, __('suppliers.err_company_contact_phone'));
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address, opening_balance) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $contactPerson, $phone, $email !== '' ? $email : null, $address, $openingBalance]);
            $supplierId = (int) $pdo->lastInsertId();
            if ($openingBalance > 0) {
                $ledgerStmt = $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, debit, credit, balance, reference_type, reference_id, description, entry_date) VALUES (?, ?, 0, ?, 'opening_balance', ?, ?, ?)");
                $ledgerStmt->execute([$supplierId, $openingBalance, $openingBalance, $supplierId, __('suppliers.ledger_opening_desc'), date('Y-m-d')]);
            }
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_supplier_added'), ['data' => $buildPayload($pdo)]);
        }

        if ($action === 'record_purchase' || $action === 'update_purchase') {
            $purchaseId = (int) request_value('purchase_id', '0');
            $supplierId = (int) request_value('supplier_id', '0');
            $cylinderType = request_value('cylinder_type', 'Small');
            $sentByType = [
                'Small' => max(0, (int) request_value('sent_qty_Small', '0')),
                'Medium' => max(0, (int) request_value('sent_qty_Medium', '0')),
                'Large' => max(0, (int) request_value('sent_qty_Large', '0')),
            ];
            $sentPressureByType = [];
            foreach (['Small', 'Medium', 'Large'] as $sz) {
                $raw = request_value('sent_pressure_' . $sz, '');
                $sentPressureByType[$sz] = $raw === '' ? null : max(0, (float) $raw);
            }
            $sentQuantity = $sentByType['Small'] + $sentByType['Medium'] + $sentByType['Large'];
            $nonZeroSizes = array_values(array_filter(['Small', 'Medium', 'Large'], static function (string $t) use ($sentByType): bool {
                return ($sentByType[$t] ?? 0) > 0;
            }));
            if (count($nonZeroSizes) === 1) {
                $cylinderType = $nonZeroSizes[0];
            } elseif (count($nonZeroSizes) > 1) {
                $cylinderType = 'Mixed';
            } else {
                $cylinderType = in_array($cylinderType, ['Small', 'Medium', 'Large', 'Mixed'], true) ? $cylinderType : 'Small';
            }
            $sentPressureVal = null;
            foreach (['Small', 'Medium', 'Large'] as $sz) {
                if (($sentPressureByType[$sz] ?? null) !== null) {
                    $sentPressureVal = $sentPressureByType[$sz];
                    break;
                }
            }
            $dateSent = request_value('date_sent', date('Y-m-d'));
            $defaultUnitPrice = max(0, (float) request_value('unit_price', '0'));
            $transactionDate = request_value('transaction_date', date('Y-m-d'));
            $paidAmount = max(0, (float) request_value('paid_amount', '0'));
            $paymentType = request_value('payment_type', 'Credit');
            $validTypes = ['Small', 'Medium', 'Large', 'Mixed'];
            $validPaymentTypes = ['Cash', 'Bank', 'Credit'];
            $recvByType = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
            $breakdownRows = [];
            $inventoryQuantity = 0.0;
            $receivedPressureTotal = 0.0;
            $totalAmount = 0.0;
            foreach ($validTypes as $sz) {
                $qty = max(0, (int) request_value('recv_qty_' . $sz, '0'));
                $pressureRaw = request_value('recv_pressure_' . $sz, '');
                $pressureVal = $pressureRaw === '' ? null : max(0, (float) $pressureRaw);
                $lineUnit = max(0, (float) request_value('recv_unit_price_' . $sz, '0'));
                if ($lineUnit <= 0) {
                    $lineUnit = $defaultUnitPrice;
                }
                if ($qty <= 0) {
                    continue;
                }
                $recvByType[$sz] = $qty;
                $invQty = (float) $qty;
                $inventoryQuantity += $invQty;
                $receivedPressureTotal += $invQty * (float) ($pressureVal ?? 0.0);
                $lineTotal = $invQty * $lineUnit;
                $totalAmount += $lineTotal;
                $breakdownRows[] = [
                    'size' => $sz,
                    'qty' => $qty,
                    'pressure' => $pressureVal,
                    'line_unit' => $lineUnit,
                    'inventory_qty' => $invQty,
                ];
            }
            if (!$breakdownRows) {
                $jsonResponse(false, __('suppliers.err_recv_qty_required'));
            }
            if (!in_array($cylinderType, $validTypes, true) || !in_array($paymentType, $validPaymentTypes, true)) {
                $jsonResponse(false, __('suppliers.err_invalid_types'));
            }
            if ($supplierId <= 0) {
                $jsonResponse(false, __('suppliers.err_select_supplier'));
            }
            if ($totalAmount <= 0 && $defaultUnitPrice <= 0) {
                $jsonResponse(false, __('suppliers.err_unit_price_required'));
            }
            if ($totalAmount <= 0) {
                $totalAmount = $inventoryQuantity * $defaultUnitPrice;
            }
            $computedReceived = (int) round($inventoryQuantity);
            $totalReceived = $computedReceived;
            $quantity = $totalReceived;
            $receivedFully = $totalReceived;
            $avgUnitPrice = $inventoryQuantity > 0 ? ($totalAmount / $inventoryQuantity) : $defaultUnitPrice;
            $unitPrice = $avgUnitPrice;
            $paidAmount = min($paidAmount, $totalAmount);
            $remainingAmount = max(0, $totalAmount - $paidAmount);
            $status = $paymentStatus($totalAmount, $paidAmount);

            $pdo->beginTransaction();

            $loadReceivedByType = static function (PDO $pdo, int $txId): array {
                $stmt = $pdo->prepare("SELECT COALESCE(b.refill_cylinder_type, t.cylinder_type) AS ctype, SUM(b.quantity) AS q
                    FROM supplier_refill_breakdown b
                    INNER JOIN supplier_transactions t ON t.id = b.transaction_id
                    WHERE b.transaction_id = ?
                    GROUP BY COALESCE(b.refill_cylinder_type, t.cylinder_type)");
                $stmt->execute([$txId]);
                $out = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
                foreach ($stmt->fetchAll() as $row) {
                    $ct = (string) ($row['ctype'] ?? '');
                    if (isset($out[$ct])) {
                        $out[$ct] = (int) ($row['q'] ?? 0);
                    }
                }
                return $out;
            };

            if ($action === 'update_purchase') {
                $oldStmt = $pdo->prepare("SELECT id, supplier_id, quantity, cylinder_type, sent_quantity, sent_qty_small, sent_qty_medium, sent_qty_large, inventory_quantity FROM supplier_transactions WHERE id = ? FOR UPDATE");
                $oldStmt->execute([$purchaseId]);
                $old = $oldStmt->fetch();
                if (!$old) {
                    $pdo->rollBack();
                    $jsonResponse(false, __('suppliers.err_purchase_not_found'));
                }
                $oldSupplierId = (int) ($old['supplier_id'] ?? 0);
                $oldSentByType = $sentByTypeFromTransactionRow($old);
                $oldRecvByType = $loadReceivedByType($pdo, $purchaseId);
                $adjustSupplierPending($pdo, $oldSupplierId, $oldSentByType, $oldRecvByType, -1);

                $invByOld = $loadBreakdownInventoryByType($pdo, $purchaseId);
                $pressureByOld = $loadBreakdownPressureByType($pdo, $purchaseId);
                foreach ($invByOld as $ctype => $deltaQty) {
                    $deltaPressure = (float) ($pressureByOld[$ctype] ?? 0.0);
                    if ($deltaQty == 0.0 && $deltaPressure == 0.0) {
                        continue;
                    }
                    $neg = -1 * $deltaQty;
                    $negPressure = -1 * $deltaPressure;
                    if (column_exists($pdo, 'cylinder_stock_by_type', 'total_pressure')) {
                        $pdo->prepare("UPDATE cylinder_stock_by_type SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?), available_pressure = GREATEST(0, available_pressure + ?), total_pressure = GREATEST(0, total_pressure + ?) WHERE cylinder_type = ?")
                            ->execute([$neg, $neg, $negPressure, $negPressure, $ctype]);
                    } else {
                        $pdo->prepare("UPDATE cylinder_stock_by_type SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?), available_pressure = GREATEST(0, available_pressure + ?) WHERE cylinder_type = ?")
                            ->execute([$neg, $neg, $negPressure, $ctype]);
                    }
                }
                $sumOldInv = array_sum($invByOld);
                $pdo->prepare("UPDATE cylinders SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?) ORDER BY id ASC LIMIT 1")
                    ->execute([-1 * $sumOldInv, -1 * $sumOldInv]);

                $pdo->prepare("DELETE FROM supplier_payments WHERE transaction_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_ledger WHERE reference_type = 'purchase' AND reference_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_ledger WHERE reference_type = 'payment' AND reference_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_refill_breakdown WHERE transaction_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM refill_discrepancy WHERE transaction_id = ?")->execute([$purchaseId]);
                $txUpdate = $pdo->prepare("UPDATE supplier_transactions SET supplier_id=?, cylinder_type=?, sent_quantity=?, sent_pressure=?, sent_qty_small=?, sent_qty_medium=?, sent_qty_large=?, sent_pressure_small=?, sent_pressure_medium=?, sent_pressure_large=?, date_sent=?, quantity=?, total_received=?, inventory_quantity=?, received_fully_quantity=?, received_pressure_total=?, unit_price=?, total_amount=?, paid_amount=?, remaining_amount=?, payment_type=?, payment_status=?, transaction_date=? WHERE id=?");
                $txUpdate->execute([
                    $supplierId, $cylinderType, $sentQuantity, $sentPressureVal,
                    $sentByType['Small'], $sentByType['Medium'], $sentByType['Large'],
                    $sentPressureByType['Small'], $sentPressureByType['Medium'], $sentPressureByType['Large'],
                    $dateSent, $quantity, $totalReceived, $inventoryQuantity, $receivedFully, $receivedPressureTotal, $unitPrice, $totalAmount, $paidAmount, $remainingAmount, $paymentType, $status, $transactionDate, $purchaseId,
                ]);
            } else {
                $txInsert = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, cylinder_type, sent_quantity, sent_pressure, sent_qty_small, sent_qty_medium, sent_qty_large, sent_pressure_small, sent_pressure_medium, sent_pressure_large, date_sent, quantity, total_received, inventory_quantity, received_fully_quantity, received_pressure_total, unit_price, total_amount, paid_amount, remaining_amount, payment_type, payment_status, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $txInsert->execute([
                    $supplierId, $cylinderType, $sentQuantity, $sentPressureVal,
                    $sentByType['Small'], $sentByType['Medium'], $sentByType['Large'],
                    $sentPressureByType['Small'], $sentPressureByType['Medium'], $sentPressureByType['Large'],
                    $dateSent, $quantity, $totalReceived, $inventoryQuantity, $receivedFully, $receivedPressureTotal, $unitPrice, $totalAmount, $paidAmount, $remainingAmount, $paymentType, $status, $transactionDate,
                ]);
                $purchaseId = (int) $pdo->lastInsertId();
            }

            $adjustSupplierPending($pdo, $supplierId, $sentByType, $recvByType, 1);

            $breakdownInsert = $pdo->prepare("INSERT INTO supplier_refill_breakdown (transaction_id, refill_cylinder_type, status_label, quantity, pressure_received, line_unit_price, inventory_qty) VALUES (?, ?, 'Refill Receipt', ?, ?, ?, ?)");
            foreach ($breakdownRows as $rowItem) {
                $breakdownInsert->execute([
                    $purchaseId,
                    $rowItem['size'],
                    $rowItem['qty'],
                    $rowItem['pressure'],
                    $rowItem['line_unit'],
                    $rowItem['inventory_qty'],
                ]);
            }
            $difference = $sentQuantity - $totalReceived;
            if ($difference !== 0) {
                $discStmt = $pdo->prepare("INSERT INTO refill_discrepancy (supplier_id, transaction_id, sent_quantity, received_fully, difference_quantity, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $discStmt->execute([$supplierId, $purchaseId, $sentQuantity, $receivedFully, $difference, 'Dispatch vs receipt cylinder count']);
            }

            $prevBalance = $supplierBalance($pdo, $supplierId);
            $afterDebitBalance = $prevBalance + $totalAmount;
            $ledgerPurchaseDesc = $formatPurchaseLedgerDescription($pdo, $purchaseId);
            $ledgerDebit = $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, debit, credit, balance, reference_type, reference_id, description, entry_date) VALUES (?, ?, 0, ?, 'purchase', ?, ?, ?)");
            $ledgerDebit->execute([$supplierId, $totalAmount, $afterDebitBalance, $purchaseId, $ledgerPurchaseDesc, $transactionDate]);
            if ($paidAmount > 0) {
                $payStmt = $pdo->prepare("INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, ?, ?, ?, ?)");
                $payStmt->execute([$supplierId, $purchaseId, $paidAmount, $paymentType, $transactionDate]);
                $afterCreditBalance = $afterDebitBalance - $paidAmount;
                $payDesc = 'Installment #SP-' . (int) $purchaseId . ' (' . $paymentType . ') — ' . format_currency($paidAmount) . ' toward refill purchase';
                $ledgerCredit = $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, debit, credit, balance, reference_type, reference_id, description, entry_date) VALUES (?, 0, ?, ?, 'payment', ?, ?, ?)");
                $ledgerCredit->execute([$supplierId, $paidAmount, $afterCreditBalance, $purchaseId, $payDesc, $transactionDate]);
            }

            foreach ($breakdownRows as $rowItem) {
                $add = $rowItem['inventory_qty'];
                $addPressure = (float) $add * (float) ($rowItem['pressure'] ?? 0.0);
                if (column_exists($pdo, 'cylinder_stock_by_type', 'total_pressure')) {
                    $pdo->prepare("UPDATE cylinder_stock_by_type SET total = total + ?, available = available + ?, available_pressure = available_pressure + ?, total_pressure = total_pressure + ? WHERE cylinder_type = ?")
                        ->execute([$add, $add, $addPressure, $addPressure, $rowItem['size']]);
                } else {
                    $pdo->prepare("UPDATE cylinder_stock_by_type SET total = total + ?, available = available + ?, available_pressure = available_pressure + ? WHERE cylinder_type = ?")
                        ->execute([$add, $add, $addPressure, $rowItem['size']]);
                }
            }
            $addTotal = $inventoryQuantity;
            $stockRow = $pdo->query('SELECT id, total, available FROM cylinders ORDER BY id ASC LIMIT 1 FOR UPDATE')->fetch();
            if (!$stockRow) {
                $pdo->prepare("INSERT INTO cylinders (total, available, issued, empty) VALUES (?, ?, 0, 0)")->execute([$addTotal, $addTotal]);
            } else {
                $pdo->prepare("UPDATE cylinders SET total = total + ?, available = available + ? WHERE id = ?")->execute([$addTotal, $addTotal, (int) $stockRow['id']]);
            }

            $pdo->commit();
            $jsonResponse(true, $action === 'update_purchase' ? __('suppliers.msg_purchase_updated') : __('suppliers.msg_purchase_saved'), ['data' => $buildPayload($pdo)]);
        }

        if ($action === 'delete_purchase') {
            $purchaseId = (int) request_value('purchase_id', '0');
            if ($purchaseId <= 0) {
                $jsonResponse(false, __('suppliers.err_purchase_not_found'));
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT supplier_id, cylinder_type, sent_quantity, sent_qty_small, sent_qty_medium, sent_qty_large, quantity, inventory_quantity FROM supplier_transactions WHERE id = ? FOR UPDATE");
            $stmt->execute([$purchaseId]);
            $row = $stmt->fetch();
            if (!$row) {
                $pdo->rollBack();
                $jsonResponse(false, __('suppliers.err_purchase_not_found'));
            }
            $supplierIdDel = (int) ($row['supplier_id'] ?? 0);
            $oldSentByTypeDel = $sentByTypeFromTransactionRow($row);
            $loadReceivedByTypeDel = static function (PDO $pdo, int $txId): array {
                $st = $pdo->prepare("SELECT COALESCE(b.refill_cylinder_type, t.cylinder_type) AS ctype, SUM(b.quantity) AS q
                    FROM supplier_refill_breakdown b
                    INNER JOIN supplier_transactions t ON t.id = b.transaction_id
                    WHERE b.transaction_id = ?
                    GROUP BY COALESCE(b.refill_cylinder_type, t.cylinder_type)");
                $st->execute([$txId]);
                $out = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
                foreach ($st->fetchAll() as $r) {
                    $ct = (string) ($r['ctype'] ?? '');
                    if (isset($out[$ct])) {
                        $out[$ct] = (int) ($r['q'] ?? 0);
                    }
                }
                return $out;
            };
            $oldRecvByType = $loadReceivedByTypeDel($pdo, $purchaseId);
            $adjustSupplierPending($pdo, $supplierIdDel, $oldSentByTypeDel, $oldRecvByType, -1);
            $invByType = $loadBreakdownInventoryByType($pdo, $purchaseId);
            $pressureByType = $loadBreakdownPressureByType($pdo, $purchaseId);
            foreach ($invByType as $ctype => $deltaQty) {
                $deltaPressure = (float) ($pressureByType[$ctype] ?? 0.0);
                if ($deltaQty == 0.0 && $deltaPressure == 0.0) {
                    continue;
                }
                $neg = -1 * $deltaQty;
                $negPressure = -1 * $deltaPressure;
                if (column_exists($pdo, 'cylinder_stock_by_type', 'total_pressure')) {
                    $pdo->prepare("UPDATE cylinder_stock_by_type SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?), available_pressure = GREATEST(0, available_pressure + ?), total_pressure = GREATEST(0, total_pressure + ?) WHERE cylinder_type = ?")
                        ->execute([$neg, $neg, $negPressure, $negPressure, $ctype]);
                } else {
                    $pdo->prepare("UPDATE cylinder_stock_by_type SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?), available_pressure = GREATEST(0, available_pressure + ?) WHERE cylinder_type = ?")
                        ->execute([$neg, $neg, $negPressure, $ctype]);
                }
            }
            $sumInv = array_sum($invByType);
            $pdo->prepare("UPDATE cylinders SET total = GREATEST(0, total + ?), available = GREATEST(0, available + ?) ORDER BY id ASC LIMIT 1")
                ->execute([-1 * $sumInv, -1 * $sumInv]);
            $pdo->prepare("DELETE FROM supplier_payments WHERE transaction_id = ?")->execute([$purchaseId]);
            $pdo->prepare("DELETE FROM supplier_ledger WHERE reference_id = ? AND reference_type IN ('purchase','payment')")->execute([$purchaseId]);
            $pdo->prepare("DELETE FROM supplier_refill_breakdown WHERE transaction_id = ?")->execute([$purchaseId]);
            $pdo->prepare("DELETE FROM refill_discrepancy WHERE transaction_id = ?")->execute([$purchaseId]);
            $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ?")->execute([$purchaseId]);
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_purchase_deleted'), ['data' => $buildPayload($pdo)]);
        }

        if ($action === 'delete_supplier') {
            $supplierId = (int) request_value('supplier_id', '0');
            if ($supplierId <= 0) {
                $jsonResponse(false, __('suppliers.err_supplier_not_found'));
            }
            $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$supplierId]);
            $jsonResponse(true, __('suppliers.msg_supplier_deleted'), ['data' => $buildPayload($pdo)]);
        }

        if ($action === 'supplier_history') {
            $supplierId = (int) request_value('supplier_id', '0');
            $historyStmt = $pdo->prepare("SELECT t.id, t.cylinder_type, t.sent_quantity, t.sent_pressure, t.sent_qty_small, t.sent_qty_medium, t.sent_qty_large,
                t.sent_pressure_small, t.sent_pressure_medium, t.sent_pressure_large,
                t.quantity, t.total_received, t.inventory_quantity, t.unit_price, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status, t.transaction_date,
                (SELECT GROUP_CONCAT(CONCAT(
                    COALESCE(b.refill_cylinder_type, ''), '::', COALESCE(b.status_label, ''), '::', b.quantity, '::',
                    COALESCE(b.pressure_received, 0), '::', COALESCE(b.line_unit_price, 0), '::', b.inventory_qty
                    ) ORDER BY b.id ASC SEPARATOR '||') FROM supplier_refill_breakdown b WHERE b.transaction_id = t.id) AS breakdown_data
                FROM supplier_transactions t WHERE t.supplier_id = ? ORDER BY t.id DESC");
            $historyStmt->execute([$supplierId]);
            $historyRows = [];
            foreach ($historyStmt->fetchAll() as $hr) {
                $historyRows[] = $enrichSupplierHistoryPurchaseRow($hr);
            }
            $paymentStmt = $pdo->prepare("SELECT id, transaction_id, amount, payment_type, payment_date FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC");
            $paymentStmt->execute([$supplierId]);
            $payments = $paymentStmt->fetchAll();
            $ledgerStmt = $pdo->prepare("SELECT entry_date, debit, credit, balance, reference_type, reference_id, description FROM supplier_ledger WHERE supplier_id = ? ORDER BY id DESC");
            $ledgerStmt->execute([$supplierId]);
            $pendingBakee = ['Small' => 0, 'Medium' => 0, 'Large' => 0, 'total' => 0];
            $pendingStmt = $pdo->prepare("SELECT cylinder_type, pending_count FROM supplier_pending_cylinders WHERE supplier_id = ?");
            $pendingStmt->execute([$supplierId]);
            foreach ($pendingStmt->fetchAll() as $pr) {
                $ct = (string) ($pr['cylinder_type'] ?? '');
                if (isset($pendingBakee[$ct])) {
                    $pendingBakee[$ct] = (int) ($pr['pending_count'] ?? 0);
                }
            }
            $pendingBakee['total'] = $pendingBakee['Small'] + $pendingBakee['Medium'] + $pendingBakee['Large'];
            $jsonResponse(true, __('suppliers.ajax_history_loaded'), [
                'history' => $historyRows,
                'ledger' => $ledgerStmt->fetchAll(),
                'payments' => $payments,
                'pendingBakee' => $pendingBakee,
            ]);
        }

        if ($action === 'save_supplier_payment') {
            $paymentId = (int) request_value('payment_id', '0');
            $supplierId = (int) request_value('supplier_id', '0');
            $transactionId = (int) request_value('transaction_id', '0');
            $paymentDate = request_value('payment_date', date('Y-m-d'));
            $paymentType = request_value('payment_type', 'Cash');
            $amount = max(0, (float) request_value('amount', '0'));
            $validPaymentTypes = ['Cash', 'Bank', 'Credit'];
            if ($supplierId <= 0 || $transactionId <= 0 || $amount <= 0) {
                $jsonResponse(false, __('suppliers.err_supplier_purchase_amount'));
            }
            if (!in_array($paymentType, $validPaymentTypes, true)) {
                $jsonResponse(false, __('suppliers.err_invalid_payment_type'));
            }
            $txStmt = $pdo->prepare("SELECT id, total_amount FROM supplier_transactions WHERE id = ? AND supplier_id = ? LIMIT 1");
            $txStmt->execute([$transactionId, $supplierId]);
            $tx = $txStmt->fetch();
            if (!$tx) {
                $jsonResponse(false, __('suppliers.err_purchase_record_not_found'));
            }
            $pdo->beginTransaction();
            if ($paymentId > 0) {
                $updateStmt = $pdo->prepare("UPDATE supplier_payments SET transaction_id = ?, amount = ?, payment_type = ?, payment_date = ? WHERE id = ? AND supplier_id = ?");
                $updateStmt->execute([$transactionId, $amount, $paymentType, $paymentDate, $paymentId, $supplierId]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$supplierId, $transactionId, $amount, $paymentType, $paymentDate]);
            }
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS paid_total FROM supplier_payments WHERE transaction_id = ?");
            $sumStmt->execute([$transactionId]);
            $paidTotal = (float) (($sumStmt->fetch()['paid_total'] ?? 0));
            $total = (float) ($tx['total_amount'] ?? 0);
            $remaining = max(0, $total - $paidTotal);
            $status = $paymentStatus($total, $paidTotal);
            $updateTxStmt = $pdo->prepare("UPDATE supplier_transactions SET paid_amount = ?, remaining_amount = ?, payment_status = ? WHERE id = ?");
            $updateTxStmt->execute([$paidTotal, $remaining, $status, $transactionId]);
            $rebuildSupplierLedger($pdo, $supplierId);
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_payment_saved'));
        }

        if ($action === 'delete_supplier_payment') {
            $paymentId = (int) request_value('payment_id', '0');
            if ($paymentId <= 0) {
                $jsonResponse(false, __('suppliers.err_payment_not_found'));
            }
            $stmt = $pdo->prepare("SELECT supplier_id, transaction_id FROM supplier_payments WHERE id = ? LIMIT 1");
            $stmt->execute([$paymentId]);
            $row = $stmt->fetch();
            if (!$row) {
                $jsonResponse(false, __('suppliers.err_payment_not_found'));
            }
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            $transactionId = (int) ($row['transaction_id'] ?? 0);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM supplier_payments WHERE id = ?")->execute([$paymentId]);
            $txStmt = $pdo->prepare("SELECT total_amount FROM supplier_transactions WHERE id = ? LIMIT 1");
            $txStmt->execute([$transactionId]);
            $tx = $txStmt->fetch();
            if ($tx) {
                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS paid_total FROM supplier_payments WHERE transaction_id = ?");
                $sumStmt->execute([$transactionId]);
                $paidTotal = (float) (($sumStmt->fetch()['paid_total'] ?? 0));
                $total = (float) ($tx['total_amount'] ?? 0);
                $remaining = max(0, $total - $paidTotal);
                $status = $paymentStatus($total, $paidTotal);
                $pdo->prepare("UPDATE supplier_transactions SET paid_amount = ?, remaining_amount = ?, payment_status = ? WHERE id = ?")
                    ->execute([$paidTotal, $remaining, $status, $transactionId]);
            }
            $rebuildSupplierLedger($pdo, $supplierId);
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_payment_deleted'));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $jsonResponse(false, __('suppliers.err_operation_failed'));
    }
}

$initialPayload = $buildPayload($pdo);
ob_start();
?>
<style>
    .supplier-card { transition: all .25s ease; }
    .supplier-card:hover { transform: translateY(-2px); }
    .fade-in { animation: supplierFade .24s ease; }
    .slide-up { animation: supplierSlide .28s ease; }
    @keyframes supplierFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes supplierSlide { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0);} }
    @media (max-width: 640px) {
        #summaryCards p.text-3xl { font-size: 1.5rem; line-height: 2rem; }
        #purchaseForm .btn,
        #ledgerPaymentForm .btn { width: 100%; }
    }
</style>

<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6" id="summaryCards">
    <div class="app-card supplier-card p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e(__('suppliers.kpi_total_suppliers')) ?></p>
        <p class="text-3xl font-bold text-oxygenDeep" data-key="suppliers"><?= (int) $initialPayload['summary']['suppliers'] ?></p>
    </div>
    <div class="app-card supplier-card p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e(__('suppliers.kpi_total_purchases')) ?></p>
        <p class="text-3xl font-bold text-slate-800" data-key="purchases"><?= e(format_currency((float) $initialPayload['summary']['purchases'])) ?></p>
    </div>
    <div class="app-card supplier-card p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e(__('suppliers.kpi_pending_payments')) ?></p>
        <p class="text-3xl font-bold text-amber-600" data-key="pending"><?= e(format_currency((float) $initialPayload['summary']['pending'])) ?></p>
    </div>
    <div class="app-card supplier-card p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e(__('suppliers.kpi_paid_amount')) ?></p>
        <p class="text-3xl font-bold text-emerald-600" data-key="paid"><?= e(format_currency((float) $initialPayload['summary']['paid'])) ?></p>
    </div>
</section>

<section class="app-card p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <button type="button" class="btn btn-primary w-full" data-open-modal="addSupplierModal">[+] Add New Supplier</button>
        <a href="?module=suppliers_list<?= i18n_lang_query() ?>" class="btn btn-soft text-center w-full">[📂] View Supplier List</a>
        <a href="?module=supplier_purchases<?= i18n_lang_query() ?>" class="btn btn-soft text-center w-full">[📄] Purchase Records</a>
        <a href="?module=supplier_payments<?= i18n_lang_query() ?>" class="btn btn-soft text-center w-full">[💳] Payment History</a>
    </div>
</section>

<section class="app-card p-4 mb-6 fade-in">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.inventory_title')) ?></h3>
            <p class="text-xs text-slate-500"><?= e(__('suppliers.inventory_hint')) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-800"><?= e(__('suppliers.stock_total')) ?>: <strong id="stockTotal"><?= e(number_format((float) $initialPayload['stockTotals']['total'], 2)) ?></strong> (<?= e(__('suppliers.pressure_psi')) ?> <strong id="stockTotalPressure"><?= e(number_format((float) ($initialPayload['stockTotals']['total_pressure'] ?? 0), 2)) ?></strong>)</span>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800"><?= e(__('suppliers.stock_available')) ?>: <strong id="stockAvailable"><?= e(number_format((float) $initialPayload['stockTotals']['available'], 2)) ?></strong> (<?= e(__('suppliers.pressure_psi')) ?> <strong id="stockAvailablePressure"><?= e(number_format((float) ($initialPayload['stockTotals']['available_pressure'] ?? 0), 2)) ?></strong>)</span>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4" id="typedStockCards"></div>
</section>

<section class="grid grid-cols-1 gap-5 mb-6">
    <div class="app-card p-4 slide-up shadow-sm rounded-xl">
        <h3 class="font-semibold text-oxygenDeep mb-3"><?= e(__('suppliers.refill_dispatch_title')) ?></h3>
        <form id="purchaseForm" class="space-y-4">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="record_purchase">
            <input type="hidden" name="purchase_id" value="">
            <input type="hidden" name="total_received" value="0" id="purchaseTotalReceived">
            <input type="hidden" name="cylinder_type" id="purchaseCylinderTypeHidden" value="Small">
            <p class="text-xs text-slate-500 hidden" id="totalSentAllTimeLine"></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_supplier')) ?></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="supplier_id" required class="app-input flex-1 min-w-[160px]" id="purchaseSupplier"></select>
                        <span id="supplierPendingBadge" class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-900 cursor-help" title=""><?= e(__('suppliers.badge_history')) ?></span>
                    </div>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_date_sent')) ?></span>
                    <input name="date_sent" type="date" class="app-input" value="<?= date('Y-m-d') ?>">
                </label>
            </div>
            <p class="text-xs text-slate-500 mb-1"><?= e(__('suppliers.dispatch_trip_hint')) ?></p>
            <div class="overflow-x-auto rounded-xl border border-indigo-100 bg-indigo-50/50 p-2">
                <table class="w-full text-sm min-w-[640px]">
                    <thead class="bg-white/90"><tr>
                        <th class="p-2 text-start"><?= e(__('suppliers.col_size')) ?></th>
                        <th class="p-2 text-start"><?= e(__('suppliers.col_sent_qty')) ?></th>
                        <th class="p-2 text-start"><?= e(__('suppliers.col_sent_pressure')) ?></th>
                        <th class="p-2 text-end"><?= e(__('suppliers.col_trip_subtotal')) ?></th>
                    </tr></thead>
                    <tbody>
                        <tr class="border-t border-indigo-100/80">
                            <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Small')) ?></td>
                            <td class="p-2"><input name="sent_qty_Small" type="number" min="0" value="0" class="app-input dispatch-sent-qty" data-size="Small"></td>
                            <td class="p-2"><input name="sent_pressure_Small" type="number" step="0.01" min="0" class="app-input dispatch-sent-pressure" data-size="Small" placeholder="<?= e(__('common.optional')) ?>"></td>
                            <td class="p-2 text-end text-slate-600"><span id="dispatchSubSmall">0</span></td>
                        </tr>
                        <tr class="border-t border-indigo-100/80">
                            <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Medium')) ?></td>
                            <td class="p-2"><input name="sent_qty_Medium" type="number" min="0" value="0" class="app-input dispatch-sent-qty" data-size="Medium"></td>
                            <td class="p-2"><input name="sent_pressure_Medium" type="number" step="0.01" min="0" class="app-input dispatch-sent-pressure" data-size="Medium" placeholder="<?= e(__('common.optional')) ?>"></td>
                            <td class="p-2 text-end text-slate-600"><span id="dispatchSubMedium">0</span></td>
                        </tr>
                        <tr class="border-t border-indigo-100/80">
                            <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Large')) ?></td>
                            <td class="p-2"><input name="sent_qty_Large" type="number" min="0" value="0" class="app-input dispatch-sent-qty" data-size="Large"></td>
                            <td class="p-2"><input name="sent_pressure_Large" type="number" step="0.01" min="0" class="app-input dispatch-sent-pressure" data-size="Large" placeholder="<?= e(__('common.optional')) ?>"></td>
                            <td class="p-2 text-end text-slate-600"><span id="dispatchSubLarge">0</span></td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-white/90 font-medium text-slate-800"><tr>
                        <td class="p-2" colspan="3"><?= e(__('suppliers.total_sent_trip')) ?></td>
                        <td class="p-2 text-end"><span id="dispatchSentGrand">0</span></td>
                    </tr></tfoot>
                </table>
            </div>
            <div class="rounded-lg border border-sky-200 bg-sky-50/80 px-3 py-2 text-xs text-sky-950">
                <span class="font-semibold text-sky-900"><?= e(__('suppliers.pending_bakee_title')) ?></span>
                <span id="previousPendingLine" class="ms-1"><?= e(__('suppliers.pending_select_supplier')) ?></span>
            </div>

            <div class="rounded-xl border border-slate-200 p-3 bg-slate-50">
                <h4 class="font-semibold text-oxygenDeep mb-2"><?= e(__('suppliers.reconciliation_title')) ?></h4>
                <p class="text-xs text-slate-500 mb-3"><?= e(__('suppliers.reconciliation_hint')) ?></p>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-3">
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_receipt_date')) ?></span>
                        <input name="transaction_date" type="date" class="app-input" value="<?= date('Y-m-d') ?>">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_default_unit_price')) ?></span>
                        <input name="unit_price" type="number" step="0.01" min="0" class="app-input" placeholder="<?= e(__('suppliers.ph_fallback_unit')) ?>">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_inventory_qty_total')) ?></span>
                        <input name="inventory_qty_preview" readonly class="app-input bg-slate-100" placeholder="0.00">
                    </label>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[920px]">
                        <thead class="bg-white"><tr>
                            <th class="p-2 text-start"><?= e(__('suppliers.col_size')) ?></th>
                            <th class="p-2 text-start"><?= e(__('suppliers.col_received_qty')) ?></th>
                            <th class="p-2 text-start"><?= e(__('suppliers.col_pressure_received')) ?></th>
                            <th class="p-2 text-start"><?= e(__('suppliers.col_unit_price')) ?></th>
                            <th class="p-2 text-start"><?= e(__('suppliers.col_pending_supplier')) ?></th>
                            <th class="p-2 text-end"><?= e(__('suppliers.col_line_total')) ?></th>
                        </tr></thead>
                        <tbody id="refillBreakdownBody">
                            <tr class="border-t border-slate-100" data-recv-row="Small">
                                <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Small')) ?></td>
                                <td class="p-2"><input name="recv_qty_Small" type="number" min="0" value="0" class="app-input recv-qty" data-size="Small"></td>
                                <td class="p-2"><input name="recv_pressure_Small" type="number" step="0.01" min="0" class="app-input recv-pressure" data-size="Small" placeholder="<?= e(__('suppliers.ph_psi')) ?>"></td>
                                <td class="p-2"><input name="recv_unit_price_Small" type="number" step="0.01" min="0" class="app-input recv-unit" data-size="Small" placeholder="<?= e(__('suppliers.ph_use_default')) ?>"></td>
                                <td class="p-2"><input type="text" readonly class="app-input bg-slate-100 recv-pending" data-size="Small" value="0"></td>
                                <td class="p-2 text-end"><span class="recv-line-total font-medium text-slate-800" data-size="Small"><?= e(__('currency.symbol')) ?> 0.00</span></td>
                            </tr>
                            <tr class="border-t border-slate-100" data-recv-row="Medium">
                                <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Medium')) ?></td>
                                <td class="p-2"><input name="recv_qty_Medium" type="number" min="0" value="0" class="app-input recv-qty" data-size="Medium"></td>
                                <td class="p-2"><input name="recv_pressure_Medium" type="number" step="0.01" min="0" class="app-input recv-pressure" data-size="Medium" placeholder="<?= e(__('suppliers.ph_psi')) ?>"></td>
                                <td class="p-2"><input name="recv_unit_price_Medium" type="number" step="0.01" min="0" class="app-input recv-unit" data-size="Medium" placeholder="<?= e(__('suppliers.ph_use_default')) ?>"></td>
                                <td class="p-2"><input type="text" readonly class="app-input bg-slate-100 recv-pending" data-size="Medium" value="0"></td>
                                <td class="p-2 text-end"><span class="recv-line-total font-medium text-slate-800" data-size="Medium"><?= e(__('currency.symbol')) ?> 0.00</span></td>
                            </tr>
                            <tr class="border-t border-slate-100" data-recv-row="Large">
                                <td class="p-2 font-medium text-slate-700"><?= e(cylinder_size_label('Large')) ?></td>
                                <td class="p-2"><input name="recv_qty_Large" type="number" min="0" value="0" class="app-input recv-qty" data-size="Large"></td>
                                <td class="p-2"><input name="recv_pressure_Large" type="number" step="0.01" min="0" class="app-input recv-pressure" data-size="Large" placeholder="<?= e(__('suppliers.ph_psi')) ?>"></td>
                                <td class="p-2"><input name="recv_unit_price_Large" type="number" step="0.01" min="0" class="app-input recv-unit" data-size="Large" placeholder="<?= e(__('suppliers.ph_use_default')) ?>"></td>
                                <td class="p-2"><input type="text" readonly class="app-input bg-slate-100 recv-pending" data-size="Large" value="0"></td>
                                <td class="p-2 text-end"><span class="recv-line-total font-medium text-slate-800" data-size="Large"><?= e(__('currency.symbol')) ?> 0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_total_cost_auto')) ?></span>
                    <input name="total_amount_preview" readonly class="app-input bg-slate-50" placeholder="<?= e(__('suppliers.ph_total_amount')) ?>">
                </label>
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_paid_amount')) ?></span>
                    <input name="paid_amount" type="number" step="0.01" min="0" class="app-input" placeholder="<?= e(__('suppliers.label_paid_amount')) ?>">
                </label>
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_remaining_amount')) ?></span>
                    <input name="remaining_amount_preview" readonly class="app-input bg-slate-50" placeholder="<?= e(__('suppliers.label_remaining_amount')) ?>">
                </label>
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_payment_type')) ?></span>
                    <select name="payment_type" required class="app-input">
                        <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                        <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                        <option value="Credit"><?= e(__('suppliers.pay_credit')) ?></option>
                    </select>
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="btn btn-primary" id="purchaseSubmitBtn"><?= e(__('suppliers.btn_save_receipt')) ?></button>
                <button type="button" class="btn btn-soft hidden" id="purchaseResetBtn"><?= e(__('suppliers.btn_cancel_edit')) ?></button>
            </div>
        </form>
    </div>
</section>

<div id="addSupplierModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-modal="addSupplierModal"></div>
    <div class="relative w-full max-w-lg app-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.add_supplier_title')) ?></h3>
            <button type="button" class="btn btn-soft" data-close-modal="addSupplierModal"><?= e(__('common.close')) ?></button>
        </div>
        <form id="supplierForm" class="space-y-3">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="add_supplier">
            <input name="name" required class="app-input" placeholder="<?= e(__('suppliers.ph_company')) ?>">
            <input name="contact_person" required class="app-input" placeholder="<?= e(__('suppliers.ph_contact')) ?>">
            <input name="phone" required class="app-input" placeholder="<?= e(__('suppliers.ph_phone')) ?>">
            <input name="email" type="email" class="app-input" placeholder="<?= e(__('suppliers.ph_email')) ?>">
            <textarea name="address" class="app-input" placeholder="<?= e(__('suppliers.ph_address')) ?>"></textarea>
            <input name="opening_balance" type="number" step="0.01" min="0" class="app-input" placeholder="<?= e(__('suppliers.ph_opening_balance')) ?>">
            <button class="btn btn-primary w-full"><?= e(__('suppliers.btn_save_supplier')) ?></button>
        </form>
    </div>
</div>

<section class="app-card p-4 mb-6 hidden">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <input id="supplierSearch" class="app-input md:col-span-2" placeholder="<?= e(__('suppliers.search_placeholder')) ?>">
        <input id="filterFromDate" type="date" class="app-input">
        <input id="filterToDate" type="date" class="app-input">
        <select id="supplierFilter" class="app-input"></select>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
        <button type="button" id="applyFilterBtn" class="btn btn-soft"><?= e(__('suppliers.apply_filter')) ?></button>
        <button type="button" id="exportCsvBtn" class="btn btn-soft"><?= e(__('suppliers.export_csv')) ?></button>
    </div>
</section>

<section class="app-card p-4 mb-6 hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.payment_history_title')) ?></h3>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800"><?= e(__('suppliers.badge_paid_period')) ?>: <strong id="periodPaidAmount"><?= e(format_currency((float) ($initialPayload['paymentSummary']['paid'] ?? 0))) ?></strong></span>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800"><?= e(__('suppliers.badge_outstanding')) ?>: <strong id="periodOutstandingAmount"><?= e(format_currency((float) ($initialPayload['paymentSummary']['outstanding'] ?? 0))) ?></strong></span>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
        <label class="block">
            <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_history_range')) ?></span>
            <select id="paymentPeriodFilter" class="app-input">
                <option value="weekly" selected><?= e(__('suppliers.period_weekly')) ?></option>
                <option value="monthly"><?= e(__('suppliers.period_monthly')) ?></option>
            </select>
        </label>
        <label class="block md:col-span-2">
            <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_supplier')) ?></span>
            <select id="paymentSupplierFilter" class="app-input"></select>
        </label>
        <div class="flex items-end">
            <button type="button" id="applyPaymentFilterBtn" class="btn btn-soft w-full"><?= e(__('suppliers.apply_payment_filter')) ?></button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[1100px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.ph_last_payment')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_supplier')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchase_ref')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchase_date')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total_due')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_installments_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_outstanding_balance')) ?></th>
            </tr></thead>
            <tbody id="paymentHistoryBody"></tbody>
        </table>
    </div>
</section>

<section class="app-card overflow-hidden mb-6 hidden">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold"><?= e(__('suppliers.list_title')) ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[980px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.col_company')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_contact')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_phone')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_purchases')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_balance')) ?></th>
                <th class="text-start p-3"><?= e(__('common.actions')) ?></th>
            </tr></thead>
            <tbody id="suppliersTbody"></tbody>
        </table>
    </div>
</section>

<section class="app-card overflow-hidden hidden">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-semibold"><?= e(__('suppliers.purchases_title')) ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[1060px]">
            <thead class="bg-slate-50"><tr>
                <th class="text-start p-3"><?= e(__('suppliers.col_date')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_supplier')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_type')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_sent')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_received')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_inventory_qty')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_total')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_paid')) ?></th>
                <th class="text-start p-3"><?= e(__('suppliers.col_remaining')) ?></th>
                <th class="text-start p-3"><?= e(__('common.status')) ?></th>
                <th class="text-start p-3"><?= e(__('common.actions')) ?></th>
            </tr></thead>
            <tbody id="purchaseTbody"></tbody>
        </table>
    </div>
</section>

<div id="historyModal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-history="1"></div>
    <div class="relative w-full max-w-6xl app-card p-4 sm:p-5 max-h-[90vh] overflow-y-auto overflow-x-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h3 class="text-lg font-semibold text-oxygenDeep"><?= e(__('suppliers.modal_history_title')) ?></h3>
            <button class="btn btn-soft shrink-0" data-close-history="1"><?= e(__('common.close')) ?></button>
        </div>
        <h4 class="text-sm font-semibold mb-2 text-oxygenDeep"><?= e(__('suppliers.modal_purchase_history')) ?></h4>
        <p class="text-xs text-slate-500 mb-2"><?= e(__('suppliers.modal_purchase_hint')) ?></p>
        <div class="overflow-x-auto mb-4 -mx-1 px-1">
            <table class="w-full text-sm min-w-[1100px] border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50"><tr>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_date')) ?></th>
                    <th class="p-2 text-start min-w-[220px]"><?= e(__('suppliers.col_size_breakdown')) ?></th>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_sent_pressure')) ?></th>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_recv_pressure')) ?></th>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_unit_price')) ?></th>
                    <th class="p-2 text-end whitespace-nowrap"><?= e(__('suppliers.col_total')) ?></th>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('common.status')) ?></th>
                </tr></thead>
                <tbody id="historyPurchaseBody"></tbody>
            </table>
        </div>
        <div id="historyPendingBakee" class="mb-4 rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-3 text-sm text-amber-950">
            <span class="font-semibold"><?= e(__('suppliers.pending_at_supplier_bakee')) ?></span>
            <p class="mt-1 text-xs sm:text-sm" id="historyPendingBakeeBody">—</p>
        </div>
        <h4 class="text-sm font-semibold mb-2 text-oxygenDeep"><?= e(__('suppliers.modal_ledger_entries')) ?></h4>
        <div class="overflow-x-auto mb-4 -mx-1 px-1">
            <table class="w-full text-sm min-w-[1000px] border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50"><tr>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_date')) ?></th>
                    <th class="p-2 text-start min-w-[200px]"><?= e(__('suppliers.col_description')) ?></th>
                    <th class="p-2 text-start whitespace-nowrap"><?= e(__('suppliers.col_pending_at_supplier_short')) ?></th>
                    <th class="p-2 text-end whitespace-nowrap"><?= e(__('suppliers.col_debit')) ?></th>
                    <th class="p-2 text-end whitespace-nowrap"><?= e(__('suppliers.col_credit')) ?></th>
                    <th class="p-2 text-end whitespace-nowrap"><?= e(__('suppliers.col_balance')) ?></th>
                </tr></thead>
                <tbody id="historyLedgerBody"></tbody>
            </table>
        </div>
        <h4 class="text-sm font-semibold mt-4 mb-2 text-oxygenDeep"><?= e(__('suppliers.modal_installments')) ?></h4>
        <form id="ledgerPaymentForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-3">
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="payment_id" value="">
            <input type="hidden" name="supplier_id" value="">
            <label class="block sm:col-span-2 lg:col-span-2">
                <span class="block text-xs text-slate-500 mb-1"><?= e(__('suppliers.label_purchase_select')) ?></span>
                <select name="transaction_id" class="app-input w-full" required></select>
            </label>
            <label class="block">
                <span class="block text-xs text-slate-500 mb-1"><?= e(__('suppliers.label_payment_date')) ?></span>
                <input name="payment_date" type="date" class="app-input w-full" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label class="block">
                <span class="block text-xs text-slate-500 mb-1"><?= e(__('suppliers.label_amount')) ?></span>
                <input name="amount" type="number" step="0.01" min="0.01" class="app-input w-full" required>
            </label>
            <label class="block">
                <span class="block text-xs text-slate-500 mb-1"><?= e(__('suppliers.label_payment_type')) ?></span>
                <select name="payment_type" class="app-input w-full" required>
                    <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                    <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                    <option value="Credit"><?= e(__('suppliers.pay_credit')) ?></option>
                </select>
            </label>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit" class="btn btn-primary w-full" id="ledgerPaymentSubmitBtn"><?= e(__('suppliers.btn_save_payment')) ?></button>
                <button type="button" class="btn btn-soft w-full hidden" id="ledgerPaymentResetBtn"><?= e(__('suppliers.btn_cancel_edit')) ?></button>
            </div>
        </form>
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[980px] border border-slate-200 rounded-lg overflow-hidden">
                <thead class="bg-slate-50"><tr>
                    <th class="p-2 text-start"><?= e(__('suppliers.label_payment_date')) ?></th>
                    <th class="p-2 text-start"><?= e(__('suppliers.label_purchase_select')) ?></th>
                    <th class="p-2 text-end"><?= e(__('suppliers.col_installment_amount')) ?></th>
                    <th class="p-2 text-end"><?= e(__('suppliers.col_remaining_after')) ?></th>
                    <th class="p-2 text-start"><?= e(__('common.actions')) ?></th>
                </tr></thead>
                <tbody id="historyPaymentBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php
$supplierJs = [
    'sizeSmall' => cylinder_size_label('Small'),
    'sizeMedium' => cylinder_size_label('Medium'),
    'sizeLarge' => cylinder_size_label('Large'),
    'stockTotal' => __('suppliers.stock_total'),
    'stockAvailable' => __('suppliers.stock_available'),
    'pressurePsi' => __('suppliers.pressure_psi'),
    'selectSupplier' => __('suppliers.select_supplier_placeholder'),
    'allSuppliers' => __('suppliers.all_suppliers'),
    'historyBadge' => __('suppliers.badge_history'),
    'totalDispatchedHint' => __('suppliers.total_dispatched_hint'),
    'badgeTitlePending' => __('suppliers.badge_title_pending'),
    'pendingSelect' => __('suppliers.pending_select_supplier'),
    'bakeePrefix' => __('suppliers.bakee_prefix'),
    'bakeeTitleDetail' => __('suppliers.bakee_title_suffix'),
    'pendingIntro' => __('suppliers.pending_line_intro'),
    'noSuppliers' => __('suppliers.no_suppliers'),
    'noPurchases' => __('suppliers.no_purchases_filter'),
    'noPayments' => __('suppliers.no_payment_records'),
    'noInstallments' => __('suppliers.no_installments_yet'),
    'noPurchaseHistory' => __('suppliers.no_purchase_history'),
    'noLedger' => __('suppliers.no_ledger_entries'),
    'noInstallmentPayments' => __('suppliers.no_installment_payments'),
    'viewLedger' => __('suppliers.btn_view_ledger'),
    'delete' => __('suppliers.btn_delete'),
    'edit' => __('suppliers.btn_edit'),
    'printInvoice' => __('suppliers.btn_print_invoice'),
    'saved' => __('common.saved'),
    'done' => __('common.done'),
    'saveReceipt' => __('suppliers.btn_save_receipt'),
    'updateReceipt' => __('suppliers.btn_update_receipt'),
    'savePayment' => __('suppliers.btn_save_payment'),
    'updatePayment' => __('suppliers.btn_update_payment'),
    'filterFailed' => __('suppliers.filter_failed'),
    'paymentHistoryFailed' => __('suppliers.payment_history_load_failed'),
    'failedHistory' => __('suppliers.failed_load_history'),
    'confirmDelPurchase' => __('suppliers.confirm_delete_purchase'),
    'confirmDelSupplier' => __('suppliers.confirm_delete_supplier'),
    'confirmDelPayment' => __('suppliers.confirm_delete_payment'),
    'supplierContextMissing' => __('suppliers.err_supplier_context'),
    'selectPurchase' => __('suppliers.select_purchase_placeholder'),
    'dueRemainingTpl' => __('suppliers.pending_detail_html'),
    'optFullyPaidTpl' => __('suppliers.opt_fully_paid_installment'),
    'lblDue' => __('suppliers.lbl_due'),
    'lblRem' => __('suppliers.lbl_rem'),
    'ledgerPendingTitle' => __('suppliers.ledger_cell_pending_title'),
    'entryFallback' => __('common.entry'),
    'statusPaid' => __('suppliers.status_paid'),
    'statusPartial' => __('suppliers.status_partial'),
    'statusDue' => __('suppliers.status_due'),
];
?>
<script>
(() => {
    const data = <?= json_encode($initialPayload, JSON_UNESCAPED_UNICODE) ?>;
    const t = <?= json_encode($supplierJs, JSON_UNESCAPED_UNICODE) ?>;
    const currencySym = <?= json_encode(__('currency.symbol'), JSON_UNESCAPED_UNICODE) ?>;
    const fmt = (n) => `${currencySym} ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const fmtRs = fmt;
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#039;' }[m]));
    const formatHistoryBreakdownCell = (raw) => {
        if (raw == null || raw === '' || raw === '—') return '<span class="text-slate-400">—</span>';
        return String(raw)
            .split(' | ')
            .map((part) => `<div class="leading-snug border-b border-slate-100/80 last:border-0 pb-1 last:pb-0 mb-1 last:mb-0">${escapeHtml(part)}</div>`)
            .join('');
    };
    const statusClass = (status) => status === 'PAID' ? 'status-paid' : (status === 'PARTIAL' ? 'status-partial' : 'status-due');
    const toast = document.getElementById('toast');
    const showToast = (message) => {
        if (!toast || !message) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 2200);
    };
    const request = async (body) => {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body
        });
        return response.json();
    };

    let currentData = data;
    const supplierForm = document.getElementById('supplierForm');
    const purchaseForm = document.getElementById('purchaseForm');
    const purchaseSubmitBtn = document.getElementById('purchaseSubmitBtn');
    const purchaseResetBtn = document.getElementById('purchaseResetBtn');
    const suppliersTbody = document.getElementById('suppliersTbody');
    const purchaseTbody = document.getElementById('purchaseTbody');
    const purchaseSupplier = document.getElementById('purchaseSupplier');
    const supplierFilter = document.getElementById('supplierFilter');
    const typedStockCards = document.getElementById('typedStockCards');
    const historyModal = document.getElementById('historyModal');
    const ledgerPaymentForm = document.getElementById('ledgerPaymentForm');
    const ledgerPaymentSubmitBtn = document.getElementById('ledgerPaymentSubmitBtn');
    const ledgerPaymentResetBtn = document.getElementById('ledgerPaymentResetBtn');
    const paymentHistoryBody = document.getElementById('paymentHistoryBody');
    const paymentPeriodFilter = document.getElementById('paymentPeriodFilter');
    const paymentSupplierFilter = document.getElementById('paymentSupplierFilter');
    const refillBreakdownBody = document.getElementById('refillBreakdownBody');
    const previousPendingLine = document.getElementById('previousPendingLine');
    const supplierPendingBadge = document.getElementById('supplierPendingBadge');
    const totalSentAllTimeLine = document.getElementById('totalSentAllTimeLine');
    const SIZES = ['Small', 'Medium', 'Large'];
    let editPurchaseRow = null;
    const sizeLabel = (code) => (code === 'Small' ? t.sizeSmall : code === 'Medium' ? t.sizeMedium : code === 'Large' ? t.sizeLarge : code);
    const mapPayStatus = (s) => (s === 'PAID' || s === 'Paid' ? t.statusPaid : s === 'PARTIAL' || s === 'Partial' ? t.statusPartial : s === 'DUE' || s === 'Due' ? t.statusDue : s);

    const getPendingEntry = (supplierId) => {
        const map = currentData.pendingBySupplier || {};
        return map[String(supplierId)] || map[supplierId] || { Small: 0, Medium: 0, Large: 0, total: 0 };
    };

    const parseSentFromPurchaseRow = (row) => {
        if (row == null) return { Small: 0, Medium: 0, Large: 0 };
        const s = Number(row.sent_qty_small ?? 0);
        const m = Number(row.sent_qty_medium ?? 0);
        const l = Number(row.sent_qty_large ?? 0);
        if (s + m + l > 0) {
            return { Small: s, Medium: m, Large: l };
        }
        const t = row.cylinder_type || 'Small';
        const sq = Number(row.sent_quantity || 0);
        if (t === 'Mixed') {
            return { Small: 0, Medium: 0, Large: 0 };
        }
        return {
            Small: t === 'Small' ? sq : 0,
            Medium: t === 'Medium' ? sq : 0,
            Large: t === 'Large' ? sq : 0,
        };
    };

    const parseRecvFromPurchaseRow = (row) => {
        const out = { Small: 0, Medium: 0, Large: 0 };
        const raw = row.breakdown_data || '';
        if (!raw) return out;
        String(raw).split('||').forEach((entry) => {
            const p = entry.split('::');
            if (p.length >= 6) {
                const size = (p[0] || '').trim() || (row.cylinder_type || 'Small');
                if (SIZES.includes(size)) {
                    out[size] += Number(p[2] || 0);
                }
            } else if (p.length >= 3) {
                const qty = Number(p[1] || 0);
                const ct = row.cylinder_type || 'Small';
                out[ct] += qty;
            }
        });
        return out;
    };

    const openingPendingForSize = (supplierId, size, editRow) => {
        const live = Number(getPendingEntry(supplierId)[size] || 0);
        if (!editRow || !editRow.id) return live;
        const sent = parseSentFromPurchaseRow(editRow);
        const recv = parseRecvFromPurchaseRow(editRow);
        const net = Number(sent[size] || 0) - Number(recv[size] || 0);
        return live - net;
    };

    const parseBreakdownForForm = (row) => {
        const qty = { Small: 0, Medium: 0, Large: 0 };
        const pressure = { Small: '', Medium: '', Large: '' };
        const unit = { Small: '', Medium: '', Large: '' };
        const raw = row.breakdown_data || '';
        if (raw) {
            String(raw).split('||').forEach((entry) => {
                const p = entry.split('::');
                if (p.length >= 6) {
                    const size = (p[0] || '').trim() || (row.cylinder_type || 'Small');
                    if (!SIZES.includes(size)) return;
                    qty[size] = Number(p[2] || 0);
                    pressure[size] = p[3] !== undefined && p[3] !== '' && Number(p[3]) !== 0 ? String(p[3]) : '';
                    unit[size] = p[4] !== undefined && p[4] !== '' && Number(p[4]) !== 0 ? String(p[4]) : '';
                } else if (p.length >= 3) {
                    const ct = row.cylinder_type || 'Small';
                    qty[ct] += Number(p[1] || 0);
                }
            });
        }
        return { qty, pressure, unit };
    };

    const renderSummary = () => {
        document.querySelector('#summaryCards [data-key="suppliers"]').textContent = String(currentData.summary.suppliers);
        document.querySelector('#summaryCards [data-key="purchases"]').textContent = fmt(currentData.summary.purchases);
        document.querySelector('#summaryCards [data-key="pending"]').textContent = fmt(currentData.summary.pending);
        document.querySelector('#summaryCards [data-key="paid"]').textContent = fmt(currentData.summary.paid);
        document.getElementById('stockTotal').textContent = Number(currentData.stockTotals.total || 0).toFixed(2);
        document.getElementById('stockAvailable').textContent = Number(currentData.stockTotals.available || 0).toFixed(2);
        const totalPressureEl = document.getElementById('stockTotalPressure');
        const availablePressureEl = document.getElementById('stockAvailablePressure');
        if (totalPressureEl) totalPressureEl.textContent = Number(currentData.stockTotals.total_pressure || 0).toFixed(2);
        if (availablePressureEl) availablePressureEl.textContent = Number(currentData.stockTotals.available_pressure || 0).toFixed(2);
    };

    const renderTypedStock = () => {
        typedStockCards.innerHTML = '';
        (currentData.stockByType || []).forEach((item) => {
            const card = document.createElement('div');
            card.className = 'rounded-xl border border-slate-200 p-3 bg-white';
            card.innerHTML = `<p class="text-xs text-slate-500">${escapeHtml(sizeLabel(item.cylinder_type))}</p>
                <p class="text-lg font-semibold text-oxygenDeep">${escapeHtml(t.stockTotal)}: ${Number(item.total || 0).toFixed(2)} <span class="text-xs font-medium text-slate-500">(${escapeHtml(t.pressurePsi)} ${Number(item.total_pressure || 0).toFixed(2)})</span></p>
                <p class="text-xs text-emerald-600">${escapeHtml(t.stockAvailable)}: ${Number(item.available || 0).toFixed(2)} <span class="font-medium text-emerald-700">(${escapeHtml(t.pressurePsi)} ${Number(item.available_pressure || 0).toFixed(2)})</span></p>`;
            typedStockCards.appendChild(card);
        });
    };

    const renderSupplierOptions = () => {
        const html = [`<option value="">${escapeHtml(t.selectSupplier)}</option>`]
            .concat((currentData.supplierOptions || []).map((s) => `<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`));
        purchaseSupplier.innerHTML = html.join('');
        const filterHtml = [`<option value="0">${escapeHtml(t.allSuppliers)}</option>`]
            .concat((currentData.supplierOptions || []).map((s) => `<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`));
        supplierFilter.innerHTML = filterHtml.join('');
        paymentSupplierFilter.innerHTML = filterHtml.join('');
        refreshSupplierContextUI();
    };

    const refreshSupplierContextUI = () => {
        const sid = Number(purchaseSupplier.value || 0);
        const sentMap = currentData.totalSentBySupplier || {};
        const totalSent = Number(sentMap[String(sid)] ?? sentMap[sid] ?? 0);
        if (totalSentAllTimeLine) {
            if (sid > 0) {
                totalSentAllTimeLine.textContent = `${t.totalDispatchedHint}: ${totalSent}`;
                totalSentAllTimeLine.classList.remove('hidden');
            } else {
                totalSentAllTimeLine.classList.add('hidden');
            }
        }
        if (supplierPendingBadge && previousPendingLine) {
            if (sid <= 0) {
                supplierPendingBadge.textContent = t.historyBadge;
                supplierPendingBadge.title = t.badgeTitlePending;
                previousPendingLine.textContent = t.pendingSelect;
            } else {
                const p = getPendingEntry(sid);
                const sum = Number(p.total != null ? p.total : SIZES.reduce((a, sz) => a + Number(p[sz] || 0), 0));
                supplierPendingBadge.textContent = `${t.bakeePrefix} ${sum}`;
                supplierPendingBadge.title = `${t.bakeeTitleDetail}: ${t.sizeSmall} ${Number(p.Small || 0)}, ${t.sizeMedium} ${Number(p.Medium || 0)}, ${t.sizeLarge} ${Number(p.Large || 0)}. ${sum}.`;
                const openParts = SIZES.map((sz) => `${sizeLabel(sz)}: ${openingPendingForSize(sid, sz, editPurchaseRow)}`).join('; ');
                previousPendingLine.textContent = `${t.pendingIntro} — ${openParts}`;
            }
        }
        calcPurchase();
    };

    const renderSuppliers = () => {
        if (!currentData.suppliers.length) {
            suppliersTbody.innerHTML = `<tr><td class="p-3 text-slate-500" colspan="7">${escapeHtml(t.noSuppliers)}</td></tr>`;
            return;
        }
        suppliersTbody.innerHTML = currentData.suppliers.map((row) => {
            const balance = Math.max(0, Number(row.purchases || 0) - Number(row.paid || 0));
            return `<tr class="border-t border-slate-100 fade-in">
                <td class="p-3">${escapeHtml(row.name)}</td>
                <td class="p-3">${escapeHtml(row.contact_person || '-')}</td>
                <td class="p-3">${escapeHtml(row.phone || '-')}</td>
                <td class="p-3">${fmt(row.purchases)}</td>
                <td class="p-3">${fmt(row.paid)}</td>
                <td class="p-3 ${balance > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600'}">${fmt(balance)}</td>
                <td class="p-3">
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-soft" href="?module=ledger&supplier_q=${encodeURIComponent(String(row.name || ''))}<?= i18n_lang_query() ?>">${escapeHtml(t.viewLedger)}</a>
                        <button type="button" class="btn btn-soft supplier-delete-btn" data-id="${Number(row.id)}">${escapeHtml(t.delete)}</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    };

    const renderPurchases = () => {
        if (!currentData.purchases.length) {
            purchaseTbody.innerHTML = `<tr><td class="p-3 text-slate-500" colspan="11">${escapeHtml(t.noPurchases)}</td></tr>`;
            return;
        }
        purchaseTbody.innerHTML = currentData.purchases.map((row) => `<tr class="border-t border-slate-100 fade-in">
            <td class="p-3">${escapeHtml(row.transaction_date)}</td>
            <td class="p-3">${escapeHtml(row.supplier_name)}</td>
            <td class="p-3">${escapeHtml(sizeLabel(row.cylinder_type))}</td>
            <td class="p-3">${Number(row.sent_quantity || 0)}</td>
            <td class="p-3">${Number(row.total_received || row.quantity || 0)}</td>
            <td class="p-3">${Number(row.inventory_quantity || row.quantity || 0).toFixed(2)}</td>
            <td class="p-3">${fmt(row.total_amount)}</td>
            <td class="p-3">${fmt(row.paid_amount)}</td>
            <td class="p-3">${fmt(row.remaining_amount)}</td>
            <td class="p-3"><span class="px-2 py-1 rounded-full text-xs ${statusClass(row.payment_status)}">${escapeHtml(mapPayStatus(row.payment_status))}</span></td>
            <td class="p-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-soft purchase-edit-btn" data-purchase='${JSON.stringify(row).replace(/'/g, '&#039;')}'>${escapeHtml(t.edit)}</button>
                    <button type="button" class="btn btn-soft purchase-delete-btn" data-id="${Number(row.id)}">${escapeHtml(t.delete)}</button>
                    <button type="button" class="btn btn-soft print-btn" data-id="${Number(row.id)}">${escapeHtml(t.printInvoice)}</button>
                </div>
            </td>
        </tr>`).join('');
    };

    const renderPaymentHistory = () => {
        const rows = currentData.paymentHistory || [];
        document.getElementById('periodPaidAmount').textContent = fmt((currentData.paymentSummary || {}).paid || 0);
        document.getElementById('periodOutstandingAmount').textContent = fmt((currentData.paymentSummary || {}).outstanding || 0);
        if (!rows.length) {
            paymentHistoryBody.innerHTML = `<tr><td class="p-3 text-slate-500" colspan="8">${escapeHtml(t.noPayments)}</td></tr>`;
            return;
        }
        paymentHistoryBody.innerHTML = rows.map((row) => {
            const installments = String(row.installment_breakdown || '')
                .split('|')
                .filter(Boolean)
                .map((item) => {
                    const [date, amount] = item.split(':');
                    return `<div class="text-xs text-slate-600">${escapeHtml(date || '-')} - ${fmt(amount || 0)}</div>`;
                }).join('');
            return `<tr class="border-t border-slate-100 fade-in">
                <td class="p-3">${escapeHtml(row.last_payment_date || '-')}</td>
                <td class="p-3">${escapeHtml(row.supplier_name || '-')}</td>
                <td class="p-3">SP-${Number(row.transaction_id || 0)}</td>
                <td class="p-3">${escapeHtml(row.transaction_date || '-')}</td>
                <td class="p-3">${fmt(row.total_amount)}</td>
                <td class="p-3">${installments || `<span class="text-slate-500 text-xs">${escapeHtml(t.noInstallments)}</span>`}</td>
                <td class="p-3">${fmt(row.paid_total)}</td>
                <td class="p-3 ${Number(row.outstanding_balance || 0) > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600'}">${fmt(row.outstanding_balance)}</td>
            </tr>`;
        }).join('');
    };

    const syncHiddenCylinderType = () => {
        const el = document.getElementById('purchaseCylinderTypeHidden');
        if (!el) return;
        const s = Number(purchaseForm.sent_qty_Small?.value || 0);
        const m = Number(purchaseForm.sent_qty_Medium?.value || 0);
        const l = Number(purchaseForm.sent_qty_Large?.value || 0);
        const nz = [];
        if (s > 0) nz.push('Small');
        if (m > 0) nz.push('Medium');
        if (l > 0) nz.push('Large');
        if (nz.length === 1) el.value = nz[0];
        else if (nz.length > 1) el.value = 'Mixed';
        else el.value = 'Small';
    };

    const resetDispatchRows = () => {
        SIZES.forEach((sz) => {
            const q = purchaseForm[`sent_qty_${sz}`];
            const pr = purchaseForm[`sent_pressure_${sz}`];
            if (q) q.value = '0';
            if (pr) pr.value = '';
        });
        syncHiddenCylinderType();
    };

    const resetRecvRows = () => {
        SIZES.forEach((sz) => {
            const q = purchaseForm[`recv_qty_${sz}`];
            const pr = purchaseForm[`recv_pressure_${sz}`];
            const u = purchaseForm[`recv_unit_price_${sz}`];
            if (q) q.value = '0';
            if (pr) pr.value = '';
            if (u) u.value = '';
        });
    };

    const renderAll = () => {
        renderSummary();
        renderTypedStock();
        renderSupplierOptions();
        renderSuppliers();
        renderPurchases();
        renderPaymentHistory();
    };

    const buildRemainingByPayment = (historyRows, paymentRows) => {
        const totalByTx = {};
        const paidRunningByTx = {};
        (historyRows || []).forEach((tx) => {
            totalByTx[Number(tx.id)] = Number(tx.total_amount || 0);
            paidRunningByTx[Number(tx.id)] = 0;
        });
        const byTx = {};
        (paymentRows || []).forEach((p) => {
            const key = Number(p.transaction_id || 0);
            if (!byTx[key]) byTx[key] = [];
            byTx[key].push(p);
        });
        const remainingByPaymentId = {};
        Object.keys(byTx).forEach((txIdKey) => {
            const txId = Number(txIdKey);
            const rows = byTx[txId].slice().sort((a, b) => {
                const da = String(a.payment_date || '');
                const db = String(b.payment_date || '');
                if (da !== db) return da.localeCompare(db);
                return Number(a.id || 0) - Number(b.id || 0);
            });
            const total = Number(totalByTx[txId] || 0);
            let paid = 0;
            rows.forEach((row) => {
                paid += Number(row.amount || 0);
                remainingByPaymentId[Number(row.id || 0)] = Math.max(0, total - paid);
            });
        });
        return remainingByPaymentId;
    };

    const loadSupplierHistory = async (supplierId) => {
        const body = new FormData();
        body.append('action', 'supplier_history');
        body.append('supplier_id', String(supplierId || 0));
        const result = await request(body);
        if (!result.ok) {
            showToast(result.message || t.failedHistory);
            return;
        }
        historyModal.dataset.supplierId = String(supplierId || 0);
        const purchaseBody = document.getElementById('historyPurchaseBody');
        const ledgerBody = document.getElementById('historyLedgerBody');
        const paymentBody = document.getElementById('historyPaymentBody');
        const historyRows = result.history || [];
        const paymentRows = result.payments || [];
        const remainingByPaymentId = buildRemainingByPayment(historyRows, paymentRows);
        const pb = result.pendingBakee || { Small: 0, Medium: 0, Large: 0, total: 0 };
        const pendingLine = t.dueRemainingTpl
            .replace(/\{s\}/g, String(Number(pb.Small || 0)))
            .replace(/\{m\}/g, String(Number(pb.Medium || 0)))
            .replace(/\{l\}/g, String(Number(pb.Large || 0)))
            .replace(/\{t\}/g, String(Number(pb.total || 0)));
        const pendingEl = document.getElementById('historyPendingBakeeBody');
        if (pendingEl) pendingEl.innerHTML = pendingLine;
        const pendingCompact = `S:${Number(pb.Small || 0)} M:${Number(pb.Medium || 0)} L:${Number(pb.Large || 0)}`;
        purchaseBody.innerHTML = historyRows.length ? historyRows.map((row) => `<tr class="border-t border-slate-100">
            <td class="p-2 whitespace-nowrap align-top">${escapeHtml(row.transaction_date)}</td>
            <td class="p-2 align-top text-xs text-slate-800">${formatHistoryBreakdownCell(row.display_size_breakdown)}</td>
            <td class="p-2 align-top text-xs whitespace-nowrap">${escapeHtml(row.display_sent_pressure || '—')}</td>
            <td class="p-2 align-top text-xs whitespace-nowrap">${escapeHtml(row.display_recv_pressure || '—')}</td>
            <td class="p-2 align-top text-xs whitespace-nowrap">${escapeHtml(row.display_unit_price || fmtRs(row.unit_price || 0))}</td>
            <td class="p-2 text-right align-top whitespace-nowrap font-medium">${fmtRs(row.total_amount)}</td>
            <td class="p-2 align-top"><span class="px-2 py-1 rounded-full text-xs ${statusClass(row.payment_status)}">${escapeHtml(mapPayStatus(row.payment_status))}</span></td>
        </tr>`).join('') : `<tr><td class="p-2 text-slate-500" colspan="7">${escapeHtml(t.noPurchaseHistory)}</td></tr>`;
        ledgerBody.innerHTML = (result.ledger || []).length ? result.ledger.map((row) => {
            const desc = row.description || row.reference_type || t.entryFallback;
            return `<tr class="border-t border-slate-100">
            <td class="p-2 whitespace-nowrap align-top">${escapeHtml(row.entry_date)}</td>
            <td class="p-2 align-top text-xs text-slate-800 max-w-[340px]"><span title="${escapeHtml(desc)}">${escapeHtml(desc)}</span></td>
            <td class="p-2 align-top text-xs text-amber-900/90 whitespace-nowrap" title="${escapeHtml(t.ledgerPendingTitle)}">${escapeHtml(pendingCompact)}</td>
            <td class="p-2 text-right align-top whitespace-nowrap font-medium">${fmtRs(row.debit)}</td>
            <td class="p-2 text-right align-top whitespace-nowrap font-medium">${fmtRs(row.credit)}</td>
            <td class="p-2 text-right align-top whitespace-nowrap font-semibold text-slate-900">${fmtRs(row.balance)}</td>
        </tr>`;
        }).join('') : `<tr><td class="p-2 text-slate-500" colspan="6">${escapeHtml(t.noLedger)}</td></tr>`;
        paymentBody.innerHTML = paymentRows.length ? paymentRows.map((row) => `<tr class="border-t border-slate-100">
            <td class="p-2">${escapeHtml(row.payment_date || '-')}</td>
            <td class="p-2">SP-${Number(row.transaction_id || 0)}</td>
            <td class="p-2 text-right font-medium">${fmtRs(row.amount)}</td>
            <td class="p-2 text-right ${Number(remainingByPaymentId[Number(row.id || 0)] || 0) > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600'}">${fmtRs(remainingByPaymentId[Number(row.id || 0)] || 0)}</td>
            <td class="p-2">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-soft history-payment-edit-btn"
                        data-id="${Number(row.id || 0)}"
                        data-transaction-id="${Number(row.transaction_id || 0)}"
                        data-date="${escapeHtml(row.payment_date || '')}"
                        data-amount="${Number(row.amount || 0)}"
                        data-payment-type="${escapeHtml(row.payment_type || 'Cash')}">${escapeHtml(t.edit)}</button>
                    <button type="button" class="btn btn-soft history-payment-delete-btn" data-id="${Number(row.id || 0)}">${escapeHtml(t.delete)}</button>
                </div>
            </td>
        </tr>`).join('') : `<tr><td class="p-2 text-slate-500" colspan="5">${escapeHtml(t.noInstallmentPayments)}</td></tr>`;
        const duePurchases = historyRows.filter((row) => Number(row.remaining_amount || 0) > 0);
        const txOptions = [`<option value="">${escapeHtml(t.selectPurchase)}</option>`].concat(
            duePurchases.map((row) => `<option value="${Number(row.id)}">SP-${Number(row.id)} | ${escapeHtml(row.transaction_date)} | ${escapeHtml(t.lblDue)}: ${fmtRs(row.total_amount)} · ${escapeHtml(t.lblRem)}: ${fmtRs(row.remaining_amount)}</option>`),
        );
        ledgerPaymentForm.transaction_id.innerHTML = txOptions.join('');
        ledgerPaymentForm.payment_id.value = '';
        ledgerPaymentForm.supplier_id.value = String(supplierId || 0);
        ledgerPaymentForm.payment_date.value = '<?= date('Y-m-d') ?>';
        ledgerPaymentForm.amount.value = '';
        ledgerPaymentForm.payment_type.value = 'Cash';
        ledgerPaymentSubmitBtn.textContent = t.savePayment;
        ledgerPaymentResetBtn.classList.add('hidden');
        historyModal.classList.remove('hidden');
        historyModal.classList.add('flex');
    };

    const calcPurchase = () => {
        syncHiddenCylinderType();
        const sid = Number(purchaseSupplier.value || 0);
        const sentBy = {
            Small: Number(purchaseForm.sent_qty_Small?.value || 0),
            Medium: Number(purchaseForm.sent_qty_Medium?.value || 0),
            Large: Number(purchaseForm.sent_qty_Large?.value || 0),
        };
        SIZES.forEach((sz) => {
            const subEl = document.getElementById(`dispatchSub${sz}`);
            if (subEl) subEl.textContent = String(sentBy[sz] || 0);
        });
        const grand = sentBy.Small + sentBy.Medium + sentBy.Large;
        const dg = document.getElementById('dispatchSentGrand');
        if (dg) dg.textContent = String(grand);
        const defaultUnit = Number(purchaseForm.unit_price?.value || 0);
        const paid = Number(purchaseForm.paid_amount?.value || 0);
        let inventoryFromRows = 0;
        let total = 0;
        SIZES.forEach((sz) => {
            const qtyEl = purchaseForm[`recv_qty_${sz}`];
            const unitEl = purchaseForm[`recv_unit_price_${sz}`];
            const qty = qtyEl ? Number(qtyEl.value || 0) : 0;
            let lineUnit = unitEl ? Number(unitEl.value || 0) : 0;
            if (!lineUnit || lineUnit < 0) lineUnit = defaultUnit;
            inventoryFromRows += qty;
            total += qty * lineUnit;
            const pendIn = openingPendingForSize(sid, sz, editPurchaseRow);
            const sentHere = Number(sentBy[sz] || 0);
            const recv = qty;
            const pendingAfter = pendIn + sentHere - recv;
            const pendField = refillBreakdownBody.querySelector(`.recv-pending[data-size="${sz}"]`);
            if (pendField) pendField.value = String(Math.max(0, pendingAfter));
            const lineSpan = refillBreakdownBody.querySelector(`.recv-line-total[data-size="${sz}"]`);
            if (lineSpan) lineSpan.textContent = fmt(qty * lineUnit);
        });
        const trEl = document.getElementById('purchaseTotalReceived');
        if (trEl) trEl.value = String(Math.max(0, Math.round(inventoryFromRows)));
        if (purchaseForm.inventory_qty_preview) {
            purchaseForm.inventory_qty_preview.value = inventoryFromRows > 0 ? Number(inventoryFromRows).toFixed(2) : '0.00';
        }
        const remaining = Math.max(0, total - paid);
        if (purchaseForm.total_amount_preview) purchaseForm.total_amount_preview.value = total > 0 ? fmt(total) : '';
        if (purchaseForm.remaining_amount_preview) purchaseForm.remaining_amount_preview.value = total > 0 ? fmt(remaining) : '';
    };

    ['unit_price', 'paid_amount'].forEach((name) => {
        const field = purchaseForm[name];
        if (!field) return;
        field.addEventListener('input', calcPurchase);
    });
    SIZES.forEach((sz) => {
        ['recv_qty_', 'recv_pressure_', 'recv_unit_price_', 'sent_qty_', 'sent_pressure_'].forEach((prefix) => {
            const field = purchaseForm[`${prefix}${sz}`];
            if (field) field.addEventListener('input', calcPurchase);
        });
    });
    purchaseSupplier.addEventListener('change', refreshSupplierContextUI);

    supplierForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = await request(new FormData(supplierForm));
        showToast(result.message || t.saved);
        if (!result.ok) return;
        supplierForm.reset();
        currentData = result.data;
        renderAll();
    });

    purchaseForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        calcPurchase();
        const result = await request(new FormData(purchaseForm));
        showToast(result.message || t.saved);
        if (!result.ok) return;
        editPurchaseRow = null;
        purchaseForm.reset();
        purchaseForm.action.value = 'record_purchase';
        purchaseForm.purchase_id.value = '';
        purchaseSubmitBtn.textContent = t.saveReceipt;
        purchaseResetBtn.classList.add('hidden');
        purchaseForm.transaction_date.value = '<?= date('Y-m-d') ?>';
        purchaseForm.date_sent.value = '<?= date('Y-m-d') ?>';
        resetDispatchRows();
        resetRecvRows();
        const trEl = document.getElementById('purchaseTotalReceived');
        if (trEl) trEl.value = '0';
        calcPurchase();
        currentData = result.data;
        renderAll();
        refreshSupplierContextUI();
    });

    purchaseResetBtn.addEventListener('click', () => {
        editPurchaseRow = null;
        purchaseForm.reset();
        purchaseForm.action.value = 'record_purchase';
        purchaseForm.purchase_id.value = '';
        purchaseSubmitBtn.textContent = t.saveReceipt;
        purchaseResetBtn.classList.add('hidden');
        purchaseForm.transaction_date.value = '<?= date('Y-m-d') ?>';
        purchaseForm.date_sent.value = '<?= date('Y-m-d') ?>';
        resetDispatchRows();
        resetRecvRows();
        const trEl = document.getElementById('purchaseTotalReceived');
        if (trEl) trEl.value = '0';
        calcPurchase();
        refreshSupplierContextUI();
    });

    document.getElementById('applyFilterBtn').addEventListener('click', async () => {
        const body = new FormData();
        body.append('action', 'fetch_dashboard');
        body.append('search', document.getElementById('supplierSearch').value || '');
        body.append('from_date', document.getElementById('filterFromDate').value || '');
        body.append('to_date', document.getElementById('filterToDate').value || '');
        body.append('supplier_filter', supplierFilter.value || '0');
        body.append('payment_period', paymentPeriodFilter.value || 'weekly');
        body.append('payment_supplier_filter', paymentSupplierFilter.value || '0');
        const result = await request(body);
        if (!result.ok) {
            showToast(result.message || t.filterFailed);
            return;
        }
        currentData = result.data;
        renderAll();
    });

    document.getElementById('applyPaymentFilterBtn').addEventListener('click', async () => {
        const body = new FormData();
        body.append('action', 'fetch_payment_history');
        body.append('payment_period', paymentPeriodFilter.value || 'weekly');
        body.append('payment_supplier_filter', paymentSupplierFilter.value || '0');
        const result = await request(body);
        if (!result.ok) {
            showToast(result.message || t.paymentHistoryFailed);
            return;
        }
        currentData.paymentHistory = result.paymentHistory || [];
        currentData.paymentSummary = result.paymentSummary || { period: 'weekly', paid: 0, outstanding: 0 };
        renderPaymentHistory();
    });

    document.getElementById('exportCsvBtn').addEventListener('click', () => {
        const params = new URLSearchParams({
            module: 'suppliers',
            action: 'export_purchases_csv',
            from_date: document.getElementById('filterFromDate').value || '',
            to_date: document.getElementById('filterToDate').value || '',
            supplier_filter: supplierFilter.value || '0'
        });
        const qs = params.toString() + <?= json_encode(i18n_locale() === 'ps' ? '&lang=ps' : '', JSON_UNESCAPED_UNICODE) ?>;
        window.open(`?${qs}`, '_blank');
    });

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.classList.contains('purchase-edit-btn')) {
            const payload = target.getAttribute('data-purchase') || '{}';
            const row = JSON.parse(payload);
            editPurchaseRow = row;
            purchaseForm.action.value = 'update_purchase';
            purchaseForm.purchase_id.value = String(row.id || '');
            purchaseForm.supplier_id.value = String(row.supplier_id || '');
            const sents = parseSentFromPurchaseRow(row);
            const pressKeys = { Small: 'sent_pressure_small', Medium: 'sent_pressure_medium', Large: 'sent_pressure_large' };
            SIZES.forEach((sz) => {
                const q = purchaseForm[`sent_qty_${sz}`];
                const pr = purchaseForm[`sent_pressure_${sz}`];
                if (q) q.value = String(sents[sz] ?? 0);
                if (pr) {
                    const pk = pressKeys[sz];
                    const pv = row[pk];
                    pr.value = pv != null && pv !== '' ? String(pv) : '';
                }
            });
            syncHiddenCylinderType();
            purchaseForm.date_sent.value = row.date_sent || '<?= date('Y-m-d') ?>';
            purchaseForm.unit_price.value = String(row.unit_price || 0);
            purchaseForm.transaction_date.value = row.transaction_date || '<?= date('Y-m-d') ?>';
            purchaseForm.paid_amount.value = String(row.paid_amount || 0);
            purchaseForm.payment_type.value = row.payment_type || 'Credit';
            const parsed = parseBreakdownForForm(row);
            SIZES.forEach((sz) => {
                const q = purchaseForm[`recv_qty_${sz}`];
                const pr = purchaseForm[`recv_pressure_${sz}`];
                const u = purchaseForm[`recv_unit_price_${sz}`];
                if (q) q.value = String(parsed.qty[sz] ?? 0);
                if (pr) pr.value = parsed.pressure[sz] || '';
                if (u) u.value = parsed.unit[sz] || '';
            });
            const trEl = document.getElementById('purchaseTotalReceived');
            if (trEl) trEl.value = String(row.total_received || row.quantity || 0);
            purchaseSubmitBtn.textContent = t.updateReceipt;
            purchaseResetBtn.classList.remove('hidden');
            refreshSupplierContextUI();
            calcPurchase();
            purchaseForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (target.classList.contains('purchase-delete-btn')) {
            if (!confirm(t.confirmDelPurchase)) return;
            const body = new FormData();
            body.append('action', 'delete_purchase');
            body.append('purchase_id', target.dataset.id || '0');
            const result = await request(body);
            showToast(result.message || t.done);
            if (!result.ok) return;
            currentData = result.data;
            renderAll();
        }
        if (target.classList.contains('supplier-delete-btn')) {
            if (!confirm(t.confirmDelSupplier)) return;
            const body = new FormData();
            body.append('action', 'delete_supplier');
            body.append('supplier_id', target.dataset.id || '0');
            const result = await request(body);
            showToast(result.message || t.done);
            if (!result.ok) return;
            currentData = result.data;
            renderAll();
        }
        if (target.classList.contains('history-payment-edit-btn')) {
            ledgerPaymentForm.payment_id.value = target.dataset.id || '';
            const editTxId = Number(target.dataset.transactionId || 0);
            const txSelect = ledgerPaymentForm.transaction_id;
            if (editTxId > 0) {
                const hasOpt = [...txSelect.options].some((o) => Number(o.value) === editTxId);
                if (!hasOpt) {
                    const opt = document.createElement('option');
                    opt.value = String(editTxId);
                    opt.textContent = t.optFullyPaidTpl.replace(/\{id\}/g, String(editTxId));
                    txSelect.appendChild(opt);
                }
                txSelect.value = String(editTxId);
            } else {
                ledgerPaymentForm.transaction_id.value = '';
            }
            ledgerPaymentForm.payment_date.value = target.dataset.date || '<?= date('Y-m-d') ?>';
            ledgerPaymentForm.amount.value = String(target.dataset.amount || '');
            ledgerPaymentForm.payment_type.value = target.dataset.paymentType || 'Cash';
            ledgerPaymentSubmitBtn.textContent = t.updatePayment;
            ledgerPaymentResetBtn.classList.remove('hidden');
        }
        if (target.classList.contains('history-payment-delete-btn')) {
            if (!confirm(t.confirmDelPayment)) return;
            const supplierId = Number(historyModal.dataset.supplierId || 0);
            const body = new FormData();
            body.append('action', 'delete_supplier_payment');
            body.append('payment_id', target.dataset.id || '0');
            const result = await request(body);
            showToast(result.message || t.done);
            if (!result.ok) return;
            if (supplierId > 0) {
                await loadSupplierHistory(supplierId);
            }
        }
        if (target.classList.contains('print-btn')) {
            const id = Number(target.dataset.id || 0);
            if (id > 0) {
                window.open(`?module=suppliers&action=print_purchase_invoice&purchase_id=${id}<?= i18n_locale() === 'ps' ? '&lang=ps' : '' ?>`, '_blank');
            }
        }
        if (target.dataset.closeHistory === '1') {
            historyModal.classList.add('hidden');
            historyModal.classList.remove('flex');
        }
    });
    ledgerPaymentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const supplierId = Number(ledgerPaymentForm.supplier_id.value || historyModal.dataset.supplierId || 0);
        if (supplierId <= 0) {
            showToast(t.supplierContextMissing);
            return;
        }
        const body = new FormData(ledgerPaymentForm);
        body.append('action', 'save_supplier_payment');
        const result = await request(body);
        showToast(result.message || t.saved);
        if (!result.ok) return;
        await loadSupplierHistory(supplierId);
    });

    ledgerPaymentResetBtn.addEventListener('click', () => {
        ledgerPaymentForm.payment_id.value = '';
        ledgerPaymentForm.payment_date.value = '<?= date('Y-m-d') ?>';
        ledgerPaymentForm.amount.value = '';
        ledgerPaymentForm.payment_type.value = 'Cash';
        ledgerPaymentSubmitBtn.textContent = t.savePayment;
        ledgerPaymentResetBtn.classList.add('hidden');
    });

    renderAll();
    resetDispatchRows();
    resetRecvRows();
    editPurchaseRow = null;
    calcPurchase();
    refreshSupplierContextUI();
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.suppliers'), $content);
