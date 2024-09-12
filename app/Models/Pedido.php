<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 *     schema="Pedido",
 *     type="object",
 *     title="Pedido",
 *     description="Modelo de Pedido",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID do pedido"
 *     ),
 *     @OA\Property(
 *         property="estado",
 *         type="string",
 *         enum={"aberto", "aprovado", "concluido", "cancelado"},
 *         description="Estado do pedido"
 *     ),
 *     @OA\Property(
 *         property="preco_total",
 *         type="number",
 *         format="float",
 *         description="Preço total do pedido"
 *     ),
 *     @OA\Property(
 *         property="produtos",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Produto"),
 *         description="Produtos associados ao pedido"
 *     )
 * )
 */
class Pedido extends Model
{
    use HasFactory;

    protected $fillable = ['estado', 'preco_total'];

    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'produto_pedido')->withPivot('preco', 'quantidade');
    }
}
