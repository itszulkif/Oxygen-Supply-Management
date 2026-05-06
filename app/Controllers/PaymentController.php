<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Services\OxygenOpsService;

final class PaymentController
{
    private PaymentModel $model;
    private OxygenOpsService $ops;

    public function __construct()
    {
        $this->model = new PaymentModel();
        $this->ops = new OxygenOpsService();
    }

    public function index(): array
    {
        return ['data' => $this->model->all()];
    }

    public function store(array $data): array
    {
        $result = $this->ops->addPaymentWithAutomation($data);
        return ['message' => 'Payment added and invoice/ledger updated', 'data' => $result];
    }
}
