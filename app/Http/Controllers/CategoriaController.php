<?php

namespace App\Http\Controllers;

use App\Services\CategoriaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 *     name="Categorias",
 *     description="Categorias"
 * )
 */
class CategoriaController extends Controller
{
    protected $categoriaService;

    public function __construct(CategoriaServiceInterface $categoriaService)
    {
        $this->categoriaService = $categoriaService;
    }

    /**
     * @OA\Get(
     *     path="/cardapio",
     *     summary="Listar categorias",
     *     description="Retorna uma lista de categorias com seus produtos associados. Os resultados são armazenados em cache por 60 segundos.",
     *     operationId="listarCategorias",
     *     tags={"Categorias"},
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
     *                 @OA\Property(property="categorias", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza 💜"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="categoria_id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                         @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     ))
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
     * Exibe uma lista paginada de categorias.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a lista de categorias ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação das categorias.
     */
    public function index(Request $request)
    {
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $categorias = $this->categoriaService->listarCategorias($request);

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'categorias' => $categorias->items(),
                    'ultima_pagina' => $categorias->lastPage(),
                    'total_itens' => $categorias->total(),
                    'filtro' => [
                        'itens_por_pagina' => $categorias->perPage(),
                        'ordenar_por' => $request->query('ordenar_por', 'id'),
                        'ordem' =>  $request->query('ordem', 'asc'),
                        'pagina' => $categorias->currentPage(),
                    ],
                ];

                return response()->json($response);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao listar categorias', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }

    /**
     * @OA\Post(
     *     path="/categorias",
     *     summary="Criar nova categoria",
     *     description="Cria uma nova categoria. O cache de categorias é limpo após a criação.",
     *     operationId="criarCategoria",
     *     tags={"Categorias"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="nome", type="string", example="Pizza 💜")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Categoria criada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="categoria", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza 💜"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                    
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
     *                 @OA\Schema(type="string", example="O campo nome é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo nome deve ser uma string."),
     *                 @OA\Schema(type="string", example="O campo nome não pode ter mais de 255 caracteres."),
     *                 @OA\Schema(type="string", example="O nome da categoria já existe.")
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
     * Armazena uma nova categoria no banco de dados.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a nova categoria ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a criação da categoria.
     */
    public function store(Request $request)
    {
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $categoria = $this->categoriaService->criarCategoria($request);

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'categoria' => $categoria,
                ];

                return response()->json($response, 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response['mensagem_erro'] = is_array($errors) ? implode(', ', $errors) : $errors;
                return response()->json($response, 422);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao criar categoria', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }

    /**
     * @OA\Get(
     *     path="/categorias/{id}",
     *     summary="Mostrar categoria",
     *     description="Retorna uma categoria específica com seus produtos associados. O resultado é armazenado em cache por 60 segundos.",
     *     operationId="visualizarCategoria",
     *     tags={"Categorias"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID da categoria"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="string", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="categoria", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza 💜"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="categoria_id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                         @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     ))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoria não encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Categoria não encontrada"),
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
     * Exibe uma categoria específica.
     *
     * @param int $id ID da categoria.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a categoria ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação da categoria.
     */
    public function show($id)
    {
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $categoria = $this->categoriaService->mostrarCategoria($id);

                if (!$categoria) {
                    $response['mensagem_erro'] = 'Categoria não encontrada.';
                    return response()->json($response, 404);
                }

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'categoria' => $categoria,
                ];

                return response()->json($response, 200);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao mostrar categoria', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }

    /**
     * @OA\Put(
     *     path="/categorias/{id}",
     *     summary="Atualizar categoria",
     *     description="Atualiza uma categoria específica. O cache de categorias é limpo após a atualização.",
     *     operationId="atualizarCategoria",
     *     tags={"Categorias"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID da categoria"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="nome", type="string", example="Pizza 💜")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categoria atualizada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="string", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="categoria", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nome", type="string", example="Pizza 💜"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="categoria_id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                         @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z")
     *                     ))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoria não encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Categoria não encontrada."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", oneOf={
     *                 @OA\Schema(type="string", example="O campo nome é obrigatório."),
     *                 @OA\Schema(type="string", example="O campo nome deve ser uma string."),
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
     * Atualiza uma categoria específica.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * @param int $id ID da categoria.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a categoria atualizada ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a atualização da categoria.
     */
    public function update(Request $request, $id)
    {
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $categoria = $this->categoriaService->atualizarCategoria($request, $id);

                // Verifica se a categoria foi encontrada
                if (!$categoria) {
                    $response['mensagem_erro'] = 'Categoria não encontrada.';
                    return response()->json($response, 404);
                }

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'categoria' => $categoria,
                ];

                return response()->json($response, 200);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response['mensagem_erro'] = is_array($errors) ? implode(', ', $errors) : $errors;
                return response()->json($response, 422);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao atualizar categoria', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }


    /**
     * @OA\Delete(
     *     path="/categorias/{id}",
     *     summary="Deletar categoria",
     *     description="Deleta uma categoria específica se não houver mais nada vinculado a ela. O cache de categorias é limpo após a exclusão.",
     *     operationId="deletarCategoria",
     *     tags={"Categorias"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID da categoria"
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Categoria deletada com sucesso"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Categoria possui vínculos",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Categoria possui vínculos e não pode ser deletada."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoria não encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Categoria não encontrada."),
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
     * Deleta uma categoria específica.
     *
     * @param int $id ID da categoria.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON indicando sucesso ou falha na exclusão.
     * 
     * @throws \Exception Se ocorrer um erro durante a exclusão da categoria.
     */
    public function destroy($id)
    {
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $categoria = $this->categoriaService->deletarCategoria($id);

                // Verifica se a categoria foi encontrada
                if ($categoria['sucesso'] === false && $categoria['mensagem_erro'] === 'Categoria não encontrada.') {
                    $response['mensagem_erro'] = $categoria['mensagem_erro'];
                    return response()->json($response, 404);
                } elseif ($categoria['sucesso'] === false) {
                    $response['mensagem_erro'] = $categoria['mensagem_erro'];
                    return response()->json($response, 400);
                }

                return response()->json(null, 204);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao deletar categoria', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }
}
