<?php

declare(strict_types=1);

namespace App\Models;

final class PaymentModel extends BaseModel
{
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM payments ORDER BY id DESC')->fetchAll();
    }
}
