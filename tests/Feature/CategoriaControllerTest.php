<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testListarCategorias()
    {
        Categoria::factory()->hasProdutos(3)->count(15)->create();

        $response = $this->getJson('/cardapio?itens_por_pagina=10&ordenar_por=id&ordem=asc&pagina=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'categorias' => [
                        '*' => [
                            'id',
                            'nome',
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

        $this->assertCount(10, $response->json('dados.categorias'));
    }

    public function testCriarCategoria()
    {
        $data = ['nome' => 'Nova Categoria'];

        $response = $this->postJson('/categorias', $data);

        $response->assertStatus(201)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'categoria' => [
                        'nome' => 'Nova Categoria',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'sucesso',
                'mensagem_erro',
                'dados' => [
                    'categoria' => [
                        'id',
                        'nome',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('categorias', $data);
        $this->assertFalse(Cache::tags(['categorias'])->has('categorias_10_id_asc_pagina_1'));
    }

    public function testCriarCategoriaSemNome()
    {
        $data = [];

        $response = $this->postJson('/categorias', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome é obrigatório.',
            ]);
    }

    public function testCriarCategoriaNomeDuplicado()
    {
        $categoria = Categoria::factory()->create(['nome' => 'Categoria Existente']);
        $data = ['nome' => 'Categoria Existente'];

        $response = $this->postJson('/categorias', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O nome da categoria já existe.',
            ]);
    }

    public function testCriarCategoriaNomeMuitoLongo()
    {
        $data = ['nome' => str_repeat('a', 256)];

        $response = $this->postJson('/categorias', $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome não pode ter mais de 255 caracteres.',
            ]);
    }

    public function testAtualizarCategoriaNomeMuitoLongo()
    {
        $categoria = Categoria::factory()->create();
        $data = ['nome' => str_repeat('a', 256)];

        $response = $this->putJson("/categorias/{$categoria->id}", $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome não pode ter mais de 255 caracteres.',
            ]);
    }

    public function testAtualizarCategoriaNomeInvalido()
    {
        $categoria = Categoria::factory()->create();
        $data = ['nome' => 12345];

        $response = $this->putJson("/categorias/{$categoria->id}", $data);

        $response->assertStatus(422)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'O campo nome deve ser uma string.',
            ]);
    }

    public function testMostrarCategoria()
    {
        $categoria = Categoria::factory()->hasProdutos(3)->create();

        $response = $this->getJson("/categorias/{$categoria->id}");

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'categoria' => [
                        'id' => $categoria->id,
                        'nome' => $categoria->nome,
                        'produtos' => $categoria->produtos->toArray(),
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('dados.categoria.produtos'));
    }

    public function testMostrarCategoriaInexistente()
    {
        $response = $this->getJson("/categorias/999");

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Categoria não encontrada',
            ]);
    }

    public function testAtualizarCategoria()
    {
        $categoria = Categoria::factory()->hasProdutos(3)->create();
        $data = ['nome' => 'Categoria Atualizada'];

        $response = $this->putJson("/categorias/{$categoria->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'categoria' => [
                        'id' => $categoria->id,
                        'nome' => 'Categoria Atualizada',
                        'produtos' => $categoria->produtos->toArray(),
                    ],
                ],
            ]);

        $this->assertDatabaseHas('categorias', $data);
        $this->assertCount(3, $categoria->produtos);
    }

    public function testAtualizarCategoriaInexistente()
    {
        $data = ['nome' => 'Categoria Atualizada'];

        $response = $this->putJson("/categorias/999", $data);

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Categoria não encontrada.',
            ]);
    }

    public function testDeletarCategoria()
    {
        $categoria = Categoria::factory()->hasProdutos(3)->create();

        $response = $this->deleteJson("/categorias/{$categoria->id}");
        $response->assertStatus(400)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Categoria possui vínculos e não pode ser deletada.',
            ]);

        $categoria->produtos()->delete();

        $response = $this->deleteJson("/categorias/{$categoria->id}");
        $response->assertStatus(204);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
        $this->assertFalse(Cache::tags(['categorias'])->has('categoria_' . $categoria->id));
    }

    public function testDeletarCategoriaInexistente()
    {
        $response = $this->deleteJson("/categorias/999");

        $response->assertStatus(404)
            ->assertJson([
                'sucesso' => false,
                'mensagem_erro' => 'Categoria não encontrada.',
            ]);
    }
}
