<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Services\OxygenOpsService;

final class ServiceController
{
    private ServiceModel $model;
    private OxygenOpsService $ops;

    public function __construct()
    {
        $this->model = new ServiceModel();
        $this->ops = new OxygenOpsService();
    }

    public function index(): array
    {
        return ['data' => $this->model->all()];
    }

    public function show(int $id): array
    {
        $row = $this->model->find($id);
        return $row ? ['data' => $row] : ['error' => 'Service not found'];
    }

    public function store(array $data): array
    {
        $result = $this->ops->addServiceWithAutomation($data);
        return ['message' => 'Service added and invoice auto-generated', 'data' => $result];
    }

    public function update(int $id, array $data): array
    {
        $ok = $this->model->update($id, $data);
        return ['message' => $ok ? 'Service updated' : 'Service not updated'];
    }

    public function destroy(int $id): array
    {
        $ok = $this->model->delete($id);
        return ['message' => $ok ? 'Service deleted' : 'Service not deleted'];
    }
}
