<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProdutoControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa a listagem de produtos.
     *
     * Este teste verifica se a rota GET '/produtos' retorna uma lista de produtos com a estrutura JSON esperada.
     */
    public function testListarProdutos()
    {
        Categoria::factory()->hasProdutos(3)->create();

        $response = $this->getJson('/produtos?itens_por_pagina=10&ordenar_por=id&ordem=asc&pagina=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'produtos' => [
                        '*' => [
                            'id',
                            'categoria_id',
                            'nome',
                            'preco',
                            'created_at',
                            'updated_at',
                            'categoria' => [
                                'id',
                                'nome',
                                'created_at',
                                'updated_at',
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

        $this->assertCount(3, $response->json('dados.produtos'));
    }

    /**
     * Testa a criação de um novo produto.
     *
     * Este teste verifica se a rota POST '/produtos' cria um novo produto corretamente e limpa o cache após a criação.
     */
    public function testCriarProduto()
    {
        $categoria = Categoria::factory()->create();
        $data = [
            'categoria_id' => $categoria->id,
            'nome' => 'Novo Produto',
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(201)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => [
                        'nome' => 'Novo Produto',
                        'preco' => 99.99,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'produto' => [
                        'id',
                        'categoria_id',
                        'nome',
                        'preco',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('produtos', $data);
        $this->assertFalse(Cache::tags(['produtos'])->has('produtos_10_id_asc_pagina_1'));
    }

    /**
     * Testa a criação de um produto sem nome.
     *
     * Este teste verifica se a validação está funcionando corretamente ao tentar criar um produto sem o campo obrigatório 'nome'.
     */
    public function testCriarProdutoSemNome()
    {
        $categoria = Categoria::factory()->create();
        $data = [
            'categoria_id' => $categoria->id,
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome é obrigatório.',
            ]);
    }

    public function testCriarProdutoSemCategoriaId()
    {
        $data = [
            'nome' => 'Novo Produto',
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo categoria_id é obrigatório.',
            ]);
    }

    public function testCriarProdutoCategoriaIdNaoInteiro()
    {
        $data = [
            'categoria_id' => 'não é um inteiro',
            'nome' => 'Novo Produto',
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo categoria_id deve ser um inteiro.',
            ]);
    }

    public function testCriarProdutoCategoriaIdInexistente()
    {
        $data = [
            'categoria_id' => 999,
            'nome' => 'Novo Produto',
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'A categoria especificada não existe.',
            ]);
    }

    public function testCriarProdutoNomeDuplicado()
    {
        $categoria = Categoria::factory()->create();
        Produto::factory()->create(['nome' => 'Produto Existente']);
        $data = [
            'categoria_id' => $categoria->id,
            'nome' => 'Produto Existente',
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O nome do produto já existe.',
            ]);
    }

    public function testCriarProdutoNomeMuitoLongo()
    {
        $categoria = Categoria::factory()->create();
        $data = [
            'categoria_id' => $categoria->id,
            'nome' => str_repeat('a', 256),
            'preco' => 99.99,
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome não pode ter mais de 255 caracteres.',
            ]);
    }

    public function testCriarProdutoPrecoNaoNumerico()
    {
        $categoria = Categoria::factory()->create();
        $data = [
            'categoria_id' => $categoria->id,
            'nome' => 'Novo Produto',
            'preco' => 'não é numérico',
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo preco deve ser um número.',
            ]);
    }

    public function testCriarProdutoSemPreco()
    {
        $categoria = Categoria::factory()->create();
        $data = [
            'categoria_id' => $categoria->id,
            'nome' => 'Novo Produto',
        ];

        $response = $this->postJson('/produtos', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo preco é obrigatório.',
            ]);
    }

    /**
     * Testa a exibição de um produto específico.
     *
     * Este teste verifica se a rota GET '/produtos/{id}' retorna os detalhes de um produto específico.
     */
    public function testMostrarProduto()
    {
        $produto = Produto::factory()->create();

        $response = $this->getJson("/produtos/{$produto->id}");

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => [
                        'id' => $produto->id,
                        'nome' => $produto->nome,
                        'preco' => $produto->preco,
                        'categoria' => [
                            'id' => $produto->categoria->id,
                            'nome' => $produto->categoria->nome,
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Testa a exibição de um produto inexistente.
     *
     * Este teste verifica se a rota GET '/produtos/{id}' retorna um erro 404 quando o produto não existe.
     */
    public function testMostrarProdutoInexistente()
    {
        $response = $this->getJson("/produtos/999");

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Produto não encontrado.',
            ]);
    }

    /**
     * Testa a atualização de um produto.
     *
     * Este teste verifica se a rota PUT '/produtos/{id}' atualiza corretamente o nome e o preço de um produto.
     */
    public function testAtualizarProduto()
    {
        $produto = Produto::factory()->create();
        $data = [
            'nome' => 'Produto Atualizado',
            'preco' => 199.99,
        ];

        $response = $this->putJson("/produtos/{$produto->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => [
                        'id' => $produto->id,
                        'nome' => 'Produto Atualizado',
                        'preco' => 199.99,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('produtos', $data);
    }

    /**
     * Testa a atualização de um produto inexistente.
     *
     * Este teste verifica se a rota PUT '/produtos/{id}' retorna um erro 404 quando o produto não existe.
     */
    public function testAtualizarProdutoInexistente()
    {
        $data = [
            'nome' => 'Produto Atualizado',
            'preco' => 199.99,
        ];

        $response = $this->putJson("/produtos/999", $data);

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Produto não encontrado.',
            ]);
    }

    /**
     * Testa a exclusão de um produto.
     *
     * Este teste verifica se a rota DELETE '/produtos/{id}' exclui corretamente um produto e limpa o cache após a exclusão.
     */
    public function testDeletarProduto()
    {
        $produto = Produto::factory()->create();

        $response = $this->deleteJson("/produtos/{$produto->id}");
        $response->assertStatus(204);

        $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
        $this->assertFalse(Cache::tags(['produtos'])->has('produto_' . $produto->id));
    }

    /**
     * Testa a exclusão de um produto inexistente.
     *
     * Este teste verifica se a rota DELETE '/produtos/{id}' retorna um erro 404 quando o produto não existe.
     */
    public function testDeletarProdutoInexistente()
    {
        $response = $this->deleteJson("/produtos/999");

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Produto não encontrado.',
            ]);
    }
}
