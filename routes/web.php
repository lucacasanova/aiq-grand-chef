<?php

use App\Events\atualizarPedido;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\PedidoController;

Route::get('/', function () {
    return view('welcome');
});

/*
* Rotas para categorias
*/
Route::get('cardapio', [CategoriaController::class, 'index']);
Route::post('categorias', [CategoriaController::class, 'store']);
Route::get('categorias/{id}', [CategoriaController::class, 'show']);
Route::put('categorias/{id}', [CategoriaController::class, 'update']);
Route::delete('categorias/{id}', [CategoriaController::class, 'destroy']);


/*
* Rotas para produtos
*/
Route::get('produtos', [ProdutoController::class, 'index']);
Route::post('produtos', [ProdutoController::class, 'store']);
Route::get('produtos/{id}', [ProdutoController::class, 'show']);
Route::put('produtos/{id}', [ProdutoController::class, 'update']);
Route::delete('produtos/{id}', [ProdutoController::class, 'destroy']);


/*
* Rotas para pedidos
*/
Route::get('pedidos', [PedidoController::class, 'index']);
Route::post('pedidos', [PedidoController::class, 'store']);
Route::get('pedidos/{id}', [PedidoController::class, 'show']);
Route::put('pedidos/{id}', [PedidoController::class, 'update']);
