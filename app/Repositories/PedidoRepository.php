<?php

namespace App\Repositories;

use App\Models\Pedido;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PedidoRepository implements PedidoRepositoryInterface
{
    public function getAll(int $itensPorPagina, string $ordenarPor, string $ordem, int $pagina): LengthAwarePaginator
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'pedidos_' . $itensPorPagina . '_' . $ordenarPor . '_' . $ordem . '_pagina_' . $pagina;

        // Recupera pedidos do cache ou do banco de dados
        return Cache::tags(['pedidos'])->remember($cacheKey, $cacheTempo, function () use ($itensPorPagina, $ordenarPor, $ordem, $pagina) {
            return Pedido::with(['produtos'])->orderBy($ordenarPor, $ordem)->paginate($itensPorPagina, ['*'], 'page', $pagina);
        });
    }

    public function findById(int $id)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Chave do cache
        $cacheKey = 'pedido_' . $id;

        // Recupera pedido do cache ou do banco de dados
        return Cache::tags(['pedidos'])->remember($cacheKey, $cacheTempo, function () use ($id) {
            // Recupera pedido com seus produtos associados
            return Pedido::with(['produtos'])->find($id);
        });
    }

    public function create(array $data)
    {
        // Criação do pedido
        $pedido = Pedido::create(['estado' => 'aberto']);

        $precoTotal = 0;

        // Adicionar produtos ao pedido e calcular o preço total
        foreach ($data['produtos'] as $produto) {
            $precoTotal += $produto['preco'] * $produto['quantidade'];
            $pedido->produtos()->attach($produto['produto_id'], [
                'quantidade' => $produto['quantidade'],
                'preco' => (float) $produto['preco'],
            ]);
        }

        // Atualizar o pedido com o preço total
        $pedido->update(['preco_total' => $precoTotal]);

        // Limpa o cache após criar um novo pedido
        Cache::tags(['pedidos'])->flush();

        // Carregar os produtos com o pivot ajustado
        $pedido->load(['produtos' => function ($query) {
            $query->select('produtos.*', 'produto_pedido.preco as pivot_preco', 'produto_pedido.quantidade as pivot_quantidade')
                ->withPivot('preco', 'quantidade');
        }]);

        // Ajustar o preço no pivot para float
        $pedido->produtos->each(function ($produto) {
            $produto->pivot->preco = (float) $produto->pivot->preco;
        });

        return $pedido;
    }

    public function update(int $id, array $data)
    {
        // Tempo de cache em segundos
        $cacheTempo = 60;

        // Recupera pedido com seus pedidos associados
        $pedido = Pedido::find($id);

        if ($pedido) {
            // Atualiza o pedido
            $pedido->update($data);

            // Ajustar os preços no pivot e preco_total para float
            $pedido->preco_total = (float) $pedido->preco_total;
            $pedido->produtos->each(function ($produto) {
                $produto->pivot->preco = (float) $produto->pivot->preco;
            });

            // Chave do cache
            $cacheKey = 'pedido_' . $pedido->id;
            // Atualiza o pedido no cache
            Cache::tags(['pedidos'])->put($cacheKey, $pedido, $cacheTempo);
        }

        return $pedido;
    }
}
