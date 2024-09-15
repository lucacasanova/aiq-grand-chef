<?php

namespace App\Services;

use App\Events\atualizarPedido;
use App\Events\criarPedido;
use App\Events\listarPedido;
use App\Repositories\PedidoRepositoryInterface;
use Illuminate\Http\Request;

class PedidoService implements PedidoServiceInterface
{
    protected $pedidoRepository;

    public function __construct(PedidoRepositoryInterface $pedidoRepository)
    {
        $this->pedidoRepository = $pedidoRepository;
    }

    public function listarPedidos(Request $request)
    {
        // Validação de parâmetros
        $itensPorPagina = (int) $request->query('itens_por_pagina', 10);
        $ordenarPor = $request->query('ordenar_por', 'id');
        $ordem = $request->query('ordem', 'asc');
        $pagina = (int) $request->query('pagina', 1);

        $pedidos = $this->pedidoRepository->getAll($itensPorPagina, $ordenarPor, $ordem, $pagina);

        // Ajustar os preços no pivot para float
        $pedidos->each(function ($pedido) {
            $pedido->preco_total = (float) $pedido->preco_total;
            $pedido->produtos->each(function ($produto) {
                $produto->pivot->preco = (float) $produto->pivot->preco;
            });
        });

        // Disparo de webSocket
        event(new listarPedido($pedidos->items()));

        return $pedidos;
    }

    public function criarPedido(Request $request)
    {
        // Validação dos dados da requisição
        $validatedData = $request->validate([
            'produtos' => 'required|array',
            'produtos.*.produto_id' => 'required|integer|exists:produtos,id',
            'produtos.*.quantidade' => 'required|integer|min:1',
            'produtos.*.preco' => 'required|numeric|min:0',
        ], [
            'produtos.required' => 'O campo produtos é obrigatório.',
            'produtos.array' => 'O campo produtos deve ser um array.',
            'produtos.*.produto_id.required' => 'O campo produto_id é obrigatório.',
            'produtos.*.produto_id.integer' => 'O campo produto_id deve ser um inteiro.',
            'produtos.*.produto_id.exists' => 'O produto especificado não existe.',
            'produtos.*.quantidade.required' => 'O campo quantidade é obrigatório.',
            'produtos.*.quantidade.integer' => 'O campo quantidade deve ser um inteiro.',
            'produtos.*.quantidade.min' => 'O campo quantidade deve ser no mínimo 1.',
            'produtos.*.preco.required' => 'O campo preco é obrigatório.',
            'produtos.*.preco.numeric' => 'O campo preco deve ser numérico.',
            'produtos.*.preco.min' => 'O campo preco deve ser no mínimo 0.',
        ]);

        $pedido = $this->pedidoRepository->create($validatedData);

        // Disparo de webSocket
        event(new criarPedido($pedido));

        return $pedido;
    }

    public function mostrarPedido($id)
    {
        $pedido = $this->pedidoRepository->findById($id);

        // Ajustar os preços no pivot e preco_total para float
        $pedido->preco_total = (float) $pedido->preco_total;
        $pedido->produtos->each(function ($produto) {
            $produto->pivot->preco = (float) $produto->pivot->preco;
        });

        return $pedido;
    }

    public function atualizarPedido(Request $request, $id)
    {
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'pedido' => null,
        ];

        $pedido = $this->pedidoRepository->findById($id);

        // Validação dos dados
        $validatedData = $request->validate([
            'estado' => 'in:aprovado,concluido,cancelado',
        ]);

        // Verificar se o estado já está atualizado
        if ($pedido->estado === $validatedData['estado']) {
            $response['mensagem_erro'] = 'O estado já está atualizado.';
            return $response;
        }

        //Validações adicionais
        if ($pedido->estado === 'concluido' && $validatedData['estado'] === 'aprovado') {
            $response['mensagem_erro'] = 'Não é possível alterar um pedido concluído para aprovado.';
            return $response;
        }
        if ($pedido->estado === 'cancelado' && in_array($validatedData['estado'], ['aprovado', 'concluido'])) {
            $response['mensagem_erro'] = 'Não é possível alterar um pedido cancelado para outro estado.';
            return $response;
        }

        $pedido = $this->pedidoRepository->update($id, $validatedData);

        // Disparo de webSocket
        event(new atualizarPedido($pedido));

        $response['sucesso'] = true;
        $response['pedido'] = $pedido;
        return $response;
    }
}
