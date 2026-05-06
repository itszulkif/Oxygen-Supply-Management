<?php

declare(strict_types=1);

namespace App\Models;

final class ServiceModel extends BaseModel
{
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM services ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (customer_id, service_type, quantity, price, date) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) ($data['customer_id'] ?? 0),
            (string) ($data['service_type'] ?? 'rental'),
            (int) ($data['quantity'] ?? 1),
            (float) ($data['price'] ?? 0),
            (string) ($data['date'] ?? date('Y-m-d')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE services SET customer_id = ?, service_type = ?, quantity = ?, price = ?, date = ? WHERE id = ?'
        );
        return $stmt->execute([
            (int) ($data['customer_id'] ?? 0),
            (string) ($data['service_type'] ?? 'rental'),
            (int) ($data['quantity'] ?? 1),
            (float) ($data['price'] ?? 0),
            (string) ($data['date'] ?? date('Y-m-d')),
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM services WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
