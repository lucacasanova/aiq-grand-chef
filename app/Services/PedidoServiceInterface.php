<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PedidoServiceInterface
{
    public function listarPedidos(Request $request);
    public function criarPedido(Request $request);
    public function mostrarPedido($id);
    public function atualizarPedido(Request $request, $id);
}