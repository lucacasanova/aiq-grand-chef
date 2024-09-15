<?php

namespace App\Services;

use Illuminate\Http\Request;

interface CategoriaServiceInterface
{
    public function listarCategorias(Request $request);
    public function criarCategoria(Request $request);
    public function mostrarCategoria($id);
    public function atualizarCategoria(Request $request, $id);
    public function deletarCategoria($id);
}