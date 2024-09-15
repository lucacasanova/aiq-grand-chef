<?php

namespace App\Services;

use App\Events\atualizarCategoria;
use App\Events\criarCategoria;
use App\Events\listarCategoria;
use App\Repositories\CategoriaRepositoryInterface;
use Illuminate\Http\Request;

class CategoriaService implements CategoriaServiceInterface
{
    protected $categoriaRepository;

    public function __construct(CategoriaRepositoryInterface $categoriaRepository)
    {
        $this->categoriaRepository = $categoriaRepository;
    }

    public function listarCategorias(Request $request)
    {
        // Validação de parâmetros
        $itensPorPagina = (int) $request->query('itens_por_pagina', 10);
        $ordenarPor = $request->query('ordenar_por', 'id');
        $ordem = $request->query('ordem', 'asc');
        $pagina = (int) $request->query('pagina', 1);

        $categorias = $this->categoriaRepository->getAll($itensPorPagina, $ordenarPor, $ordem, $pagina);

        // Disparo de webSocket
        event(new listarCategoria($categorias->items()));

        return $categorias;
    }

    public function criarCategoria(Request $request)
    {
        // Validação dos dados da requisição
        $validatedData = $request->validate([
            'nome' => 'required|string|max:255|unique:categorias,nome',
        ], [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.string' => 'O campo nome deve ser uma string.',
            'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
            'nome.unique' => 'O nome da categoria já existe.',
        ]);

        $categoria = $this->categoriaRepository->create($validatedData);

        // Disparo de webSocket
        event(new criarCategoria($categoria));

        return $categoria;
    }

    public function mostrarCategoria($id)
    {
        $categoria = $this->categoriaRepository->findById($id);
        return $categoria;
    }

    public function atualizarCategoria(Request $request, $id)
    {
        $categoria = $this->categoriaRepository->findById($id);

        // Validação dos dados
        $validatedData = $request->validate([
            'nome' => 'string|max:255',
        ], [
            'nome.string' => 'O campo nome deve ser uma string.',
            'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
        ]);

        $categoria = $this->categoriaRepository->update($id, $validatedData);

        // Disparo de webSocket
        event(new atualizarCategoria($categoria));

        return $categoria;
    }

    public function deletarCategoria($id)
    {
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
        ];

        $categoria = $this->categoriaRepository->findById($id);

        if (!$categoria) {
            $response['mensagem_erro'] = 'Categoria não encontrada.';
            return $response;
        }

        if ($categoria->produtos()->exists()) {
            $response['mensagem_erro'] = 'Categoria possui vínculos e não pode ser deletada.';
            return $response;
        }

        $this->categoriaRepository->delete($id);

        $response['sucesso'] = true;

        return $response;
    }
}
