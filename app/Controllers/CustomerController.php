<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;

final class CustomerController
{
    private CustomerModel $model;

    public function __construct()
    {
        $this->model = new CustomerModel();
    }

    public function index(): array
    {
        return ['data' => $this->model->all()];
    }

    public function show(int $id): array
    {
        $row = $this->model->find($id);
        return $row ? ['data' => $row] : ['error' => 'Customer not found'];
    }

    public function store(array $data): array
    {
        $id = $this->model->create($data);
        return ['message' => 'Customer created', 'id' => $id];
    }

    public function update(int $id, array $data): array
    {
        $ok = $this->model->update($id, $data);
        return ['message' => $ok ? 'Customer updated' : 'Customer not updated'];
    }

    public function destroy(int $id): array
    {
        $ok = $this->model->delete($id);
        return ['message' => $ok ? 'Customer deleted' : 'Customer not deleted'];
    }
}
