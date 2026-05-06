<?php

declare(strict_types=1);

namespace App\Models;

final class CustomerModel extends BaseModel
{
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM customers ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            trim((string) ($data['name'] ?? '')),
            trim((string) ($data['phone'] ?? '')),
            trim((string) ($data['address'] ?? '')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?'
        );
        return $stmt->execute([
            trim((string) ($data['name'] ?? '')),
            trim((string) ($data['phone'] ?? '')),
            trim((string) ($data['address'] ?? '')),
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
