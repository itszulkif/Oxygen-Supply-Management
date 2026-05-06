<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

abstract class BaseModel
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }
}
