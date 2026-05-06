<?php

declare(strict_types=1);

namespace App\Models;

final class InvoiceModel extends BaseModel
{
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM invoices ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
