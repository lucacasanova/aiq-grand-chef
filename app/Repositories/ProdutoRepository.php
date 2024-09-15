<?php

namespace App\Repositories;

use App\Models\Produto;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProdutoRepository implements ProdutoRepositoryInterface
{
    public function getAll(int $itensPorPagina, string $ordenarPor, string $ordem, int $pagina): LengthAwarePaginator
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'produtos_' . $itensPorPagina . '_' . $ordenarPor . '_' . $ordem . '_pagina_' . $pagina;

        // Recupera produtos do cache ou do banco de dados
        return Cache::tags(['produtos'])->remember($cacheKey, $cacheTempo, function () use ($itensPorPagina, $ordenarPor, $ordem, $pagina) {
            return Produto::with(['categoria'])->orderBy($ordenarPor, $ordem)->paginate($itensPorPagina, ['*'], 'page', $pagina);
        });
    }

    public function findById(int $id)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'produto_' . $id;

        // Recupera produto do cache ou do banco de dados
        return Cache::tags(['produtos'])->remember($cacheKey, $cacheTempo, function () use ($id) {
            // Recupera produto com suas categorias e pedidos associados
            return Produto::with(['categoria', 'pedidos'])->find($id);
        });
    }

    public function create(array $data)
    {
        // Criação do produto
        $produto = Produto::create($data);

        // Limpa o cache após criar um novo produto
        Cache::tags(['produtos'])->flush();

        return $produto;
    }

    public function update(int $id, array $data)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Recupera produto com seus produtos associados
        $produto = Produto::find($id);

        if ($produto) {
            // Atualiza o produto
            $produto->update($data);
            // Chave do cache
            $cacheKey = 'produto_' . $produto->id;
            // Atualiza o produto no cache
            Cache::tags(['produtos'])->put($cacheKey, $produto, $cacheTempo);
        }

        return $produto;
    }

    public function delete(int $id)
    {
        // Recupera produto pelo ID
        $produto = Produto::find($id);

        if ($produto) {
            // Deleta o produto
            $produto->delete();
            // Remove o produto do cache
            $cacheKey = 'produto_' . $produto->id;
            Cache::tags(['produtos'])->forget($cacheKey);
        }

        return $produto;
    }
}
