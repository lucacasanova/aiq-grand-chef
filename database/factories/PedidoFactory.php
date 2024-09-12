<?php

namespace Database\Factories;

use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    public function definition()
    {
        return [
            'estado' => 'aberto',
            'preco_total' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}