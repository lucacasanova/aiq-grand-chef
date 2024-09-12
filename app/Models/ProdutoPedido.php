<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OpenApi\Attributes as OA;
/**
 * @OA\Schema(
 *     schema="ProdutoPedido",
 *     type="object",
 *     title="ProdutoPedido",
 *     description="Modelo de ProdutoPedido",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID do produto pedido"
 *     ),
 *     @OA\Property(
 *         property="pedido_id",
 *         type="integer",
 *         description="ID do pedido"
 *     ),
 *     @OA\Property(
 *         property="produto_id",
 *         type="integer",
 *         description="ID do produto"
 *     ),
 *     @OA\Property(
 *         property="preco",
 *         type="number",
 *         format="float",
 *         description="Preço do produto no pedido"
 *     ),
 *     @OA\Property(
 *         property="quantidade",
 *         type="integer",
 *         description="Quantidade do produto no pedido"
 *     )
 * )
 */
class ProdutoPedido extends Pivot
{
    use HasFactory;

    protected $table = 'produto_pedido';
    protected $fillable = ['pedido_id', 'produto_id', 'preco', 'quantidade'];
}
