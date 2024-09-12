<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 *     schema="Categoria",
 *     type="object",
 *     title="Categoria",
 *     description="Modelo de Categoria",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID da categoria"
 *     ),
 *     @OA\Property(
 *         property="nome",
 *         type="string",
 *         description="Nome da categoria"
 *     ),
 *     @OA\Property(
 *         property="produtos",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Produto"),
 *         description="Produtos associados à categoria"
 *     )
 * )
 */
class Categoria extends Model
{
    use HasFactory;

    protected $fillable = ['nome'];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }
}
