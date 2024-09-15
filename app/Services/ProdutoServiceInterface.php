<?php

namespace App\Services;

use Illuminate\Http\Request;

interface ProdutoServiceInterface
{
    public function listarProdutos(Request $request);
    public function criarProduto(Request $request);
    public function mostrarProduto($id);
    public function atualizarProduto(Request $request, $id);
    public function deletarProduto($id);
}