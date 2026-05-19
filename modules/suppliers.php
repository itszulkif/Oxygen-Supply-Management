<?php

$pdo = db();
ensure_supplier_transaction_notes_column($pdo);

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
ensure_supplier_transaction_notes_column($pdo);
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
ensure_cylinder_daka_tash_columns($pdo);

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

$seedTypes = [standard_cylinder_size()];
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

$formatPurchaseLedgerDescription = static function (PDO $pdo, int $purchaseId, ?string $userNotes = null): string {
    $stmt = $pdo->prepare('SELECT * FROM supplier_transactions WHERE id = ? LIMIT 1');
    $stmt->execute([$purchaseId]);
    $t = $stmt->fetch();
    if (!$t) {
        return "Purchase #SP-{$purchaseId}";
    }
    $notes = $userNotes !== null ? trim($userNotes) : trim((string) ($t['notes'] ?? ''));
    if ($notes !== '') {
        return mb_strlen($notes) > 1990 ? (mb_substr($notes, 0, 1987) . '…') : $notes;
    }
    $recvQty = supplier_transaction_received_qty($t);
    $total = format_currency((float) ($t['total_amount'] ?? 0));
    $paid = format_currency((float) ($t['paid_amount'] ?? 0));
    $remaining = format_currency((float) ($t['remaining_amount'] ?? 0));
    $payType = supplier_payment_type_label((string) ($t['payment_type'] ?? ''));
    $out = "Purchase #SP-{$purchaseId} | Received: {$recvQty} | Bill: {$total} | Paid: {$paid} | Remaining: {$remaining} | {$payType}";
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
    $totalSent = (int) ($sentBy['Small'] ?? 0) + (int) ($sentBy['Medium'] ?? 0) + (int) ($sentBy['Large'] ?? 0);
    if ($totalSent > 0) {
        $legacySp = $t['sent_pressure'] ?? null;
        $psiNum = ($legacySp !== null && $legacySp !== '') ? (float) $legacySp : 0.0;
        if ($psiNum <= 0) {
            foreach (['small', 'medium', 'large'] as $pk) {
                $pr = $t['sent_pressure_' . $pk] ?? null;
                if ($pr !== null && $pr !== '' && (float) $pr > 0) {
                    $psiNum = (float) $pr;
                    break;
                }
            }
        }
        $psiLabel = $psiNum > 0 ? (rtrim(rtrim((string) $psiNum, '0'), '.') . ' PSI') : '—';
        $sentChunks[] = __('suppliers.break_qty') . ": {$totalSent}, " . __('suppliers.break_pressure') . ": {$psiLabel}";
        if ($psiNum > 0) {
            $sentPsiOnly[] = $psiLabel;
        }
    }
    $recvChunks = [];
    $recvPsiOnly = [];
    $raw = (string) ($t['breakdown_data'] ?? '');
    if ($raw !== '') {
        foreach (explode('||', $raw) as $entry) {
            if ($entry === '') {
                continue;
            }
            $p = explode('::', $entry);
            if (count($p) >= 6) {
                $qty = (int) ($p[2] ?? 0);
                $psi = (float) ($p[3] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $psiLabel = $psi > 0 ? (rtrim(rtrim((string) $psi, '0'), '.') . ' PSI') : '—';
                $recvChunks[] = __('suppliers.break_qty') . ": {$qty}, " . __('suppliers.break_pressure') . ": {$psiLabel}";
                if ($psi > 0) {
                    $recvPsiOnly[] = rtrim(rtrim((string) $psi, '0'), '.') . ' PSI';
                }
            } elseif (count($p) >= 3) {
                $qty = (int) ($p[1] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $recvChunks[] = __('suppliers.break_qty') . ": {$qty}, " . __('suppliers.break_pressure') . ': —';
            }
        }
    }
    $legacySp = $t['sent_pressure'] ?? null;
    $legacySentPsi = ($legacySp !== null && $legacySp !== '' && (float) $legacySp > 0)
        ? (rtrim(rtrim((string) (float) $legacySp, '0'), '.') . ' PSI') : '';
    $notes = trim((string) ($t['notes'] ?? ''));

    return array_merge($t, [
        'received_qty' => supplier_transaction_received_qty($t),
        'display_size_breakdown' => implode(' | ', array_filter([
            $sentChunks ? (__('suppliers.label_dispatch') . ': ' . implode(' | ', $sentChunks)) : '',
            $recvChunks ? (__('suppliers.label_receipt') . ': ' . implode(' | ', $recvChunks)) : '',
        ])),
        'display_sent_pressure' => $sentPsiOnly ? implode('; ', $sentPsiOnly) : ($legacySentPsi !== '' ? $legacySentPsi : '—'),
        'display_recv_pressure' => $recvPsiOnly ? implode('; ', $recvPsiOnly) : '—',
        'display_total_price' => format_currency((float) ($t['total_amount'] ?? 0)),
        'display_notes' => $notes,
        'display_payment_type' => supplier_payment_type_label((string) ($t['payment_type'] ?? '')),
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
            'description' => __('suppliers.ledger_opening_liability_desc'),
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
        if ($transactionId <= 0) {
            $payDesc = __('suppliers.ledger_opening_payment_desc', ['amount' => $amtStr, 'type' => $payType]);
        } else {
            $payDesc = "Installment #SP-{$transactionId} ({$payType}) — {$amtStr} toward refill purchase";
        }
        $events[] = [
            'date' => (string) ($row['payment_date'] ?? date('Y-m-d')),
            'type' => 'payment',
            'debit' => 0.0,
            'credit' => (float) ($row['amount'] ?? 0),
            'reference_type' => 'payment',
            'reference_id' => $paymentId,
            'description' => $payDesc,
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
        t.paid_amount, t.remaining_amount, t.payment_type, t.payment_status, t.notes, t.transaction_date,
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
            $map[$sid] = ['total' => 0];
        }
        $map[$sid]['total'] += (int) ($r['pending_count'] ?? 0);
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
    $pendingPayments = supplier_global_pending_payments($pdo);
    $rows = $loadSuppliersTable($pdo, $search);
    foreach ($rows as &$supplierRow) {
        $supplierRow['outstanding'] = supplier_amount_owed(
            (float) ($supplierRow['opening_balance'] ?? 0),
            (float) ($supplierRow['purchases'] ?? 0),
            (float) ($supplierRow['paid'] ?? 0)
        );
    }
    unset($supplierRow);
    $supplierOptions = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();
    $purchaseRows = $loadPurchaseRows($pdo, $fromDate, $toDate, $supplierFilter);
    $paymentHistory = $loadPaymentHistory($pdo, $paymentPeriod, $paymentSupplierFilter);
    $periodPaid = 0.0;
    $periodOutstanding = 0.0;
    foreach ($paymentHistory as $entry) {
        $periodPaid += (float) ($entry['paid_total'] ?? 0);
        $periodOutstanding += (float) ($entry['outstanding_balance'] ?? 0);
    }
    $stdStockType = standard_cylinder_size();
    $typeStock = cylinder_daka_tash_for_type($pdo, $stdStockType);
    $typedStock = [[
        'cylinder_type' => $stdStockType,
        'available' => $typeStock['daka'],
        'tash' => $typeStock['tash'],
    ]];
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
            'daka' => (int) $typeStock['daka'],
            'tash' => (int) $typeStock['tash'],
            'total' => (int) $typeStock['daka'] + (int) $typeStock['tash'],
            'available' => (int) $typeStock['daka'],
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
        __('suppliers.csv_quantity'),
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
            (int) $r['quantity'],
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
        <tr><th><?= e(__('suppliers.print_col_qty')) ?></th><th><?= e(__('suppliers.print_col_total_price')) ?></th><th><?= e(__('suppliers.print_col_paid')) ?></th><th><?= e(__('suppliers.print_col_remaining')) ?></th><th><?= e(__('suppliers.print_col_status')) ?></th></tr>
        <tr>
            <td><?= (int) $row['quantity'] ?></td>
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
    $action = trim(request_value('purchase_action', request_value('action', '')));
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

        if ($action === 'adjust_cylinder_stock') {
            $cylinderType = normalize_cylinder_type(standard_cylinder_size());
            $stockMode = request_value('stock_mode', 'add');
            if (!in_array($stockMode, ['add', 'set'], true)) {
                $stockMode = 'add';
            }
            $dakaQty = max(0, (int) request_value('daka_qty', '0'));
            $tashQty = max(0, (int) request_value('tash_qty', '0'));
            if ($dakaQty <= 0 && $tashQty <= 0) {
                $jsonResponse(false, __('suppliers.err_stock_qty_required'));
            }
            ensure_cylinder_daka_tash_columns($pdo);
            if ($stockMode === 'set') {
                set_cylinder_daka_tash_stock($pdo, $cylinderType, $dakaQty, $tashQty);
            } else {
                adjust_cylinder_daka_tash($pdo, $cylinderType, $dakaQty, $tashQty);
            }
            $jsonResponse(true, __('suppliers.msg_stock_updated'), ['data' => $buildPayload($pdo)]);
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
            $pdo->commit();
            if ($openingBalance > 0) {
                $rebuildSupplierLedger($pdo, $supplierId);
            }
            $jsonResponse(true, __('suppliers.msg_supplier_added'), ['data' => $buildPayload($pdo)]);
        }

        if ($action === 'record_purchase' || $action === 'update_purchase') {
            $purchaseId = (int) request_value('purchase_id', '0');
            $supplierId = (int) request_value('supplier_id', '0');
            $stdSize = standard_cylinder_size();
            $cylinderType = $stdSize;
            $sentQty = max(0, (int) request_value('sent_qty', '0'));
            if ($sentQty <= 0) {
                $sentQty = max(0, (int) request_value('sent_qty_' . $stdSize, '0'));
            }
            $sentByType = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
            $sentByType[$stdSize] = $sentQty;
            $sentPressureByType = ['Small' => null, 'Medium' => null, 'Large' => null];
            $sentQuantity = $sentQty;
            $sentPressureVal = null;
            $dateSent = request_value('date_sent', date('Y-m-d'));
            $transactionDate = request_value('transaction_date', date('Y-m-d'));
            $paidAmount = max(0, (float) request_value('paid_amount', '0'));
            $paymentType = request_value('payment_type', 'Credit');
            $transactionNotes = trim(request_value('transaction_notes', ''));
            $validPaymentTypes = ['Cash', 'Bank', 'Credit'];
            $recvByType = ['Small' => 0, 'Medium' => 0, 'Large' => 0];
            $breakdownRows = [];
            $inventoryQuantity = 0.0;
            $receivedPressureTotal = 0.0;
            $totalAmount = 0.0;
            $recvQty = max(0, (int) request_value('recv_qty', '0'));
            if ($recvQty <= 0) {
                $recvQty = max(0, (int) request_value('recv_qty_' . $stdSize, '0'));
            }
            $recvTotalPrice = max(0, (float) request_value('recv_total_price', '0'));
            if ($recvTotalPrice <= 0) {
                $recvTotalPrice = max(0, (float) request_value('recv_total_price_' . $stdSize, '0'));
            }
            if ($recvQty > 0) {
                $recvByType[$stdSize] = $recvQty;
                $invQty = (float) $recvQty;
                $inventoryQuantity = $invQty;
                $totalAmount = $recvTotalPrice;
                $lineUnit = $recvQty > 0 ? ($recvTotalPrice / $recvQty) : 0.0;
                $breakdownRows[] = [
                    'size' => $stdSize,
                    'qty' => $recvQty,
                    'pressure' => null,
                    'line_unit' => $lineUnit,
                    'inventory_qty' => $invQty,
                ];
            }
            if (!$breakdownRows) {
                $jsonResponse(false, __('suppliers.err_recv_qty_required'));
            }
            if (!in_array($paymentType, $validPaymentTypes, true)) {
                $jsonResponse(false, __('suppliers.err_invalid_types'));
            }
            if ($supplierId <= 0) {
                $jsonResponse(false, __('suppliers.err_select_supplier'));
            }
            if ($recvQty > 0 && $recvTotalPrice <= 0) {
                $jsonResponse(false, __('suppliers.err_total_price_required'));
            }
            if ($totalAmount <= 0) {
                $jsonResponse(false, __('suppliers.err_total_price_required'));
            }
            $computedReceived = (int) round($inventoryQuantity);
            $totalReceived = $computedReceived;
            $quantity = $totalReceived;
            $receivedFully = $totalReceived;
            $avgUnitPrice = $inventoryQuantity > 0 ? ($totalAmount / $inventoryQuantity) : 0.0;
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
                foreach ($oldSentByType as $ctype => $q) {
                    if ($q > 0) {
                        adjust_cylinder_daka_tash($pdo, $ctype, 0, $q);
                    }
                }
                foreach ($oldRecvByType as $ctype => $q) {
                    if ($q > 0) {
                        adjust_cylinder_daka_tash($pdo, $ctype, -$q, 0);
                    }
                }

                $pdo->prepare("DELETE FROM supplier_payments WHERE transaction_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_ledger WHERE reference_type = 'purchase' AND reference_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_ledger WHERE reference_type = 'payment' AND reference_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM supplier_refill_breakdown WHERE transaction_id = ?")->execute([$purchaseId]);
                $pdo->prepare("DELETE FROM refill_discrepancy WHERE transaction_id = ?")->execute([$purchaseId]);
                $txUpdate = $pdo->prepare("UPDATE supplier_transactions SET supplier_id=?, cylinder_type=?, sent_quantity=?, sent_pressure=?, sent_qty_small=?, sent_qty_medium=?, sent_qty_large=?, sent_pressure_small=?, sent_pressure_medium=?, sent_pressure_large=?, date_sent=?, quantity=?, total_received=?, inventory_quantity=?, received_fully_quantity=?, received_pressure_total=?, unit_price=?, total_amount=?, paid_amount=?, remaining_amount=?, payment_type=?, payment_status=?, notes=?, transaction_date=? WHERE id=?");
                $txUpdate->execute([
                    $supplierId, $cylinderType, $sentQuantity, $sentPressureVal,
                    $sentByType['Small'], $sentByType['Medium'], $sentByType['Large'],
                    $sentPressureByType['Small'], $sentPressureByType['Medium'], $sentPressureByType['Large'],
                    $dateSent, $quantity, $totalReceived, $inventoryQuantity, $receivedFully, $receivedPressureTotal, $unitPrice, $totalAmount, $paidAmount, $remainingAmount, $paymentType, $status, $transactionNotes !== '' ? $transactionNotes : null, $transactionDate, $purchaseId,
                ]);
            } else {
                $txInsert = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, cylinder_type, sent_quantity, sent_pressure, sent_qty_small, sent_qty_medium, sent_qty_large, sent_pressure_small, sent_pressure_medium, sent_pressure_large, date_sent, quantity, total_received, inventory_quantity, received_fully_quantity, received_pressure_total, unit_price, total_amount, paid_amount, remaining_amount, payment_type, payment_status, notes, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $txInsert->execute([
                    $supplierId, $cylinderType, $sentQuantity, $sentPressureVal,
                    $sentByType['Small'], $sentByType['Medium'], $sentByType['Large'],
                    $sentPressureByType['Small'], $sentPressureByType['Medium'], $sentPressureByType['Large'],
                    $dateSent, $quantity, $totalReceived, $inventoryQuantity, $receivedFully, $receivedPressureTotal, $unitPrice, $totalAmount, $paidAmount, $remainingAmount, $paymentType, $status, $transactionNotes !== '' ? $transactionNotes : null, $transactionDate,
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

            if ($sentQty > 0 && cylinder_tash_available($pdo, $stdSize) < $sentQty) {
                $pdo->rollBack();
                $jsonResponse(false, str_replace('{qty}', (string) $sentQty, __('suppliers.err_insufficient_tash')));
            }
            foreach ($sentByType as $ctype => $q) {
                if ($q > 0) {
                    adjust_cylinder_daka_tash($pdo, $ctype, 0, -$q);
                }
            }
            foreach ($breakdownRows as $rowItem) {
                $addDaka = (int) round((float) $rowItem['inventory_qty']);
                if ($addDaka > 0) {
                    adjust_cylinder_daka_tash($pdo, (string) $rowItem['size'], $addDaka, 0);
                }
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
            foreach ($oldSentByTypeDel as $ctype => $q) {
                if ($q > 0) {
                    adjust_cylinder_daka_tash($pdo, $ctype, 0, $q);
                }
            }
            foreach ($oldRecvByType as $ctype => $q) {
                if ($q > 0) {
                    adjust_cylinder_daka_tash($pdo, $ctype, -$q, 0);
                }
            }
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
                t.quantity, t.total_received, t.inventory_quantity, t.unit_price, t.total_amount, t.paid_amount, t.remaining_amount, t.payment_type, t.payment_status, t.notes, t.transaction_date,
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
            $pendingBakee = ['total' => 0];
            $pendingStmt = $pdo->prepare('SELECT COALESCE(SUM(pending_count), 0) FROM supplier_pending_cylinders WHERE supplier_id = ?');
            $pendingStmt->execute([$supplierId]);
            $pendingBakee['total'] = (int) $pendingStmt->fetchColumn();
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
            $paymentTarget = trim((string) request_value('payment_target', ''));
            $paymentDate = request_value('payment_date', date('Y-m-d'));
            $paymentType = request_value('payment_type', 'Cash');
            $amount = max(0, (float) request_value('amount', '0'));
            $validPaymentTypes = ['Cash', 'Bank', 'Credit'];
            if ($supplierId <= 0 || $amount <= 0) {
                $jsonResponse(false, __('suppliers.err_supplier_purchase_amount'));
            }
            if (!in_array($paymentType, $validPaymentTypes, true)) {
                $jsonResponse(false, __('suppliers.err_invalid_payment_type'));
            }
            $payOpening = $paymentTarget === 'opening' || $transactionId <= 0;
            if ($payOpening) {
                $openingRemaining = supplier_opening_balance_remaining($pdo, $supplierId);
                if ($openingRemaining <= 0.00001) {
                    $jsonResponse(false, __('suppliers.err_no_opening_due'));
                }
                if ($amount > $openingRemaining + 0.00001) {
                    $jsonResponse(false, __('suppliers.err_payment_exceeds_opening'));
                }
                $transactionId = 0;
            } else {
                $txStmt = $pdo->prepare("SELECT id, total_amount FROM supplier_transactions WHERE id = ? AND supplier_id = ? LIMIT 1");
                $txStmt->execute([$transactionId, $supplierId]);
                $tx = $txStmt->fetch();
                if (!$tx) {
                    $jsonResponse(false, __('suppliers.err_purchase_record_not_found'));
                }
            }
            $pdo->beginTransaction();
            if ($paymentId > 0) {
                $updateStmt = $pdo->prepare("UPDATE supplier_payments SET transaction_id = ?, amount = ?, payment_type = ?, payment_date = ? WHERE id = ? AND supplier_id = ?");
                $updateStmt->execute([$payOpening ? null : $transactionId, $amount, $paymentType, $paymentDate, $paymentId, $supplierId]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO supplier_payments (supplier_id, transaction_id, amount, payment_type, payment_date) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->execute([$supplierId, $payOpening ? null : $transactionId, $amount, $paymentType, $paymentDate]);
            }
            if (!$payOpening) {
                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS paid_total FROM supplier_payments WHERE transaction_id = ?");
                $sumStmt->execute([$transactionId]);
                $paidTotal = (float) (($sumStmt->fetch()['paid_total'] ?? 0));
                $total = (float) ($tx['total_amount'] ?? 0);
                $remaining = max(0, $total - $paidTotal);
                $status = $paymentStatus($total, $paidTotal);
                $updateTxStmt = $pdo->prepare("UPDATE supplier_transactions SET paid_amount = ?, remaining_amount = ?, payment_status = ? WHERE id = ?");
                $updateTxStmt->execute([$paidTotal, $remaining, $status, $transactionId]);
            }
            $rebuildSupplierLedger($pdo, $supplierId);
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_payment_saved'), ['finance' => true]);
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
            if ($transactionId > 0) {
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
            }
            $rebuildSupplierLedger($pdo, $supplierId);
            $pdo->commit();
            $jsonResponse(true, __('suppliers.msg_payment_deleted'), ['finance' => true]);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('suppliers.php POST error: ' . $e->getMessage());
        $jsonResponse(false, __('suppliers.err_operation_failed'));
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $jsonResponse(false, __('suppliers.err_unknown_action'));
    }
}

$pageAction = trim((string) ($_GET['action'] ?? ''));
if ($pageAction === 'view_ledger') {
    require __DIR__ . '/supplier_ledger_view.php';
    exit;
}

$initialPayload = $buildPayload($pdo);
$langQ = i18n_lang_query();
$totalReceivedAllTime = financial_total_received_all_time($pdo);
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

<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6" id="summaryCards">
    <a href="?module=cash<?= $langQ ?>" class="app-card supplier-card p-4 border border-teal-200 bg-gradient-to-br from-teal-50 to-white block no-underline text-inherit hover:border-teal-300 hover:shadow-md transition">
        <p class="text-xs font-semibold uppercase tracking-wide text-teal-800"><?= e(__('dashboard.kpi_total_received')) ?></p>
        <p id="suppTotalReceived" class="text-3xl font-bold text-teal-900 mt-2 tabular-nums break-all"><?= e(format_currency($totalReceivedAllTime)) ?></p>
        <p class="text-xs text-teal-700/90 mt-1"><?= e(__('dashboard.kpi_total_received_sub')) ?></p>
    </a>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" class="btn btn-primary w-full" data-open-modal="addSupplierModal">[+] Add New Supplier</button>
        <a href="?module=suppliers_list<?= i18n_lang_query() ?>" class="btn btn-soft text-center w-full">[📂] View Supplier List</a>
    </div>
</section>

<section class="app-card p-4 mb-6 fade-in">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.inventory_title')) ?></h3>
            <p class="text-xs text-slate-500"><?= e(__('suppliers.inventory_hint')) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800"><?= e(__('suppliers.stock_daka')) ?>: <strong id="stockDaka"><?= (int) ($initialPayload['stockTotals']['daka'] ?? 0) ?></strong></span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-900"><?= e(__('suppliers.stock_tash')) ?>: <strong id="stockTash"><?= (int) ($initialPayload['stockTotals']['tash'] ?? 0) ?></strong></span>
            </div>
            <button type="button" class="btn btn-soft btn-sm" data-open-modal="stockSetupModal"><?= e(__('suppliers.btn_setup_stock')) ?></button>
        </div>
    </div>
</section>

<section class="grid grid-cols-1 gap-5 mb-6">
    <div class="app-card p-4 slide-up shadow-sm rounded-xl">
        <h3 class="font-semibold text-oxygenDeep mb-3"><?= e(__('suppliers.refill_dispatch_title')) ?></h3>
        <div id="purchaseFormAlert" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 mb-3" role="alert"></div>
        <form id="purchaseForm" class="space-y-4" novalidate>
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="purchase_action" value="record_purchase">
            <input type="hidden" name="purchase_id" value="">
            <input type="hidden" name="total_received" value="0" id="purchaseTotalReceived">
            <input type="hidden" name="cylinder_type" id="purchaseCylinderTypeHidden" value="<?= e(standard_cylinder_size()) ?>">
            <p class="text-xs text-slate-500 hidden" id="totalSentAllTimeLine"></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_supplier')) ?></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="supplier_id" class="app-input flex-1 min-w-[160px]" id="purchaseSupplier"></select>
                        <span id="supplierPendingBadge" class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-900 cursor-help" title=""><?= e(__('suppliers.badge_history')) ?></span>
                    </div>
                </label>
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_date_sent')) ?></span>
                    <input name="date_sent" type="date" class="app-input" value="<?= date('Y-m-d') ?>">
                </label>
            </div>
            <p class="text-xs text-slate-500 mb-1"><?= e(__('suppliers.dispatch_trip_hint')) ?></p>
            <div class="grid grid-cols-1 gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 p-3">
                <label class="block">
                    <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.col_sent_qty')) ?> <span class="text-slate-400 font-normal">(<?= e(__('suppliers.dispatch_tash_hint')) ?>)</span></span>
                    <input name="sent_qty" type="number" min="0" value="0" class="app-input" id="dispatchSentQty">
                </label>
            </div>
            <div class="rounded-lg border border-sky-200 bg-sky-50/80 px-3 py-2 text-xs text-sky-950">
                <span class="font-semibold text-sky-900"><?= e(__('suppliers.pending_bakee_title')) ?></span>
                <span id="previousPendingLine" class="ms-1"><?= e(__('suppliers.pending_select_supplier')) ?></span>
            </div>

            <div class="rounded-xl border border-slate-200 p-3 bg-slate-50">
                <h4 class="font-semibold text-oxygenDeep mb-2"><?= e(__('suppliers.reconciliation_title')) ?></h4>
                <p class="text-xs text-slate-500 mb-3"><?= e(__('suppliers.reconciliation_hint')) ?></p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_receipt_date')) ?></span>
                        <input name="transaction_date" type="date" class="app-input" value="<?= date('Y-m-d') ?>">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_daka_added')) ?></span>
                        <input name="inventory_qty_preview" readonly class="app-input bg-slate-100" placeholder="0">
                    </label>
                </div>
                <div id="refillBreakdownBody" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.col_received_qty')) ?> <span class="text-slate-400 font-normal">(<?= e(__('suppliers.receipt_daka_hint')) ?>)</span></span>
                        <input name="recv_qty" type="number" min="1" value="" class="app-input recv-qty" id="recvQty" placeholder="0">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.col_total_price')) ?></span>
                        <input name="recv_total_price" type="number" step="0.01" min="0" class="app-input recv-total" id="recvTotalPrice" placeholder="<?= e(__('suppliers.ph_total_price_required')) ?>">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.col_pending_supplier')) ?></span>
                        <input type="text" readonly class="app-input bg-slate-100 recv-pending" id="recvPending" value="0">
                    </label>
                </div>
                <p class="mt-2 text-end text-sm font-medium text-slate-800"><?= e(__('suppliers.col_line_total')) ?>: <span class="recv-line-total" id="recvLineTotal"><?= e(__('currency.symbol')) ?> 0.00</span></p>
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
                    <select name="payment_type" class="app-input">
                        <option value="Cash"><?= e(__('suppliers.pay_cash')) ?></option>
                        <option value="Bank"><?= e(__('suppliers.pay_bank')) ?></option>
                        <option value="Credit"><?= e(__('suppliers.pay_credit')) ?></option>
                    </select>
                </label>
            </div>
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_transaction_description')) ?></span>
                <textarea name="transaction_notes" rows="2" class="app-input" placeholder="<?= e(__('suppliers.ph_transaction_description')) ?>"></textarea>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" id="purchaseSubmitBtn"><?= e(__('suppliers.btn_save_receipt')) ?></button>
                <button type="button" class="btn btn-soft hidden" id="purchaseResetBtn"><?= e(__('suppliers.btn_cancel_edit')) ?></button>
            </div>
        </form>
    </div>
</section>

<div id="stockSetupModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-close-modal="stockSetupModal"></div>
    <div class="relative w-full max-w-md app-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-oxygenDeep"><?= e(__('suppliers.stock_setup_title')) ?></h3>
            <button type="button" class="btn btn-soft" data-close-modal="stockSetupModal"><?= e(__('common.close')) ?></button>
        </div>
        <p class="text-xs text-slate-500 mb-4"><?= e(__('suppliers.stock_setup_hint')) ?></p>
        <form id="stockSetupForm" class="space-y-3" novalidate>
            <?php if (i18n_locale() === 'ps'): ?><input type="hidden" name="lang" value="ps"><?php endif; ?>
            <input type="hidden" name="action" value="adjust_cylinder_stock">
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_stock_mode')) ?></span>
                <select name="stock_mode" class="app-input" id="stockModeSelect">
                    <option value="set"><?= e(__('suppliers.stock_mode_set')) ?></option>
                    <option value="add"><?= e(__('suppliers.stock_mode_add')) ?></option>
                </select>
            </label>
            <input type="hidden" name="cylinder_type" value="<?= e(standard_cylinder_size()) ?>">
            <p class="text-xs text-slate-600 rounded-lg bg-slate-50 px-3 py-2" id="stockCurrentLine">—</p>
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_stock_daka_qty')) ?></span>
                <input name="daka_qty" type="number" min="0" step="1" class="app-input" id="stockDakaInput" placeholder="0">
            </label>
            <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1"><?= e(__('suppliers.label_stock_tash_qty')) ?></span>
                <input name="tash_qty" type="number" min="0" step="1" class="app-input" id="stockTashInput" placeholder="0">
            </label>
            <button type="submit" class="btn btn-primary w-full" id="stockSetupSubmitBtn"><?= e(__('suppliers.btn_apply_stock')) ?></button>
        </form>
    </div>
</div>

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
            <label class="block">
                <span class="text-xs font-medium text-slate-600"><?= e(__('suppliers.opening_balance')) ?></span>
                <input name="opening_balance" type="number" step="0.01" min="0" class="app-input mt-1" placeholder="<?= e(__('suppliers.ph_opening_balance')) ?>">
                <span class="text-xs text-slate-500 mt-1 block"><?= e(__('suppliers.opening_balance_hint')) ?></span>
            </label>
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
        <h4 class="text-sm font-semibold mb-2 text-oxygenDeep"><?= e(__('suppliers.modal_bills_title')) ?></h4>
        <p class="text-xs text-slate-500 mb-2"><?= e(__('suppliers.modal_bills_hint')) ?></p>
        <div id="historyBillsList" class="space-y-3 mb-4 max-h-[min(52vh,520px)] overflow-y-auto pe-1"></div>
        <div id="historyPendingBakee" class="mb-4 rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-3 text-sm text-amber-950">
            <span class="font-semibold"><?= e(__('suppliers.pending_at_supplier_bakee')) ?></span>
            <p class="mt-1 text-xs sm:text-sm" id="historyPendingBakeeBody">—</p>
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
    'cylinderStandard' => cylinder_size_label(standard_cylinder_size()),
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
    'colReceivedQty' => __('suppliers.col_received_qty'),
    'colBillTotal' => __('suppliers.col_total_price'),
    'colPaid' => __('suppliers.col_paid'),
    'colRemaining' => __('suppliers.label_remaining_amount'),
    'colPaymentType' => __('suppliers.label_payment_type'),
    'colDescription' => __('suppliers.col_description'),
    'billRef' => __('suppliers.col_purchase_ref'),
    'noBills' => __('suppliers.no_purchase_history'),
    'errSelectSupplier' => __('suppliers.err_select_supplier'),
    'errRecvQty' => __('suppliers.err_recv_qty_required'),
    'errTotalPrice' => __('suppliers.err_total_price_required'),
    'errRequestFailed' => __('suppliers.err_request_failed'),
    'emptyNoSuppliers' => __('suppliers.empty_no_suppliers'),
    'stockCurrentLine' => __('suppliers.stock_current_line'),
    'errStockQty' => __('suppliers.err_stock_qty_required'),
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
    const purchaseFormAlert = document.getElementById('purchaseFormAlert');
    const showToast = (message) => {
        if (!toast || !message) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 2200);
    };
    const showPurchaseAlert = (message) => {
        if (!message) {
            if (purchaseFormAlert) {
                purchaseFormAlert.textContent = '';
                purchaseFormAlert.classList.add('hidden');
            }
            return;
        }
        showToast(message);
        if (purchaseFormAlert) {
            purchaseFormAlert.textContent = message;
            purchaseFormAlert.classList.remove('hidden');
            purchaseFormAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };
    const request = async (body) => {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body
            });
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                console.error('Invalid JSON response', text);
                return { ok: false, message: t.errRequestFailed };
            }
        } catch (networkErr) {
            console.error(networkErr);
            return { ok: false, message: t.errRequestFailed };
        }
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
    const STANDARD_SIZE = <?= json_encode(standard_cylinder_size(), JSON_UNESCAPED_UNICODE) ?>;
    let editPurchaseRow = null;

    const purchaseActionField = () => purchaseForm?.querySelector('input[name="purchase_action"]');
    const setPurchaseAction = (value) => {
        const field = purchaseActionField();
        if (field) field.value = value;
    };

    const hasSuppliers = () => (currentData.supplierOptions || []).length > 0;

    const updatePurchaseEmptyState = () => {
        const empty = !hasSuppliers();
        if (purchaseSubmitBtn) {
            purchaseSubmitBtn.disabled = empty;
            purchaseSubmitBtn.title = empty ? (t.emptyNoSuppliers || '') : '';
        }
        if (purchaseSupplier) {
            purchaseSupplier.disabled = empty;
        }
    };

    const validatePurchaseForm = () => {
        showPurchaseAlert('');
        if (!purchaseForm || !purchaseSupplier) {
            showPurchaseAlert(t.errRequestFailed);
            return false;
        }
        if (!hasSuppliers()) {
            showPurchaseAlert(t.emptyNoSuppliers || t.errSelectSupplier);
            return false;
        }
        const supplierId = Number(purchaseSupplier.value || 0);
        if (supplierId <= 0) {
            showPurchaseAlert(t.errSelectSupplier);
            purchaseSupplier.focus();
            return false;
        }
        const recvQty = Number(purchaseForm.elements.recv_qty?.value || purchaseForm.recv_qty?.value || 0);
        if (recvQty <= 0) {
            showPurchaseAlert(t.errRecvQty);
            (purchaseForm.elements.recv_qty || purchaseForm.recv_qty)?.focus();
            return false;
        }
        const totalPrice = Number(purchaseForm.elements.recv_total_price?.value || purchaseForm.recv_total_price?.value || 0);
        if (totalPrice <= 0) {
            showPurchaseAlert(t.errTotalPrice);
            (purchaseForm.elements.recv_total_price || purchaseForm.recv_total_price)?.focus();
            return false;
        }
        return true;
    };

    const savePurchase = async () => {
        if (!purchaseForm) {
            showPurchaseAlert(t.errRequestFailed);
            return;
        }
        if (!validatePurchaseForm()) return;
        calcPurchase();
        const body = new FormData(purchaseForm);
        if (!body.get('purchase_action')) {
            body.set('purchase_action', purchaseActionField()?.value || 'record_purchase');
        }
        if (purchaseSubmitBtn) purchaseSubmitBtn.disabled = true;
        const result = await request(body);
        updatePurchaseEmptyState();
        showToast(result.message || t.saved);
        if (!result.ok) {
            if (result.message) showPurchaseAlert(result.message);
            return;
        }
        showPurchaseAlert('');
        editPurchaseRow = null;
        purchaseForm.reset();
        setPurchaseAction('record_purchase');
        const purchaseIdField = purchaseForm.elements.purchase_id || purchaseForm.purchase_id;
        if (purchaseIdField) purchaseIdField.value = '';
        if (purchaseSubmitBtn) purchaseSubmitBtn.textContent = t.saveReceipt;
        if (purchaseResetBtn) purchaseResetBtn.classList.add('hidden');
        const txDate = purchaseForm.elements.transaction_date || purchaseForm.transaction_date;
        const sentDate = purchaseForm.elements.date_sent || purchaseForm.date_sent;
        if (txDate) txDate.value = '<?= date('Y-m-d') ?>';
        if (sentDate) sentDate.value = '<?= date('Y-m-d') ?>';
        resetDispatchRows();
        resetRecvRows();
        const trEl = document.getElementById('purchaseTotalReceived');
        if (trEl) trEl.value = '0';
        calcPurchase();
        currentData = result.data;
        renderAll();
        refreshSupplierContextUI();
        if (window.OxygenFinance?.notify) {
            window.OxygenFinance.notify({ source: 'supplier_purchase' });
        }
    };

    const sizeLabel = () => t.cylinderStandard || 'Cylinder';
    const mapPayStatus = (s) => (s === 'PAID' || s === 'Paid' ? t.statusPaid : s === 'PARTIAL' || s === 'Partial' ? t.statusPartial : s === 'DUE' || s === 'Due' ? t.statusDue : s);

    const pendingTotal = (entry) => {
        if (!entry) return 0;
        return Number(entry.total ?? 0);
    };

    const getPendingEntry = (supplierId) => {
        const map = currentData.pendingBySupplier || {};
        return map[String(supplierId)] || map[supplierId] || { total: 0 };
    };

    const parseSentQtyFromPurchaseRow = (row) => {
        if (row == null) return 0;
        const s = Number(row.sent_qty_small ?? 0);
        const m = Number(row.sent_qty_medium ?? 0);
        const l = Number(row.sent_qty_large ?? 0);
        if (s + m + l > 0) return s + m + l;
        return Number(row.sent_quantity || 0);
    };

    const parseRecvQtyFromPurchaseRow = (row) => {
        let total = 0;
        const raw = row?.breakdown_data || '';
        if (!raw) return total;
        String(raw).split('||').forEach((entry) => {
            const p = entry.split('::');
            if (p.length >= 6) {
                total += Number(p[2] || 0);
            } else if (p.length >= 3) {
                total += Number(p[1] || 0);
            }
        });
        return total;
    };

    const openingPending = (supplierId, editRow) => {
        const live = pendingTotal(getPendingEntry(supplierId));
        if (!editRow || !editRow.id) return live;
        const sent = parseSentQtyFromPurchaseRow(editRow);
        const recv = parseRecvQtyFromPurchaseRow(editRow);
        return live - (sent - recv);
    };

    const parseBreakdownForForm = (row) => {
        let qty = 0;
        let pressure = '';
        const raw = row?.breakdown_data || '';
        if (raw) {
            String(raw).split('||').forEach((entry) => {
                const p = entry.split('::');
                if (p.length >= 6) {
                    qty += Number(p[2] || 0);
                    if (!pressure && p[3] !== undefined && p[3] !== '' && Number(p[3]) !== 0) pressure = String(p[3]);
                } else if (p.length >= 3) {
                    qty += Number(p[1] || 0);
                }
            });
        }
        const total = Number(row?.total_amount || 0);
        return { qty, pressure, total };
    };

    const sentPressureFromRow = (row) => {
        const keys = ['sent_pressure_small', 'sent_pressure_medium', 'sent_pressure_large', 'sent_pressure'];
        for (const k of keys) {
            const v = row?.[k];
            if (v != null && v !== '') return String(v);
        }
        return '';
    };

    const renderSummary = () => {
        const summary = currentData.summary || {};
        const stockTotals = currentData.stockTotals || {};
        const setKpi = (key, value) => {
            const el = document.querySelector(`#summaryCards [data-key="${key}"]`);
            if (el) el.textContent = value;
        };
        setKpi('suppliers', String(summary.suppliers ?? 0));
        setKpi('purchases', fmt(summary.purchases ?? 0));
        setKpi('pending', fmt(summary.pending ?? 0));
        setKpi('paid', fmt(summary.paid ?? 0));
        const stockDakaEl = document.getElementById('stockDaka');
        const stockTashEl = document.getElementById('stockTash');
        if (stockDakaEl) stockDakaEl.textContent = String(Number(stockTotals.daka || 0));
        if (stockTashEl) stockTashEl.textContent = String(Number(stockTotals.tash || 0));
    };

    const renderTypedStock = () => {};

    const renderSupplierOptions = () => {
        const html = [`<option value="">${escapeHtml(t.selectSupplier)}</option>`]
            .concat((currentData.supplierOptions || []).map((s) => `<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`));
        if (purchaseSupplier) purchaseSupplier.innerHTML = html.join('');
        const filterHtml = [`<option value="0">${escapeHtml(t.allSuppliers)}</option>`]
            .concat((currentData.supplierOptions || []).map((s) => `<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`));
        if (supplierFilter) supplierFilter.innerHTML = filterHtml.join('');
        if (paymentSupplierFilter) paymentSupplierFilter.innerHTML = filterHtml.join('');
        updatePurchaseEmptyState();
        refreshSupplierContextUI();
    };

    const refreshSupplierContextUI = () => {
        if (!purchaseSupplier) return;
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
                const sum = pendingTotal(p);
                const opening = openingPending(sid, editPurchaseRow);
                supplierPendingBadge.textContent = `${t.bakeePrefix} ${sum}`;
                supplierPendingBadge.title = `${t.bakeeTitleDetail} ${sum}.`;
                previousPendingLine.textContent = `${t.pendingIntro} — ${opening}`;
            }
        }
        calcPurchase();
    };

    const renderSuppliers = () => {
        if (!suppliersTbody) return;
        const supplierRows = currentData.suppliers || [];
        if (!supplierRows.length) {
            suppliersTbody.innerHTML = `<tr><td class="p-3 text-slate-500" colspan="7">${escapeHtml(t.noSuppliers)}</td></tr>`;
            return;
        }
        suppliersTbody.innerHTML = supplierRows.map((row) => {
            const balance = Number(row.outstanding ?? Math.max(0, Number(row.opening_balance || 0) + Number(row.purchases || 0) - Number(row.paid || 0)));
            return `<tr class="border-t border-slate-100 fade-in">
                <td class="p-3">${escapeHtml(row.name)}</td>
                <td class="p-3">${escapeHtml(row.contact_person || '-')}</td>
                <td class="p-3">${escapeHtml(row.phone || '-')}</td>
                <td class="p-3">${fmt(row.purchases)}</td>
                <td class="p-3">${fmt(row.paid)}</td>
                <td class="p-3 ${balance > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600'}">${fmt(balance)}</td>
                <td class="p-3">
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-soft" href="?module=suppliers&action=view_ledger&id=${Number(row.id)}<?= i18n_lang_query() ?>">${escapeHtml(t.viewLedger)}</a>
                        <button type="button" class="btn btn-soft supplier-delete-btn" data-id="${Number(row.id)}">${escapeHtml(t.delete)}</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    };

    const renderPurchases = () => {
        if (!purchaseTbody) return;
        const purchaseRows = currentData.purchases || [];
        if (!purchaseRows.length) {
            purchaseTbody.innerHTML = `<tr><td class="p-3 text-slate-500" colspan="10">${escapeHtml(t.noPurchases)}</td></tr>`;
            return;
        }
        purchaseTbody.innerHTML = purchaseRows.map((row) => `<tr class="border-t border-slate-100 fade-in">
            <td class="p-3">${escapeHtml(row.transaction_date)}</td>
            <td class="p-3">${escapeHtml(row.supplier_name)}</td>
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
        const periodPaidEl = document.getElementById('periodPaidAmount');
        const periodOutstandingEl = document.getElementById('periodOutstandingAmount');
        if (periodPaidEl) periodPaidEl.textContent = fmt((currentData.paymentSummary || {}).paid || 0);
        if (periodOutstandingEl) periodOutstandingEl.textContent = fmt((currentData.paymentSummary || {}).outstanding || 0);
        if (!paymentHistoryBody) return;
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
        if (el) el.value = STANDARD_SIZE;
    };

    const resetDispatchRows = () => {
        if (!purchaseForm) return;
        const sentField = purchaseForm.elements.sent_qty || purchaseForm.sent_qty;
        if (sentField) sentField.value = '0';
        syncHiddenCylinderType();
    };

    const resetRecvRows = () => {
        if (!purchaseForm) return;
        const qtyField = purchaseForm.elements.recv_qty || purchaseForm.recv_qty;
        const priceField = purchaseForm.elements.recv_total_price || purchaseForm.recv_total_price;
        if (qtyField) qtyField.value = '';
        if (priceField) priceField.value = '';
    };

    const renderAll = () => {
        try {
            renderSummary();
            renderSupplierOptions();
            renderSuppliers();
            renderPurchases();
            renderPaymentHistory();
        } catch (err) {
            console.error('Suppliers UI render failed', err);
        }
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
        const billsList = document.getElementById('historyBillsList');
        const paymentBody = document.getElementById('historyPaymentBody');
        const historyRows = result.history || [];
        const paymentRows = result.payments || [];
        const remainingByPaymentId = buildRemainingByPayment(historyRows, paymentRows);
        const pb = result.pendingBakee || { total: 0 };
        const pendingLine = (t.dueRemainingTpl || '')
            .replace(/\{t\}/g, String(Number(pb.total || 0)));
        const pendingEl = document.getElementById('historyPendingBakeeBody');
        if (pendingEl) pendingEl.innerHTML = pendingLine;
        const billStatusLabel = (row) => {
            const rem = Number(row.remaining_amount || 0);
            const paid = Number(row.paid_amount || 0);
            if (rem <= 0.00001) return t.statusPaid;
            if (paid > 0) return t.statusPartial;
            return t.statusDue;
        };
        const renderBillCard = (row) => {
            const txId = Number(row.id || 0);
            const notes = String(row.display_notes || row.notes || '').trim();
            const recvQty = Number(row.received_qty ?? row.total_received ?? row.quantity ?? 0);
            const payType = escapeHtml(row.display_payment_type || row.payment_type || '—');
            const statusLbl = billStatusLabel(row);
            const notesBlock = notes
                ? `<p class="mt-3 text-sm text-slate-700 border-t border-slate-100 pt-2"><span class="text-slate-500 text-xs block mb-0.5">${escapeHtml(t.colDescription)}</span>${escapeHtml(notes)}</p>`
                : '';
            return `<article class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <h5 class="font-semibold text-oxygenDeep">SP-${txId}</h5>
                        <p class="text-xs text-slate-500">${escapeHtml(row.transaction_date || '')}</p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs ${statusClass(row.payment_status)}">${escapeHtml(statusLbl)}</span>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-3 gap-y-2 text-sm">
                    <div><dt class="text-slate-500 text-xs">${escapeHtml(t.colReceivedQty)}</dt><dd class="font-medium tabular-nums">${recvQty}</dd></div>
                    <div><dt class="text-slate-500 text-xs">${escapeHtml(t.colBillTotal)}</dt><dd class="font-medium tabular-nums">${escapeHtml(row.display_total_price || fmtRs(row.total_amount || 0))}</dd></div>
                    <div><dt class="text-slate-500 text-xs">${escapeHtml(t.colPaid)}</dt><dd class="font-medium tabular-nums">${fmtRs(row.paid_amount || 0)}</dd></div>
                    <div><dt class="text-slate-500 text-xs">${escapeHtml(t.colRemaining)}</dt><dd class="font-medium tabular-nums ${Number(row.remaining_amount || 0) > 0 ? 'text-amber-600' : 'text-emerald-600'}">${fmtRs(row.remaining_amount || 0)}</dd></div>
                    <div class="col-span-2 sm:col-span-1"><dt class="text-slate-500 text-xs">${escapeHtml(t.colPaymentType)}</dt><dd class="font-medium">${payType}</dd></div>
                </dl>
                ${notesBlock}
            </article>`;
        };
        if (billsList) {
            billsList.innerHTML = historyRows.length
                ? historyRows.map(renderBillCard).join('')
                : `<p class="text-sm text-slate-500 py-4 text-center">${escapeHtml(t.noBills)}</p>`;
        }
        if (paymentBody) paymentBody.innerHTML = paymentRows.length ? paymentRows.map((row) => `<tr class="border-t border-slate-100">
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
        if (!ledgerPaymentForm || !historyModal) return;
        const duePurchases = historyRows.filter((row) => Number(row.remaining_amount || 0) > 0);
        const txOptions = [`<option value="">${escapeHtml(t.selectPurchase)}</option>`].concat(
            duePurchases.map((row) => `<option value="${Number(row.id)}">SP-${Number(row.id)} | ${escapeHtml(row.transaction_date)} | ${escapeHtml(t.lblDue)}: ${fmtRs(row.total_amount)} · ${escapeHtml(t.lblRem)}: ${fmtRs(row.remaining_amount)}</option>`),
        );
        if (ledgerPaymentForm.transaction_id) ledgerPaymentForm.transaction_id.innerHTML = txOptions.join('');
        if (ledgerPaymentForm.payment_id) ledgerPaymentForm.payment_id.value = '';
        if (ledgerPaymentForm.supplier_id) ledgerPaymentForm.supplier_id.value = String(supplierId || 0);
        if (ledgerPaymentForm.payment_date) ledgerPaymentForm.payment_date.value = '<?= date('Y-m-d') ?>';
        if (ledgerPaymentForm.amount) ledgerPaymentForm.amount.value = '';
        if (ledgerPaymentForm.payment_type) ledgerPaymentForm.payment_type.value = 'Cash';
        if (ledgerPaymentSubmitBtn) ledgerPaymentSubmitBtn.textContent = t.savePayment;
        if (ledgerPaymentResetBtn) ledgerPaymentResetBtn.classList.add('hidden');
        historyModal.classList.remove('hidden');
        historyModal.classList.add('flex');
    };

    const calcPurchase = () => {
        if (!purchaseForm || !purchaseSupplier) return;
        syncHiddenCylinderType();
        const sid = Number(purchaseSupplier.value || 0);
        const sentHere = Number((purchaseForm.elements.sent_qty || purchaseForm.sent_qty)?.value || 0);
        const paid = Number((purchaseForm.elements.paid_amount || purchaseForm.paid_amount)?.value || 0);
        const qty = Number((purchaseForm.elements.recv_qty || purchaseForm.recv_qty)?.value || 0);
        const total = Math.max(0, Number((purchaseForm.elements.recv_total_price || purchaseForm.recv_total_price)?.value || 0));
        const pendIn = openingPending(sid, editPurchaseRow);
        const pendingAfter = pendIn + sentHere - qty;
        const pendField = document.getElementById('recvPending');
        if (pendField) pendField.value = String(Math.max(0, pendingAfter));
        const lineSpan = document.getElementById('recvLineTotal');
        if (lineSpan) lineSpan.textContent = fmt(total);
        const trEl = document.getElementById('purchaseTotalReceived');
        if (trEl) trEl.value = String(Math.max(0, Math.round(qty)));
        if (purchaseForm.inventory_qty_preview) {
            purchaseForm.inventory_qty_preview.value = qty > 0 ? String(Math.round(qty)) : '0';
        }
        const remaining = Math.max(0, total - paid);
        if (purchaseForm.total_amount_preview) purchaseForm.total_amount_preview.value = total > 0 ? fmt(total) : '';
        if (purchaseForm.remaining_amount_preview) purchaseForm.remaining_amount_preview.value = total > 0 ? fmt(remaining) : '';
    };

    if (purchaseForm) {
        ['paid_amount', 'sent_qty', 'recv_qty', 'recv_total_price'].forEach((name) => {
            const field = purchaseForm.elements[name] || purchaseForm[name];
            if (!field) return;
            field.addEventListener('input', calcPurchase);
        });
        purchaseForm.addEventListener('submit', (event) => {
            event.preventDefault();
            savePurchase();
        });
    }
    if (purchaseSubmitBtn) {
        purchaseSubmitBtn.addEventListener('click', (event) => {
            event.preventDefault();
            savePurchase();
        });
    }
    if (purchaseSupplier) {
        purchaseSupplier.addEventListener('change', refreshSupplierContextUI);
    }

    const standardStockCounts = () => {
        const totals = currentData.stockTotals || {};
        return {
            daka: Number(totals.daka ?? 0),
            tash: Number(totals.tash ?? 0),
        };
    };

    const refreshStockCurrentLine = () => {
        const lineEl = document.getElementById('stockCurrentLine');
        if (!lineEl) return;
        const row = standardStockCounts();
        lineEl.textContent = (t.stockCurrentLine || '')
            .replace(/\{daka\}/g, String(row.daka))
            .replace(/\{tash\}/g, String(row.tash));
    };

    const syncStockFormPrefill = () => {
        const modeEl = document.getElementById('stockModeSelect');
        const dakaIn = document.getElementById('stockDakaInput');
        const tashIn = document.getElementById('stockTashInput');
        if (!modeEl || modeEl.value !== 'set') return;
        const row = standardStockCounts();
        if (dakaIn) dakaIn.value = String(row.daka);
        if (tashIn) tashIn.value = String(row.tash);
    };

    const stockSetupForm = document.getElementById('stockSetupForm');
    const stockModeSelect = document.getElementById('stockModeSelect');
    if (stockModeSelect) {
        stockModeSelect.addEventListener('change', () => {
            if (stockModeSelect.value === 'set') {
                syncStockFormPrefill();
            }
        });
    }
    document.querySelectorAll('[data-open-modal="stockSetupModal"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            refreshStockCurrentLine();
            syncStockFormPrefill();
        });
    });
    if (stockSetupForm) {
        stockSetupForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const dakaQty = Number(stockSetupForm.elements.daka_qty?.value || 0);
            const tashQty = Number(stockSetupForm.elements.tash_qty?.value || 0);
            if (dakaQty <= 0 && tashQty <= 0) {
                showToast(t.errStockQty);
                return;
            }
            const submitBtn = document.getElementById('stockSetupSubmitBtn');
            if (submitBtn) submitBtn.disabled = true;
            const result = await request(new FormData(stockSetupForm));
            if (submitBtn) submitBtn.disabled = false;
            showToast(result.message || t.saved);
            if (!result.ok) return;
            currentData = result.data;
            renderAll();
            refreshStockCurrentLine();
            const modal = document.getElementById('stockSetupModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            if (stockModeSelect?.value === 'set') {
                syncStockFormPrefill();
            } else if (stockSetupForm.elements.daka_qty) {
                stockSetupForm.elements.daka_qty.value = '';
                if (stockSetupForm.elements.tash_qty) stockSetupForm.elements.tash_qty.value = '';
            }
        });
    }

    if (supplierForm) {
        supplierForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await request(new FormData(supplierForm));
            showToast(result.message || t.saved);
            if (!result.ok) return;
            supplierForm.reset();
            currentData = result.data;
            renderAll();
            const opts = currentData.supplierOptions || [];
            if (purchaseSupplier && opts.length > 0) {
                purchaseSupplier.value = String(opts[opts.length - 1].id);
            }
            showPurchaseAlert('');
            refreshSupplierContextUI();
        });
    }

    if (purchaseResetBtn && purchaseForm) {
        purchaseResetBtn.addEventListener('click', () => {
            editPurchaseRow = null;
            purchaseForm.reset();
            setPurchaseAction('record_purchase');
            const purchaseIdField = purchaseForm.elements.purchase_id || purchaseForm.purchase_id;
            if (purchaseIdField) purchaseIdField.value = '';
            if (purchaseSubmitBtn) purchaseSubmitBtn.textContent = t.saveReceipt;
            purchaseResetBtn.classList.add('hidden');
            const txDate = purchaseForm.elements.transaction_date || purchaseForm.transaction_date;
            const sentDate = purchaseForm.elements.date_sent || purchaseForm.date_sent;
            if (txDate) txDate.value = '<?= date('Y-m-d') ?>';
            if (sentDate) sentDate.value = '<?= date('Y-m-d') ?>';
            resetDispatchRows();
            resetRecvRows();
            const trEl = document.getElementById('purchaseTotalReceived');
            if (trEl) trEl.value = '0';
            calcPurchase();
            refreshSupplierContextUI();
        });
    }

    const applyFilterBtn = document.getElementById('applyFilterBtn');
    if (applyFilterBtn) applyFilterBtn.addEventListener('click', async () => {
        const body = new FormData();
        body.append('action', 'fetch_dashboard');
        body.append('search', document.getElementById('supplierSearch').value || '');
        body.append('from_date', document.getElementById('filterFromDate').value || '');
        body.append('to_date', document.getElementById('filterToDate').value || '');
        body.append('supplier_filter', supplierFilter?.value || '0');
        body.append('payment_period', paymentPeriodFilter?.value || 'weekly');
        body.append('payment_supplier_filter', paymentSupplierFilter?.value || '0');
        const result = await request(body);
        if (!result.ok) {
            showToast(result.message || t.filterFailed);
            return;
        }
        currentData = result.data;
        renderAll();
    });

    const applyPaymentFilterBtn = document.getElementById('applyPaymentFilterBtn');
    if (applyPaymentFilterBtn) applyPaymentFilterBtn.addEventListener('click', async () => {
        const body = new FormData();
        body.append('action', 'fetch_payment_history');
        body.append('payment_period', paymentPeriodFilter?.value || 'weekly');
        body.append('payment_supplier_filter', paymentSupplierFilter?.value || '0');
        const result = await request(body);
        if (!result.ok) {
            showToast(result.message || t.paymentHistoryFailed);
            return;
        }
        currentData.paymentHistory = result.paymentHistory || [];
        currentData.paymentSummary = result.paymentSummary || { period: 'weekly', paid: 0, outstanding: 0 };
        renderPaymentHistory();
    });

    const exportCsvBtn = document.getElementById('exportCsvBtn');
    if (exportCsvBtn) exportCsvBtn.addEventListener('click', () => {
        const params = new URLSearchParams({
            module: 'suppliers',
            action: 'export_purchases_csv',
            from_date: document.getElementById('filterFromDate').value || '',
            to_date: document.getElementById('filterToDate').value || '',
            supplier_filter: supplierFilter?.value || '0'
        });
        const qs = params.toString() + <?= json_encode(i18n_locale() === 'ps' ? '&lang=ps' : '', JSON_UNESCAPED_UNICODE) ?>;
        window.open(`?${qs}`, '_blank');
    });

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.classList.contains('purchase-edit-btn') && purchaseForm) {
            const payload = target.getAttribute('data-purchase') || '{}';
            const row = JSON.parse(payload);
            editPurchaseRow = row;
            setPurchaseAction('update_purchase');
            const purchaseIdEl = purchaseForm.elements.purchase_id || purchaseForm.purchase_id;
            const supplierIdEl = purchaseForm.elements.supplier_id || purchaseForm.supplier_id;
            if (purchaseIdEl) purchaseIdEl.value = String(row.id || '');
            if (supplierIdEl) supplierIdEl.value = String(row.supplier_id || '');
            if (purchaseForm.sent_qty) purchaseForm.sent_qty.value = String(parseSentQtyFromPurchaseRow(row));
            syncHiddenCylinderType();
            purchaseForm.date_sent.value = row.date_sent || '<?= date('Y-m-d') ?>';
            purchaseForm.transaction_date.value = row.transaction_date || '<?= date('Y-m-d') ?>';
            purchaseForm.paid_amount.value = String(row.paid_amount || 0);
            purchaseForm.payment_type.value = row.payment_type || 'Credit';
            if (purchaseForm.transaction_notes) {
                purchaseForm.transaction_notes.value = String(row.notes || row.display_notes || '').trim();
            }
            const parsed = parseBreakdownForForm(row);
            if (purchaseForm.recv_qty) purchaseForm.recv_qty.value = String(parsed.qty);
            if (purchaseForm.recv_total_price) purchaseForm.recv_total_price.value = String(parsed.total > 0 ? parsed.total : (row.total_amount || 0));
            const trEl = document.getElementById('purchaseTotalReceived');
            if (trEl) trEl.value = String(row.total_received || row.quantity || 0);
            if (purchaseSubmitBtn) purchaseSubmitBtn.textContent = t.updateReceipt;
            if (purchaseResetBtn) purchaseResetBtn.classList.remove('hidden');
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
            if (window.OxygenFinance?.notify) {
                window.OxygenFinance.notify({ source: 'supplier_purchase' });
            }
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
        if (target.classList.contains('history-payment-edit-btn') && ledgerPaymentForm) {
            if (ledgerPaymentForm.payment_id) ledgerPaymentForm.payment_id.value = target.dataset.id || '';
            const editTxId = Number(target.dataset.transactionId || 0);
            const txSelect = ledgerPaymentForm.transaction_id;
            if (!txSelect) return;
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
            if (ledgerPaymentForm.payment_date) ledgerPaymentForm.payment_date.value = target.dataset.date || '<?= date('Y-m-d') ?>';
            if (ledgerPaymentForm.amount) ledgerPaymentForm.amount.value = String(target.dataset.amount || '');
            if (ledgerPaymentForm.payment_type) ledgerPaymentForm.payment_type.value = target.dataset.paymentType || 'Cash';
            if (ledgerPaymentSubmitBtn) ledgerPaymentSubmitBtn.textContent = t.updatePayment;
            if (ledgerPaymentResetBtn) ledgerPaymentResetBtn.classList.remove('hidden');
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
        if (target.dataset.closeHistory === '1' && historyModal) {
            historyModal.classList.add('hidden');
            historyModal.classList.remove('flex');
        }
    });
    if (ledgerPaymentForm) ledgerPaymentForm.addEventListener('submit', async (event) => {
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
        if (result.finance && window.OxygenFinance?.notify) {
            window.OxygenFinance.notify({ source: 'supplier_payment' });
        }
        await loadSupplierHistory(supplierId);
    });

    if (ledgerPaymentResetBtn && ledgerPaymentForm) ledgerPaymentResetBtn.addEventListener('click', () => {
        if (ledgerPaymentForm.payment_id) ledgerPaymentForm.payment_id.value = '';
        if (ledgerPaymentForm.payment_date) ledgerPaymentForm.payment_date.value = '<?= date('Y-m-d') ?>';
        if (ledgerPaymentForm.amount) ledgerPaymentForm.amount.value = '';
        if (ledgerPaymentForm.payment_type) ledgerPaymentForm.payment_type.value = 'Cash';
        if (ledgerPaymentSubmitBtn) ledgerPaymentSubmitBtn.textContent = t.savePayment;
        ledgerPaymentResetBtn.classList.add('hidden');
    });

    renderAll();
    resetDispatchRows();
    resetRecvRows();
    editPurchaseRow = null;
    calcPurchase();
    refreshSupplierContextUI();
})();

(() => {
    const totalEl = document.getElementById('suppTotalReceived');
    if (!totalEl || !window.OxygenFinance?.onUpdated) return;
    window.OxygenFinance.onUpdated(async () => {
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'ledger');
            u.searchParams.set('ajax', 'finance_pulse');
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (data.ok && data.pulse?.total_received_all_time_formatted) {
                totalEl.textContent = data.pulse.total_received_all_time_formatted;
            }
        } catch (_) { /* ignore */ }
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.suppliers'), $content);
