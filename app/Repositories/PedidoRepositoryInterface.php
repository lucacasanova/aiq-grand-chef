<?php

namespace App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;

interface PedidoRepositoryInterface
{
    public function getAll(int $itensPorPagina, string $ordenarPor, string $ordem, int $pagina): LengthAwarePaginator;
    public function findById(int $id);
    public function create(array $data);
    public function update(int $id, array $data);
}
