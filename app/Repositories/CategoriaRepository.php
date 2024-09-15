<?php

namespace App\Repositories;

use App\Models\Categoria;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CategoriaRepository implements CategoriaRepositoryInterface
{
    public function getAll(int $itensPorPagina, string $ordenarPor, string $ordem, int $pagina): LengthAwarePaginator
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'categorias_' . $itensPorPagina . '_' . $ordenarPor . '_' . $ordem . '_pagina_' . $pagina;

        // Recupera categorias do cache ou do banco de dados
        return Cache::tags(['categorias'])->remember($cacheKey, $cacheTempo, function () use ($itensPorPagina, $ordenarPor, $ordem, $pagina) {
            return Categoria::with(['produtos'])->orderBy($ordenarPor, $ordem)->paginate($itensPorPagina, ['*'], 'page', $pagina);
        });
    }

    public function findById(int $id)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'categoria_' . $id;

        // Recupera a categoria do cache ou do banco de dados
        return Cache::tags(['categorias'])->remember($cacheKey, $cacheTempo, function () use ($id) {
            // Recupera a categoria com seus produtos associados
            return Categoria::with(['produtos'])->find($id);
        });
    }

    public function create(array $data)
    {
        // Criação da nova categoria
        $categoria = Categoria::create($data);

        // Limpa o cache após criar uma nova categoria
        Cache::tags(['categorias'])->flush();

        return $categoria;
    }

    public function update(int $id, array $data)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Recupera a categoria com seus produtos associados
        $categoria = Categoria::find($id);

        if ($categoria) {
            // Atualiza a categoria
            $categoria->update($data);
            // Chave do cache
            $cacheKey = 'categoria_' . $categoria->id;
            // Atualiza a categoria no cache
            Cache::tags(['categorias'])->put($cacheKey, $categoria, $cacheTempo);
        }

        return $categoria;
    }

    public function delete(int $id)
    {
        // Recupera a categoria pelo ID
        $categoria = Categoria::find($id);

        if ($categoria) {
            // Deleta a categoria
            $categoria->delete();
            // Remove a categoria do cache
            $cacheKey = 'categoria_' . $categoria->id;
            Cache::tags(['categorias'])->forget($cacheKey);
        }

        return $categoria;
    }
}
