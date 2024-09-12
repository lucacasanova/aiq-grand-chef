<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 *     schema="Produto",
 *     type="object",
 *     title="Produto",
 *     description="Modelo de Produto",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID do produto"
 *     ),
 *     @OA\Property(
 *         property="nome",
 *         type="string",
 *         description="Nome do produto"
 *     ),
 *     @OA\Property(
 *         property="preco",
 *         type="number",
 *         format="float",
 *         description="Preço do produto"
 *     ),
 *     @OA\Property(
 *         property="categoria_id",
 *         type="integer",
 *         description="ID da categoria do produto"
 *     ),
 *     @OA\Property(
 *         property="categoria",
 *         ref="#/components/schemas/Categoria",
 *         description="Categoria do produto"
 *     ),
 *     @OA\Property(
 *         property="pedidos",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Pedido"),
 *         description="Pedidos associados ao produto"
 *     )
 * )
 */
class Produto extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'preco', 'categoria_id'];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidos()
    {
        return $this->belongsToMany(Pedido::class, 'produto_pedido')->withPivot('preco', 'quantidade');
    }

    public function getPrecoAttribute($value)
    {
        return (float) $value;
    }
}
