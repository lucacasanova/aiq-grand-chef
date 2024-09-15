<?php

namespace App\Services;

use App\Events\atualizarProduto;
use App\Events\criarProduto;
use App\Events\listarProduto;
use App\Repositories\ProdutoRepositoryInterface;
use Illuminate\Http\Request;

class ProdutoService implements ProdutoServiceInterface
{
    protected $produtoRepository;

    public function __construct(ProdutoRepositoryInterface $produtoRepository)
    {
        $this->produtoRepository = $produtoRepository;
    }

    public function listarProdutos(Request $request)
    {
        // Validação de parâmetros
        $itensPorPagina = (int) $request->query('itens_por_pagina', 10);
        $ordenarPor = $request->query('ordenar_por', 'id');
        $ordem = $request->query('ordem', 'asc');
        $pagina = (int) $request->query('pagina', 1);

        $produtos = $this->produtoRepository->getAll($itensPorPagina, $ordenarPor, $ordem, $pagina);

        // Disparo de webSocket
        event(new listarProduto($produtos->items()));

        return $produtos;
    }

    public function criarProduto(Request $request)
    {
        // Validação dos dados da requisição
        $validatedData = $request->validate([
            'categoria_id' => 'required|integer|exists:categorias,id',
            'nome' => 'required|string|max:255|unique:produtos,nome',
            'preco' => 'required|numeric',
        ], [
            'categoria_id.required' => 'O campo categoria_id é obrigatório.',
            'categoria_id.integer' => 'O campo categoria_id deve ser um inteiro.',
            'categoria_id.exists' => 'A categoria especificada não existe.',
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.string' => 'O campo nome deve ser uma string.',
            'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
            'nome.unique' => 'O nome do produto já existe.',
            'preco.required' => 'O campo preco é obrigatório.',
            'preco.numeric' => 'O campo preco deve ser um número.',
        ]);

        $produto = $this->produtoRepository->create($validatedData);

        // Carrega a categoria associada
        $produto->load('categoria');

        // Disparo de webSocket
        event(new criarProduto($produto));

        return $produto;
    }

    public function mostrarProduto($id)
    {
        $produto = $this->produtoRepository->findById($id);
        return $produto;
    }

    public function atualizarProduto(Request $request, $id)
    {
        $produto = $this->produtoRepository->findById($id);

        // Validação dos dados
        $validatedData = $request->validate([
            'categoria_id' => 'integer|exists:categorias,id',
            'nome' => 'string|max:255',
            'preco' => 'numeric',
        ], [
            'categoria_id.integer' => 'O campo categoria_id deve ser um inteiro.',
            'categoria_id.exists' => 'A categoria especificada não existe.',
            'nome.string' => 'O campo nome deve ser uma string.',
            'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
            'preco.numeric' => 'O campo preco deve ser um número.',
        ]);

        $produto = $this->produtoRepository->update($id, $validatedData);

        // Carrega a categoria associada e pedidos associados
        $produto->load('categoria')->load('pedidos');

        // Disparo de webSocket
        event(new atualizarProduto($produto));

        return $produto;
    }

    public function deletarProduto($id)
    {
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
        ];

        $produto = $this->produtoRepository->findById($id);

        if (!$produto) {
            $response['mensagem_erro'] = 'Produto não encontrado.';
            return $response;
        }

        if ($produto->pedidos()->exists()) {
            $response['mensagem_erro'] = 'Produto possui vínculos e não pode ser deletado.';
            return $response;
        }

        $this->produtoRepository->delete($id);

        $response['sucesso'] = true;

        return $response;
    }
}
