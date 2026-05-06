<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\InvoiceModel;

final class InvoiceController
{
    private InvoiceModel $model;

    public function __construct()
    {
        $this->model = new InvoiceModel();
    }

    public function index(): array
    {
        return ['data' => $this->model->all()];
    }

    public function show(int $id): array
    {
        $row = $this->model->find($id);
        return $row ? ['data' => $row] : ['error' => 'Invoice not found'];
    }
}
