<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class OxygenOpsService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
        $this->ensureSchema();
    }

    public function addServiceWithAutomation(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $serviceType = 'refill';
        $saleBasis = strtolower((string) ($data['sale_basis'] ?? 'quantity'));
        if (!in_array($saleBasis, ['quantity', 'psi'], true)) {
            $saleBasis = 'quantity';
        }
        $rows = is_array($data['cylinder_rows'] ?? null) ? $data['cylinder_rows'] : [];
        $serviceCharges = max(0, (float) ($data['service_charges'] ?? 0));
        $serviceDate = (string) ($data['date'] ?? date('Y-m-d'));
        $description = trim((string) ($data['description'] ?? 'Cylinder exchange & gas refill'));
        if ($customerId <= 0) {
            throw new RuntimeException('Valid customer is required.');
        }
        if (!$rows) {
            throw new RuntimeException('At least one cylinder row is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $normalizedRows = [];
            $totalSent = 0;
            $totalReceived = 0;
            $lineTotal = 0.0;
            $runningCylinderSigned = $this->customerCylinderSignedNet($customerId);
            foreach ($rows as $row) {
                $size = normalize_cylinder_type((string) ($row['size'] ?? ''));
                $sent = max(0, (int) ($row['sent'] ?? 0));
                $received = max(0, (int) ($row['received'] ?? 0));
                $rate = max(0, (float) ($row['rate'] ?? 0));
                $totalPrice = max(0, (float) ($row['total_price'] ?? 0));
                $rowBasis = $saleBasis;
                if ($sent <= 0 && $received <= 0) {
                    if (!($rowBasis === 'psi' && $totalPrice > 0)) {
                        continue;
                    }
                }
                $saleUnits = $sent > 0 ? $sent : $received;
                if ($rowBasis === 'psi') {
                    if ($totalPrice <= 0) {
                        throw new RuntimeException('Total price is required for PSI billing.');
                    }
                    $saleUnits = 1;
                }
                if ($rowBasis !== 'psi') {
                    $runningCylinderSigned += ($received - $sent);
                    $baqi = max(0, abs($runningCylinderSigned));
                } else {
                    $baqi = 0;
                }
                $rowAmount = $rowBasis === 'psi'
                    ? $totalPrice
                    : max(0, $totalPrice > 0 ? $totalPrice : $rate);
                $soldPressureTotal = 0.0;
                $normalizedRows[] = [
                    'size' => $size,
                    'sent' => $sent,
                    'received' => $received,
                    'sale_units' => $saleUnits,
                    'baqi' => $baqi,
                    'rate' => $rate,
                    'billing_basis' => $rowBasis,
                    'sale_pressure' => 0.0,
                    'pressure_added' => 0.0,
                    'sold_pressure_total' => $soldPressureTotal,
                    'total' => $rowAmount,
                ];
                $totalSent += $sent;
                $totalReceived += $received;
                $lineTotal += $rowAmount;
            }
            if (!$normalizedRows) {
                throw new RuntimeException('No valid cylinder rows were provided.');
            }
            $this->assertTypedStockAvailability($normalizedRows);
            $totalBill = $lineTotal + $serviceCharges;
            $previousBalance = $this->customerOutstandingBalance($customerId);
            $grandTotal = $totalBill + $previousBalance;
            $incomingPayment = max(0, (float) ($data['paid_amount'] ?? 0));

            $serviceStmt = $this->pdo->prepare(
                'INSERT INTO services (customer_id, service_type, quantity, price, total_bill, service_charges, previous_balance, grand_total, paid_amount, remaining_balance, date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $serviceStmt->execute([
                $customerId,
                $serviceType,
                $totalSent,
                $lineTotal,
                $totalBill,
                $serviceCharges,
                $previousBalance,
                $grandTotal,
                0,
                $grandTotal,
                $serviceDate,
                $description,
            ]);
            $serviceId = (int) $this->pdo->lastInsertId();

            $rowInsert = $this->pdo->prepare(
                'INSERT INTO service_cylinder_rows (service_id, cylinder_size, sent_qty, received_qty, sale_units, baqi_qty, rate, billing_basis, sale_pressure, sold_pressure_total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $netBaqiTotal = 0;
            foreach ($normalizedRows as $row) {
                $rowInsert->execute([
                    $serviceId,
                    $row['size'],
                    $row['sent'],
                    $row['received'],
                    $row['sale_units'],
                    $row['baqi'],
                    $row['rate'],
                    $row['billing_basis'],
                    $row['sale_pressure'],
                    $row['sold_pressure_total'],
                    $row['total'],
                ]);
                $this->applyCustomerCylinderBalance($customerId, $row['size'], $row['baqi']);
                $netBaqiTotal += (int) $row['baqi'];
            }
            $this->applyCustomerTotalCylinderBalance($customerId, $netBaqiTotal);

            $invoiceStmt = $this->pdo->prepare(
                'INSERT INTO invoices (customer_id, service_id, total_amount, paid_amount, remaining_amount, status) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $invoiceStatus = $remainingBalance <= 0.00001 ? 'Paid' : 'Pending';
            $invoiceStmt->execute([$customerId, $serviceId, $grandTotal, 0, $grandTotal, 'Pending']);
            $invoiceId = (int) $this->pdo->lastInsertId();
            $allocation = $this->applyPaymentAcrossOldestInvoices($customerId, $incomingPayment, $serviceDate);
            if ($allocation['unapplied_amount'] > 0.00001) {
                throw new RuntimeException('Payment exceeds customer pending balance.');
            }
            $invoiceState = $this->invoiceState($invoiceId);
            $paidAmount = (float) ($invoiceState['paid_amount'] ?? 0);
            $remainingBalance = (float) ($invoiceState['remaining_amount'] ?? $grandTotal);
            $invoiceStatus = (string) ($invoiceState['status'] ?? 'Pending');
            $appliedPaymentTotal = (float) ($allocation['applied_total'] ?? 0);
            $this->syncServiceFinancialsFromInvoice($invoiceId);

            $ledgerDescription = $description !== '' ? $description : "Order #{$serviceId}";
            $this->upsertLedger(
                $customerId,
                $totalBill,
                0,
                $serviceDate,
                [
                    'description' => $ledgerDescription,
                    'sent' => $totalSent,
                    'received' => $totalReceived,
                    'baqi' => $totalSent - $totalReceived,
                    'reference_type' => 'service',
                    'reference_id' => $serviceId,
                ]
            );
            if ($appliedPaymentTotal > 0) {
                $this->upsertLedger(
                    $customerId,
                    0,
                    $appliedPaymentTotal,
                    $serviceDate,
                    [
                        'description' => "Payment for Order #{$serviceId} - {$ledgerDescription}",
                        'sent' => 0,
                        'received' => 0,
                        'baqi' => 0,
                        'reference_type' => 'service',
                        'reference_id' => $serviceId,
                    ]
                );
            }
            $this->applyInventoryIssue($normalizedRows, $totalSent, $totalReceived);

            $this->pdo->commit();
            return [
                'service_id' => $serviceId,
                'invoice_id' => $invoiceId,
                'total_amount' => $grandTotal,
                'status' => $invoiceStatus,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Receive payment from customer: applies to oldest invoices first, then reduces opening / ledger receivable.
     */
    public function receiveCustomerPayment(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $amount = max(0, (float) ($data['amount'] ?? 0));
        $paymentDate = (string) ($data['payment_date'] ?? date('Y-m-d'));
        $note = trim((string) ($data['note'] ?? ''));
        if ($customerId <= 0) {
            throw new RuntimeException('Valid customer is required.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $receivable = customer_receivable_balance($this->pdo, $customerId);
        if ($amount > $receivable + 0.00001) {
            throw new RuntimeException('Payment exceeds customer receivable balance.');
        }

        $this->pdo->beginTransaction();
        try {
            $allocation = $this->applyPaymentAcrossOldestInvoices($customerId, $amount, $paymentDate);
            $appliedToInvoices = (float) ($allocation['applied_total'] ?? 0);
            $ledgerOnly = (float) ($allocation['unapplied_amount'] ?? 0);
            $lastPaymentId = (int) ($allocation['last_payment_id'] ?? 0);

            if ($appliedToInvoices > 0) {
                $invoiceDesc = $note !== '' ? $note : 'Payment received (invoices)';
                $this->upsertLedger(
                    $customerId,
                    0,
                    $appliedToInvoices,
                    $paymentDate,
                    [
                        'description' => $invoiceDesc,
                        'sent' => 0,
                        'received' => 0,
                        'baqi' => 0,
                        'reference_type' => 'payment',
                        'reference_id' => $lastPaymentId,
                    ]
                );
            }
            if ($ledgerOnly > 0.00001) {
                $openingDesc = $note !== '' ? $note : __('ledger.payment_opening_settlement');
                $this->upsertLedger(
                    $customerId,
                    0,
                    $ledgerOnly,
                    $paymentDate,
                    [
                        'description' => $openingDesc,
                        'sent' => 0,
                        'received' => 0,
                        'baqi' => 0,
                        'reference_type' => 'opening_payment',
                        'reference_id' => $customerId,
                    ]
                );
            }

            $this->pdo->commit();
            return [
                'applied_to_invoices' => $appliedToInvoices,
                'applied_to_opening' => $ledgerOnly,
                'payment_id' => $lastPaymentId,
                'receivable_after' => customer_receivable_balance($this->pdo, $customerId),
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException($e->getMessage());
        }
    }

    /** Record empty cylinders returned (reduces opening / baqi owed, no order required). */
    public function recordCustomerCylinderReturn(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $qty = max(0, (int) ($data['cylinders_returned'] ?? 0));
        $returnDate = (string) ($data['return_date'] ?? date('Y-m-d'));
        $note = trim((string) ($data['note'] ?? ''));
        if ($customerId <= 0) {
            throw new RuntimeException('Valid customer is required.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('Enter how many cylinders were returned.');
        }

        $owed = customer_cylinders_owed($this->pdo, $customerId);
        if ($qty > $owed) {
            throw new RuntimeException("Customer only owes {$owed} cylinder(s).");
        }

        $cylinderType = normalize_cylinder_type((string) ($data['cylinder_type'] ?? standard_cylinder_size()));
        $description = $note !== '' ? $note : __('ledger.cylinder_return_desc');

        $this->pdo->beginTransaction();
        try {
            $this->upsertLedger(
                $customerId,
                0,
                0,
                $returnDate,
                [
                    'description' => $description,
                    'sent' => 0,
                    'received' => $qty,
                    'baqi' => 0,
                    'reference_type' => 'cylinder_settlement',
                    'reference_id' => 0,
                ]
            );
            inventory_receive_empty_cylinders($this->pdo, $qty, $cylinderType);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException($e->getMessage());
        }

        $stock = cylinder_daka_tash_for_type($this->pdo, $cylinderType);

        return [
            'cylinders_returned' => $qty,
            'cylinders_owed_after' => customer_cylinders_owed($this->pdo, $customerId),
            'tash_stock' => (int) ($stock['tash'] ?? 0),
        ];
    }

    public function addPaymentWithAutomation(array $data): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $paymentDate = (string) ($data['payment_date'] ?? date('Y-m-d'));
        if ($invoiceId <= 0) {
            throw new RuntimeException('Valid invoice is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $invoiceStmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
            $invoiceStmt->execute([$invoiceId]);
            $invoice = $invoiceStmt->fetch();
            if (!$invoice) {
                throw new RuntimeException('Invoice not found.');
            }

            if ($amount <= 0) {
                throw new RuntimeException('Payment amount must be greater than zero.');
            }

            $allocation = $this->applyPaymentAcrossOldestInvoices((int) $invoice['customer_id'], $amount, $paymentDate);
            if ($allocation['applied_total'] <= 0) {
                throw new RuntimeException('No pending balance found for this customer.');
            }
            if ($allocation['unapplied_amount'] > 0.00001) {
                throw new RuntimeException('Payment exceeds customer pending balance.');
            }
            $appliedTotal = (float) ($allocation['applied_total'] ?? 0);
            if ($appliedTotal > 0) {
                $customerId = (int) ($invoice['customer_id'] ?? 0);
                if ($customerId > 0) {
                    // Keep ledger balance in sync so dashboard/customers/ledger statuses update consistently.
                    $this->upsertLedger(
                        $customerId,
                        0,
                        $appliedTotal,
                        $paymentDate,
                        [
                            'description' => "Payment received for INV-{$invoiceId}",
                            'sent' => 0,
                            'received' => 0,
                            'baqi' => 0,
                            'reference_type' => 'payment',
                            'reference_id' => (int) ($allocation['last_payment_id'] ?? 0),
                        ]
                    );
                }
            }

            $currentInvoice = $this->invoiceState($invoiceId);
            $remaining = (float) ($currentInvoice['remaining_amount'] ?? 0);
            $status = (string) ($currentInvoice['status'] ?? 'Pending');
            $paymentState = $remaining <= 0.00001 ? 'full' : 'partial';

            $this->pdo->commit();
            return [
                'payment_id' => (int) ($allocation['last_payment_id'] ?? 0),
                'invoice_id' => $invoiceId,
                'remaining_amount' => $remaining,
                'status' => $status,
                'payment_state' => $paymentState,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException($e->getMessage());
        }
    }

    public function deleteServiceWithAutomation(int $serviceId): void
    {
        if ($serviceId <= 0) {
            throw new RuntimeException('Invalid service id.');
        }
        $this->pdo->beginTransaction();
        try {
            $serviceStmt = $this->pdo->prepare('SELECT id, customer_id FROM services WHERE id = ? FOR UPDATE');
            $serviceStmt->execute([$serviceId]);
            $service = $serviceStmt->fetch();
            if (!$service) {
                throw new RuntimeException('Service not found.');
            }
            $rowStmt = $this->pdo->prepare('SELECT cylinder_size, sent_qty, received_qty, baqi_qty, sold_pressure_total FROM service_cylinder_rows WHERE service_id = ?');
            $rowStmt->execute([$serviceId]);
            $rows = $rowStmt->fetchAll();
            $totalSent = 0;
            $totalReceived = 0;
            foreach ($rows as $row) {
                $size = normalize_cylinder_type((string) ($row['cylinder_size'] ?? ''));
                $sent = (int) ($row['sent_qty'] ?? 0);
                $received = (int) ($row['received_qty'] ?? 0);
                $baqi = (int) ($row['baqi_qty'] ?? 0);
                $this->applyCustomerCylinderBalance((int) $service['customer_id'], $size, -1 * $baqi);
                $this->applyCustomerTotalCylinderBalance((int) $service['customer_id'], -1 * $baqi);
                $totalSent += $sent;
                $totalReceived += $received;
            }

            $this->reverseInventoryIssue($rows, $totalSent, $totalReceived);
            $this->pdo->prepare("DELETE FROM ledger WHERE reference_type = 'service' AND reference_id = ?")->execute([$serviceId]);

            $invoiceIdsStmt = $this->pdo->prepare('SELECT id FROM invoices WHERE service_id = ?');
            $invoiceIdsStmt->execute([$serviceId]);
            $invoiceIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $invoiceIdsStmt->fetchAll());
            if ($invoiceIds) {
                $deletePayments = $this->pdo->prepare('DELETE FROM payments WHERE invoice_id = ?');
                foreach ($invoiceIds as $invoiceId) {
                    $deletePayments->execute([$invoiceId]);
                }
            }
            $this->pdo->prepare('DELETE FROM invoices WHERE service_id = ?')->execute([$serviceId]);
            $this->pdo->prepare('DELETE FROM service_cylinder_rows WHERE service_id = ?')->execute([$serviceId]);
            $this->pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$serviceId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException($e->getMessage());
        }
    }

    private function applyInventoryIssue(array $normalizedRows, int $sentQty, int $emptyReceived): void
    {
        foreach ($normalizedRows as $row) {
            $rowSent = max(0, (int) ($row['sent'] ?? 0));
            $rowReceived = max(0, (int) ($row['received'] ?? 0));
            $size = normalize_cylinder_type((string) ($row['size'] ?? ''));
            if ($rowSent <= 0 && $rowReceived <= 0) {
                continue;
            }
            // Daka (full) out, Tash (empty) in — syncs cylinder_stock_by_type.tash_qty and cylinders.empty
            adjust_cylinder_daka_tash($this->pdo, $size, -$rowSent, $rowReceived);
        }

        $row = $this->pdo->query('SELECT * FROM cylinders ORDER BY id ASC LIMIT 1 FOR UPDATE')->fetch();
        if (!$row) {
            $insert = $this->pdo->prepare('INSERT INTO cylinders (total, available, issued, empty) VALUES (0, 0, 0, 0)');
            $insert->execute();
            $row = $this->pdo->query('SELECT * FROM cylinders ORDER BY id ASC LIMIT 1 FOR UPDATE')->fetch();
        }
        $issued = (int) $row['issued'] + $sentQty;
        $update = $this->pdo->prepare('UPDATE cylinders SET issued = ? WHERE id = ?');
        $update->execute([$issued, (int) $row['id']]);
    }

    private function assertTypedStockAvailability(array $normalizedRows): void
    {
        $requiredQty = 0;
        $requiredPressure = 0.0;
        foreach ($normalizedRows as $row) {
            $requiredQty += max(0, (int) ($row['sent'] ?? 0));
            $requiredPressure += max(0, (float) ($row['sold_pressure_total'] ?? 0));
        }

        $useDaka = column_exists($this->pdo, 'cylinder_stock_by_type', 'daka_qty');
        $stockStmt = $this->pdo->query(
            $useDaka
                ? 'SELECT COALESCE(SUM(daka_qty), 0) AS available, COALESCE(SUM(available_pressure), 0) AS available_pressure FROM cylinder_stock_by_type FOR UPDATE'
                : 'SELECT COALESCE(SUM(available), 0) AS available, COALESCE(SUM(available_pressure), 0) AS available_pressure FROM cylinder_stock_by_type FOR UPDATE'
        );
        $stockRow = $stockStmt->fetch() ?: [];
        $availableQty = max(0, (float) ($stockRow['available'] ?? 0));
        $availablePressure = max(0, (float) ($stockRow['available_pressure'] ?? 0));

        if ($requiredQty > 0 && $availableQty + 0.00001 < $requiredQty) {
            $availableFormatted = rtrim(rtrim(number_format($availableQty, 2, '.', ''), '0'), '.');
            $label = $useDaka ? 'Daka (full)' : 'stock';
            throw new RuntimeException("Insufficient cylinder {$label}. Available: {$availableFormatted}, requested: {$requiredQty}.");
        }
        if ($requiredPressure > 0 && $availablePressure + 0.00001 < $requiredPressure) {
            $availableFormatted = rtrim(rtrim(number_format($availablePressure, 2, '.', ''), '0'), '.');
            $requiredFormatted = rtrim(rtrim(number_format($requiredPressure, 2, '.', ''), '0'), '.');
            throw new RuntimeException("Insufficient cylinder pressure stock. Available: {$availableFormatted} Bar, requested: {$requiredFormatted} Bar.");
        }
    }

    private function applyPaymentAcrossOldestInvoices(int $customerId, float $amount, string $paymentDate): array
    {
        $payable = max(0, $amount);
        if ($payable <= 0) {
            return ['applied_total' => 0.0, 'unapplied_amount' => 0.0, 'last_payment_id' => 0];
        }

        $invoiceStmt = $this->pdo->prepare(
            "SELECT i.id, i.service_id, i.total_amount, i.paid_amount, i.remaining_amount
             FROM invoices i
             WHERE i.customer_id = ? AND i.remaining_amount > 0
             ORDER BY i.id ASC
             FOR UPDATE"
        );
        $invoiceStmt->execute([$customerId]);
        $openInvoices = $invoiceStmt->fetchAll();
        if (!$openInvoices) {
            return ['applied_total' => 0.0, 'unapplied_amount' => $payable, 'last_payment_id' => 0];
        }

        $insertPayment = $this->pdo->prepare('INSERT INTO payments (invoice_id, amount, payment_date) VALUES (?, ?, ?)');
        $updateInvoice = $this->pdo->prepare('UPDATE invoices SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?');
        $lastPaymentId = 0;
        $appliedTotal = 0.0;

        foreach ($openInvoices as $openInvoice) {
            if ($payable <= 0.00001) {
                break;
            }
            $invoiceId = (int) ($openInvoice['id'] ?? 0);
            $remaining = max(0, (float) ($openInvoice['remaining_amount'] ?? 0));
            if ($invoiceId <= 0 || $remaining <= 0) {
                continue;
            }
            $applyNow = min($payable, $remaining);
            if ($applyNow <= 0) {
                continue;
            }
            $newPaid = (float) ($openInvoice['paid_amount'] ?? 0) + $applyNow;
            $newRemaining = max(0, (float) ($openInvoice['total_amount'] ?? 0) - $newPaid);
            $newStatus = $newRemaining <= 0.00001 ? 'Paid' : 'Pending';

            $insertPayment->execute([$invoiceId, $applyNow, $paymentDate]);
            $lastPaymentId = (int) $this->pdo->lastInsertId();
            $updateInvoice->execute([$newPaid, $newRemaining, $newStatus, $invoiceId]);
            $this->syncServiceFinancialsFromInvoice($invoiceId);

            $payable -= $applyNow;
            $appliedTotal += $applyNow;
        }

        return [
            'applied_total' => $appliedTotal,
            'unapplied_amount' => max(0, $payable),
            'last_payment_id' => $lastPaymentId,
        ];
    }

    private function invoiceState(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT paid_amount, remaining_amount, status FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$invoiceId]);
        return $stmt->fetch() ?: [];
    }

    private function syncServiceFinancialsFromInvoice(int $invoiceId): void
    {
        $stmt = $this->pdo->prepare('SELECT service_id, paid_amount, remaining_amount FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            return;
        }
        $serviceId = (int) ($invoice['service_id'] ?? 0);
        if ($serviceId <= 0) {
            return;
        }
        $update = $this->pdo->prepare('UPDATE services SET paid_amount = ?, remaining_balance = ? WHERE id = ?');
        $update->execute([
            (float) ($invoice['paid_amount'] ?? 0),
            (float) ($invoice['remaining_amount'] ?? 0),
            $serviceId,
        ]);
    }

    private function reverseInventoryIssue(array $serviceRows, int $sentQty, int $emptyReceived): void
    {
        foreach ($serviceRows as $row) {
            $rowSent = max(0, (int) ($row['sent_qty'] ?? 0));
            $rowReceived = max(0, (int) ($row['received_qty'] ?? 0));
            $size = normalize_cylinder_type((string) ($row['cylinder_size'] ?? ''));
            if ($rowSent <= 0 && $rowReceived <= 0) {
                continue;
            }
            adjust_cylinder_daka_tash($this->pdo, $size, $rowSent, -$rowReceived);
        }

        $row = $this->pdo->query('SELECT * FROM cylinders ORDER BY id ASC LIMIT 1 FOR UPDATE')->fetch();
        if (!$row) {
            return;
        }
        $issued = max(0, (int) $row['issued'] - $sentQty);
        $update = $this->pdo->prepare('UPDATE cylinders SET issued = ? WHERE id = ?');
        $update->execute([$issued, (int) $row['id']]);
    }

    private function upsertLedger(int $customerId, float $debit, float $credit, string $date, array $meta = []): void
    {
        $lastBalance = $this->customerOutstandingBalance($customerId);
        $newBalance = ($lastBalance + $debit) - $credit;
        $insert = $this->pdo->prepare(
            'INSERT INTO ledger (customer_id, debit, credit, balance, date, description, cylinders_sent, cylinders_received, cylinders_baqi, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $customerId,
            $debit,
            $credit,
            $newBalance,
            $date,
            (string) ($meta['description'] ?? 'Service transaction'),
            (int) ($meta['sent'] ?? 0),
            (int) ($meta['received'] ?? 0),
            (int) ($meta['baqi'] ?? 0),
            (string) ($meta['reference_type'] ?? null),
            (int) ($meta['reference_id'] ?? null),
        ]);
    }

    private function customerOutstandingBalance(int $customerId): float
    {
        $lastStmt = $this->pdo->prepare('SELECT balance FROM ledger WHERE customer_id = ? ORDER BY id DESC LIMIT 1');
        $lastStmt->execute([$customerId]);
        return (float) ($lastStmt->fetch()['balance'] ?? 0);
    }

    /** Net cylinder position from history (Tash − Daka); negative means customer owes cylinders. */
    private function customerCylinderSignedNet(int $customerId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(SUM(r.sent_qty), 0) AS daka, COALESCE(SUM(r.received_qty), 0) AS tash
             FROM service_cylinder_rows r
             INNER JOIN services s ON s.id = r.service_id
             WHERE s.customer_id = ?'
        );
        $st->execute([$customerId]);
        $row = $st->fetch();
        if (!$row) {
            return 0;
        }
        return (int) $row['tash'] - (int) $row['daka'];
    }

    private function applyCustomerCylinderBalance(int $customerId, string $size, int $delta): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO customer_cylinder_balance (customer_id, cylinder_size, balance_qty) VALUES (?, ?, 0)')
            ->execute([$customerId, $size]);
        $stmt = $this->pdo->prepare('UPDATE customer_cylinder_balance SET balance_qty = GREATEST(0, balance_qty + ?) WHERE customer_id = ? AND cylinder_size = ?');
        $stmt->execute([$delta, $customerId, $size]);
    }

    private function applyCustomerTotalCylinderBalance(int $customerId, int $delta): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET current_cylinder_balance = GREATEST(0, current_cylinder_balance + ?) WHERE id = ?');
        $stmt->execute([$delta, $customerId]);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS customer_cylinder_balance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                cylinder_size ENUM('Small','Medium','Large') NOT NULL,
                balance_qty INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_customer_size (customer_id, cylinder_size),
                CONSTRAINT fk_customer_cylinder_balance_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS service_cylinder_rows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                cylinder_size ENUM('Small','Medium','Large') NOT NULL,
                sent_qty INT NOT NULL DEFAULT 0,
                received_qty INT NOT NULL DEFAULT 0,
                sale_units INT NOT NULL DEFAULT 0,
                baqi_qty INT NOT NULL DEFAULT 0,
                rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                billing_basis ENUM('quantity','psi') NOT NULL DEFAULT 'quantity',
                sale_pressure DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                sold_pressure_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_service_cylinder_rows_service FOREIGN KEY (service_id) REFERENCES services(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cylinder_stock_by_type (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cylinder_type ENUM('Small','Medium','Large') NOT NULL UNIQUE,
                total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                available DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                available_pressure DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $seedStmt = $this->pdo->prepare("INSERT IGNORE INTO cylinder_stock_by_type (cylinder_type, total, available) VALUES (?, 0, 0)");
        $seedStmt->execute([standard_cylinder_size()]);
        if (function_exists('column_exists')) {
            if (!column_exists($this->pdo, 'services', 'total_bill')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN total_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER price");
            }
            if (!column_exists($this->pdo, 'services', 'service_charges')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN service_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_bill");
            }
            if (!column_exists($this->pdo, 'services', 'previous_balance')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER service_charges");
            }
            if (!column_exists($this->pdo, 'services', 'grand_total')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER previous_balance");
            }
            if (!column_exists($this->pdo, 'services', 'paid_amount')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER grand_total");
            }
            if (!column_exists($this->pdo, 'services', 'remaining_balance')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER paid_amount");
            }
            if (!column_exists($this->pdo, 'services', 'notes')) {
                $this->pdo->exec("ALTER TABLE services ADD COLUMN notes VARCHAR(255) NULL AFTER remaining_balance");
            }
            if (!column_exists($this->pdo, 'service_cylinder_rows', 'sale_pressure')) {
                $this->pdo->exec("ALTER TABLE service_cylinder_rows ADD COLUMN sale_pressure DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER rate");
            }
            if (!column_exists($this->pdo, 'service_cylinder_rows', 'sale_units')) {
                $this->pdo->exec("ALTER TABLE service_cylinder_rows ADD COLUMN sale_units INT NOT NULL DEFAULT 0 AFTER received_qty");
                $this->pdo->exec("UPDATE service_cylinder_rows SET sale_units = CASE WHEN sent_qty > 0 THEN sent_qty ELSE received_qty END");
            }
            if (!column_exists($this->pdo, 'service_cylinder_rows', 'billing_basis')) {
                $this->pdo->exec("ALTER TABLE service_cylinder_rows ADD COLUMN billing_basis ENUM('quantity','psi') NOT NULL DEFAULT 'quantity' AFTER rate");
            }
            if (!column_exists($this->pdo, 'service_cylinder_rows', 'sold_pressure_total')) {
                $this->pdo->exec("ALTER TABLE service_cylinder_rows ADD COLUMN sold_pressure_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER sale_pressure");
            }
            if (!column_exists($this->pdo, 'cylinder_stock_by_type', 'available_pressure')) {
                $this->pdo->exec("ALTER TABLE cylinder_stock_by_type ADD COLUMN available_pressure DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER available");
            }
            if (!column_exists($this->pdo, 'invoices', 'service_id')) {
                $this->pdo->exec("ALTER TABLE invoices ADD COLUMN service_id INT NULL AFTER customer_id");
            }
            if (!column_exists($this->pdo, 'ledger', 'description')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN description VARCHAR(255) NULL AFTER date");
            }
            if (!column_exists($this->pdo, 'ledger', 'cylinders_sent')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN cylinders_sent INT NOT NULL DEFAULT 0 AFTER description");
            }
            if (!column_exists($this->pdo, 'ledger', 'cylinders_received')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN cylinders_received INT NOT NULL DEFAULT 0 AFTER cylinders_sent");
            }
            if (!column_exists($this->pdo, 'ledger', 'cylinders_baqi')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN cylinders_baqi INT NOT NULL DEFAULT 0 AFTER cylinders_received");
            }
            if (!column_exists($this->pdo, 'ledger', 'reference_type')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN reference_type VARCHAR(40) NULL AFTER cylinders_baqi");
            }
            if (!column_exists($this->pdo, 'ledger', 'reference_id')) {
                $this->pdo->exec("ALTER TABLE ledger ADD COLUMN reference_id INT NULL AFTER reference_type");
            }
            if (!column_exists($this->pdo, 'customers', 'current_cylinder_balance')) {
                $this->pdo->exec("ALTER TABLE customers ADD COLUMN current_cylinder_balance INT NOT NULL DEFAULT 0 AFTER address");
            }
        }
    }
}
