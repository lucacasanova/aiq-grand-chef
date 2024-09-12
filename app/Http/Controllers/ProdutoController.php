<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 *     name="Produtos",
 *     description="Produtos"
 * )
 */

class ProdutoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/produtos",
     *     summary="Listar produtos",
     *     description="Retorna uma lista de produtos. Os resultados são armazenados em cache por 60 segundos.",
     *     operationId="listarProdutos",
     *     tags={"Produtos"},
     *     @OA\Parameter(
     *         name="itens_por_pagina",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         description="Número de itens por página"
     *     ),
     *     @OA\Parameter(
     *         name="ordenar_por",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Coluna para ordenação"
     *     ),
     *     @OA\Parameter(
     *         name="ordem",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}),
     *         description="Direção da ordenação"
     *     ),
     *     @OA\Parameter(
     *         name="pagina",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         description="Número da página"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="produtos", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="categoria_id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                     @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="categoria", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     )
     *                 )),
     *                 @OA\Property(property="ultima_pagina", type="integer", example=1),
     *                 @OA\Property(property="total_itens", type="integer", example=1),
     *                 @OA\Property(property="filtro", type="object",
     *                     @OA\Property(property="itens_por_pagina", type="integer", example=10),
     *                     @OA\Property(property="ordenar_por", type="string", example="id"),
     *                     @OA\Property(property="ordem", type="string", enum={"asc", "desc"}),
     *                     @OA\Property(property="pagina", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor + log interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Algo deu errado. Tente novamente mais tarde."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     * 
     * Exibe uma lista paginada de produtos.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a lista de produtos ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação dos produtos.
     */
    public function index(Request $request)
    {
        try {
            // Validação de parâmetros
            $itensPorPagina = $request->query('itens_por_pagina', 10);
            $ordenarPor = $request->query('ordenar_por', 'id');
            $ordem = $request->query('ordem', 'asc');
            $pagina = $request->query('pagina', 1);

            // Tempo de cache em segundos
            $cacheTempo = 60;

            // Chave do cache
            $cacheKey = 'produtos_' . $itensPorPagina . '_' . $ordenarPor . '_' . $ordem . '_pagina_' . $pagina;

            // Recupera produtos do cache ou do banco de dados
            $produtos = Cache::tags(['produtos'])->remember($cacheKey, $cacheTempo, function () use ($itensPorPagina, $ordenarPor, $ordem, $pagina) {
                return Produto::with('categoria')->orderBy($ordenarPor, $ordem)->paginate($itensPorPagina, ['*'], 'page', $pagina);
            });

            // Preparar resposta
            $response = [
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produtos' => $produtos->items(),
                    'ultima_pagina' => $produtos->lastPage(),
                    'total_itens' => $produtos->total(),
                    'filtro' => [
                        'itens_por_pagina' => $itensPorPagina,
                        'ordenar_por' => $ordenarPor,
                        'ordem' => $ordem,
                        'pagina' => $produtos->currentPage(),
                    ],
                ]
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            // Log detalhado do erro
            Log::channel('apis')->error('Erro ao listar produtos', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Resposta de erro
            $response = [
                'sucesso' => false,
                'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                'dados' => null,
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/produtos",
     *     summary="Criar novo produto",
     *     description="Cria um novo produto. O cache de produtos é limpo após a criação.",
     *     operationId="criarProduto",
     *     tags={"Produtos"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categoria_id", type="integer", example=1),
     *             @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *             @OA\Property(property="preco", type="number", format="float", example=47.77)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Produto criado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="produto", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="categoria_id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                     @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", oneOf={
     *                 @OA\Schema(type="string", example="O campo categoria_id é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo nome é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo preco é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo preco deve ser um número."),
     *                 @OA\Schema(type="string", example="O campo nome não pode ter mais de 255 caracteres."),
     *                 @OA\Schema(type="string", example="O nome do produto já existe.")
     *             }),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor + log interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Algo deu errado. Tente novamente mais tarde."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     * 
     * Armazena um novo produto no banco de dados.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o novo produto ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a criação do produto.
     */
    public function store(Request $request)
    {
        try {
            try {
                // Validação dos dados da requisição
                $validatedData = $request->validate([
                    'categoria_id' => 'required|integer|exists:categorias,id',
                    'nome' => 'required|string|max:255|unique:produtos,nome',
                    'preco' => 'required|numeric',
                ], [
                    'categoria_id.required' => 'O campo categoria_id é obrigatório.',
                    'categoria_id.integer' => 'O campo categoria_id deve ser um inteiro.',
                    'categoria_id.exists' => 'A categoria especificada não existe.',
                    'nome.required' => 'O campo nome é obrigatório.',
                    'nome.string' => 'O campo nome deve ser uma string.',
                    'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
                    'nome.unique' => 'O nome do produto já existe.',
                    'preco.required' => 'O campo preco é obrigatório.',
                    'preco.numeric' => 'O campo preco deve ser um número.',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => is_array($errors) ? implode(', ', $errors) : $errors,
                    'dados' => null,
                ];

                return response()->json($response, 422);
            }

            // Cria o produto
            $produto = Produto::create($validatedData);
            Cache::tags(['produtos'])->flush(); // Limpa o cache após criar um novo produto

            // Preparar resposta
            $response = [
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => $produto,
                ],
            ];

            return response()->json($response, 201);
        } catch (\Exception $e) {
            // Log detalhado do erro
            Log::channel('apis')->error('Erro ao criar produto', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Resposta de erro
            $response = [
                'sucesso' => false,
                'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                'dados' => null,
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/produtos/{id}",
     *     summary="Mostrar produto",
     *     description="Retorna um produto específico com sua categoria e pedidos associados. O resultado é armazenado em cache por 60 segundos.",
     *     operationId="visualizarProduto",
     *     tags={"Produtos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID do produto"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="produto", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="categoria_id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                     @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="categoria", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     ),
     *                     @OA\Property(property="pedidos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="produto_id", type="integer", example=1),
     *                         @OA\Property(property="quantidade", type="integer", example=2),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     ))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produto não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Produto não encontrado."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor + log interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Algo deu errado. Tente novamente mais tarde."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     * 
     * Exibe um produto específico.
     *
     * @param int $id ID do produto.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o produto ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação do produto.
     */
    public function show($id)
    {
        try {
            // Recupera o produto pelo ID com suas relações
            $produto = Produto::with(['categoria', 'pedidos'])->find($id);

            // Verifica se o produto foi encontrado
            if (!$produto) {
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => 'Produto não encontrado.',
                    'dados' => null,
                ];

                return response()->json($response, 404);
            }

            // Chave do cache
            $cacheKey = 'produto_' . $produto->id;

            // Recupera o produto do cache ou do banco de dados
            $produto = Cache::tags(['produtos'])->remember($cacheKey, 60, function () use ($produto) {
                return $produto;
            });

            // Preparar resposta
            $response = [
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => $produto,
                ],
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Log detalhado do erro
            Log::channel('apis')->error('Erro ao exibir produto', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Resposta de erro
            $response = [
                'sucesso' => false,
                'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                'dados' => null,
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/produtos/{id}",
     *     summary="Atualizar produto",
     *     description="Atualiza um produto específico. O cache de produtos é limpo após a atualização.",
     *     operationId="atualizarProduto",
     *     tags={"Produtos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID do produto"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categoria_id", type="integer", example=1),
     *             @OA\Property(property="nome", type="string", example="Novo Nome do Produto"),
     *             @OA\Property(property="preco", type="number", format="float", example=47.77)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produto atualizado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="produto", ref="#/components/schemas/Produto")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produto não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Produto não encontrado."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", oneOf={
     *                 @OA\Schema(type="string", example="O campo categoria_id é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo nome é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo preco é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo preco deve ser um número."),
     *                 @OA\Schema(type="string", example="O campo nome não pode ter mais de 255 caracteres.")
     *             }),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor + log interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Algo deu errado. Tente novamente mais tarde."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     * 
     * Atualiza um produto específico.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * @param int $id ID do produto.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o produto atualizado ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a atualização do produto.
     */
    public function update(Request $request, $id)
    {
        try {
            // Recupera o produto pelo ID
            $produto = Produto::find($id);

            // Verifica se o produto foi encontrado
            if (!$produto) {
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => 'Produto não encontrado.',
                    'dados' => null,
                ];

                return response()->json($response, 404);
            }

            try {
                // Validação dos dados da requisição
                $validatedData = $request->validate([
                    'categoria_id' => 'integer|exists:categorias,id',
                    'nome' => 'string|max:255',
                    'preco' => 'numeric',
                ], [
                    'categoria_id.integer' => 'O campo categoria_id deve ser um inteiro.',
                    'categoria_id.exists' => 'A categoria especificada não existe.',
                    'nome.string' => 'O campo nome deve ser uma string.',
                    'nome.max' => 'O campo nome não pode ter mais de 255 caracteres.',
                    'preco.numeric' => 'O campo preco deve ser um número.',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => is_array($errors) ? implode(', ', $errors) : $errors,
                    'dados' => null,
                ];

                return response()->json($response, 422);
            }

            // Atualiza o produto
            $produto->update($validatedData);

            // Atualiza o cache do produto
            $cacheKey = 'produto_' . $produto->id;
            Cache::tags(['produtos'])->put($cacheKey, $produto, 60);

            // Preparar resposta
            $response = [
                'sucesso' => true,
                'mensagem_erro' => null,
                'dados' => [
                    'produto' => $produto,
                ],
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Log detalhado do erro
            Log::channel('apis')->error('Erro ao atualizar produto', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Resposta de erro
            $response = [
                'sucesso' => false,
                'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                'dados' => null,
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/produtos/{id}",
     *     summary="Deletar produto",
     *     description="Deleta um produto específico se não houver mais nada vinculado a ele. O cache de produtos é limpo após a exclusão.",
     *     operationId="deletarProduto",
     *     tags={"Produtos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID do produto"
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Produto deletado com sucesso"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Produto possui vínculos",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Produto possui vínculos e não pode ser deletado."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produto não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Produto não encontrado."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor + log interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Algo deu errado. Tente novamente mais tarde."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     )
     * )
     * 
     * Deleta um produto específico.
     *
     * @param int $id ID do produto.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON indicando sucesso ou falha na exclusão.
     * 
     * @throws \Exception Se ocorrer um erro durante a exclusão do produto.
     */
    public function destroy($id)
    {
        try {
            // Recupera o produto pelo ID
            $produto = Produto::find($id);

            // Verifica se o produto foi encontrado
            if (!$produto) {
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => 'Produto não encontrado.',
                    'dados' => null,
                ];

                return response()->json($response, 404);
            }

            // Verificar se o produto tem vínculos
            if ($produto->pedidos()->exists()) {
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => 'Produto possui vínculos e não pode ser deletado.',
                    'dados' => null,
                ];

                return response()->json($response, 400);
            }

            // Deleta o produto
            $produto->delete();

            // Remove o cache do produto
            $cacheKey = 'produto_' . $produto->id;
            Cache::tags(['produtos'])->forget($cacheKey);

            return response()->json(null, 204);
        } catch (\Exception $e) {
            // Log detalhado do erro
            Log::channel('apis')->error('Erro ao deletar produto', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Resposta de erro
            $response = [
                'sucesso' => false,
                'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                'dados' => null,
            ];

            return response()->json($response, 500);
        }
    }
}
