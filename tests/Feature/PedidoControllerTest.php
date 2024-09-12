<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PedidoControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa a listagem de pedidos.
     *
     * Este teste verifica se a rota GET '/pedidos' retorna uma lista de pedidos com a estrutura JSON esperada.
     */
    public function testListarPedidos()
    {
        // Cria 3 pedidos e associa produtos a eles com preço e quantidade definidos
        $pedidos = Pedido::factory()->count(3)->create(['estado' => 'aberto', 'preco_total' => 100.00]);
        $pedidos->each(function ($pedido) {
            $produto = Produto::factory()->create();
            $pedido->produtos()->attach($produto->id, [
                'quantidade' => 2,
                'preco' => 50.00,
            ]);
        });

        $response = $this->getJson('/pedidos?itens_por_pagina=10&ordenar_por=id&ordem=asc&pagina=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'pedidos' => [
                        '*' => [
                            'id',
                            'estado',
                            'preco_total',
                            'created_at',
                            'updated_at',
                            'produtos' => [
                                '*' => [
                                    'id',
                                    'categoria_id',
                                    'nome',
                                    'preco',
                                    'created_at',
                                    'updated_at',
                                    'pivot' => [
                                        'pedido_id',
                                        'produto_id',
                                        'preco',
                                        'quantidade',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'ultima_pagina',
                    'total_itens',
                    'filtro' => [
                        'itens_por_pagina',
                        'ordenar_por',
                        'ordem',
                        'pagina',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'sucesso' => true,
                'mensagem_erro' => null,
            ]);

        $this->assertCount(3, $response->json('dados.pedidos'));
    }

    /**
     * Testa a criação de um novo pedido.
     *
     * Este teste verifica se a rota POST '/pedidos' cria um novo pedido corretamente e limpa o cache após a criação.
     */
    public function testCriarPedido()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(201)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'pedido' => [
                        'estado' => 'aberto',
                        'preco_total' => 90.00,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'pedido' => [
                        'id',
                        'estado',
                        'preco_total',
                        'created_at',
                        'updated_at',
                        'produtos' => [
                            '*' => [
                                'id',
                                'categoria_id',
                                'nome',
                                'preco',
                                'created_at',
                                'updated_at',
                                'pivot' => [
                                    'pedido_id',
                                    'produto_id',
                                    'preco',
                                    'quantidade',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('pedidos', ['preco_total' => 90.00]);
        $this->assertFalse(Cache::tags(['pedidos'])->has('pedidos_10_id_asc_pagina_1'));
    }

    /**
     * Testa a criação de um pedido sem produtos.
     *
     * Este teste verifica se a validação está funcionando corretamente ao tentar criar um pedido sem o campo obrigatório 'produtos'.
     */
    public function testCriarPedidoSemProdutos()
    {
        $data = [];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo produtos é obrigatório.',
            ]);
    }

    
    public function testCriarPedidoProdutosNaoArray()
    {
        $data = ['produtos' => 'não é um array'];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo produtos deve ser um array.',
            ]);
    }

    public function testCriarPedidoProdutoIdFaltando()
    {
        $data = [
            'produtos' => [
                [
                    'quantidade' => 2,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo produto_id é obrigatório.',
            ]);
    }

    public function testCriarPedidoProdutoIdNaoInteiro()
    {
        $data = [
            'produtos' => [
                [
                    'produto_id' => 'não é um inteiro',
                    'quantidade' => 2,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo produto_id deve ser um inteiro.',
            ]);
    }

    public function testCriarPedidoProdutoIdInexistente()
    {
        $data = [
            'produtos' => [
                [
                    'produto_id' => 999,
                    'quantidade' => 2,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O produto especificado não existe.',
            ]);
    }

    public function testCriarPedidoQuantidadeFaltando()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo quantidade é obrigatório.',
            ]);
    }

    public function testCriarPedidoQuantidadeNaoInteiro()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 'não é um inteiro',
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo quantidade deve ser um inteiro.',
            ]);
    }

    public function testCriarPedidoQuantidadeMenorQueUm()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 0,
                    'preco' => 45.00,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo quantidade deve ser no mínimo 1.',
            ]);
    }

    public function testCriarPedidoPrecoFaltando()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo preco é obrigatório.',
            ]);
    }

    public function testCriarPedidoPrecoNaoNumerico()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco' => 'não é numérico',
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo preco deve ser numérico.',
            ]);
    }

    public function testCriarPedidoPrecoMenorQueZero()
    {
        $produto = Produto::factory()->create();
        $data = [
            'produtos' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco' => -1,
                ],
            ],
        ];

        $response = $this->postJson('/pedidos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo preco deve ser no mínimo 0.',
            ]);
    }
    /**
     * Testa a exibição de um pedido específico.
     *
     * Este teste verifica se a rota GET '/pedidos/{id}' retorna os detalhes de um pedido específico.
     */
    public function testMostrarPedido()
    {
        $pedido = Pedido::factory()->create();

        $response = $this->getJson("/pedidos/{$pedido->id}");

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'pedido' => [
                        'id' => $pedido->id,
                        'estado' => $pedido->estado,
                        'preco_total' => $pedido->preco_total,
                    ],
                ],
            ]);
    }

    /**
     * Testa a exibição de um pedido inexistente.
     *
     * Este teste verifica se a rota GET '/pedidos/{id}' retorna um erro 404 quando o pedido não existe.
     */
    public function testMostrarPedidoInexistente()
    {
        $response = $this->getJson("/pedidos/999");

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Pedido não encontrado.',
            ]);
    }

    /**
     * Testa a atualização de um pedido.
     *
     * Este teste verifica se a rota PUT '/pedidos/{id}' atualiza corretamente o estado de um pedido.
     */
    public function testAtualizarPedido()
    {
        $pedido = Pedido::factory()->create();
        $data = [
            'estado' => 'aprovado',
        ];

        $response = $this->putJson("/pedidos/{$pedido->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'pedido' => [
                        'id' => $pedido->id,
                        'estado' => 'aprovado',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'aprovado']);
    }

    /**
     * Testa a atualização de um pedido inexistente.
     *
     * Este teste verifica se a rota PUT '/pedidos/{id}' retorna um erro 404 quando o pedido não existe.
     */
    public function testAtualizarPedidoInexistente()
    {
        $data = [
            'estado' => 'aprovado',
        ];

        $response = $this->putJson("/pedidos/999", $data);

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Pedido não encontrado.',
            ]);
    }
}
