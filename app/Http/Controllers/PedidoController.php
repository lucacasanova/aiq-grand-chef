<?php

namespace App\Http\Controllers;

use App\Events\atualizarPedido;
use App\Events\criarPedido;
use App\Events\listarPedido;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 *     name="Pedidos",
 *     description="Pedidos"
 * )
 */

class PedidoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/pedidos",
     *     summary="Lista pedidos",
     *     description="Retorna uma lista de pedidos com seus produtos associados. Os resultados são armazenados em cache por 60 segundos.",
     *     operationId="listarPedidos",
     *     tags={"Pedidos"},
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
     *         description="Lista de pedidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="pedidos", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estado", type="string", example="aberto"),
     *                     @OA\Property(property="preco_total", type="number", format="float", example=90),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="categoria_id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                         @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="pivot", type="object",
     *                             @OA\Property(property="pedido_id", type="integer", example=1),
     *                             @OA\Property(property="produto_id", type="integer", example=1),
     *                             @OA\Property(property="preco", type="number", format="float", example=45),
     *                             @OA\Property(property="quantidade", type="integer", example=2)
     *                         )
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
     * Exibe uma lista paginada de pedidos.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo a lista de pedidos ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação dos pedidos.
     */
    public function index(Request $request)
    {
        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                // Validação de parâmetros
                $itensPorPagina = $request->query('itens_por_pagina', 10);
                $ordenarPor = $request->query('ordenar_por', 'id');
                $ordem = $request->query('ordem', 'asc');
                $pagina = $request->query('pagina', 1);

                // Tempo de cache em segundos
                $cacheTempo = 60;

                // Chave do cache
                $cacheKey = 'pedidos_' . $itensPorPagina . '_' . $ordenarPor . '_' . $ordem . '_pagina_' . $pagina;

                // Recupera pedidos do cache ou do banco de dados
                $pedidos = Cache::tags(['pedidos'])->remember($cacheKey, $cacheTempo, function () use ($itensPorPagina, $ordenarPor, $ordem, $pagina) {
                    return Pedido::with('produtos')->orderBy($ordenarPor, $ordem)->paginate($itensPorPagina, ['*'], 'page', $pagina);
                });

                // Ajustar os preços no pivot para float
                $pedidos->each(function ($pedido) {
                    $pedido->preco_total = (float) $pedido->preco_total;
                    $pedido->produtos->each(function ($produto) {
                        $produto->pivot->preco = (float) $produto->pivot->preco;
                    });
                });

                // Preparar resposta
                $response = [
                    'sucesso' => true,
                    'mensagem_erro' => null,
                    'dados' => [
                        'pedidos' => $pedidos->items(),
                        'ultima_pagina' => $pedidos->lastPage(),
                        'total_itens' => $pedidos->total(),
                        'filtro' => [
                            'itens_por_pagina' => $itensPorPagina,
                            'ordenar_por' => $ordenarPor,
                            'ordem' => $ordem,
                            'pagina' => $pedidos->currentPage(),
                        ],
                    ],
                ];

                event(new listarPedido($pedidos->items()));

                return response()->json($response);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao listar pedidos', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                        'dados' => null,
                    ];

                    return response()->json($response, 500);
                }
            }
        }
    }

    /**
     * @OA\Post(
     *     path="/pedidos",
     *     summary="Criar novo pedido",
     *     description="Cria um novo pedido. O cache de pedidos é limpo após a criação.",
     *     operationId="criarPedido",
     *     tags={"Pedidos"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="produtos", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="produto_id", type="integer", example=1),
     *                     @OA\Property(property="quantidade", type="integer", example=2),
     *                     @OA\Property(property="preco", type="number", format="float", example=45)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pedido criado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="pedido", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estado", type="string", example="aberto"),
     *                     @OA\Property(property="preco_total", type="number", format="float", example=90),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="categoria_id", type="integer", example=1),
     *                             @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                             @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                             @OA\Property(property="pivot", type="object",
     *                                 @OA\Property(property="pedido_id", type="integer", example=1),
     *                                 @OA\Property(property="produto_id", type="integer", example=1),
     *                                 @OA\Property(property="preco", type="number", format="float", example=45),
     *                                 @OA\Property(property="quantidade", type="integer", example=2)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Erro de validação."),
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
     * Armazena um novo pedido no banco de dados.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o novo pedido ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a criação do pedido.
     */
    public function store(Request $request)
    {
        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                // Validação dos dados da requisição
                $validatedData = $request->validate([
                    'produtos' => 'required|array',
                    'produtos.*.produto_id' => 'required|integer|exists:produtos,id',
                    'produtos.*.quantidade' => 'required|integer|min:1',
                    'produtos.*.preco' => 'required|numeric|min:0',
                ], [
                    'produtos.required' => 'O campo produtos é obrigatório.',
                    'produtos.array' => 'O campo produtos deve ser um array.',
                    'produtos.*.produto_id.required' => 'O campo produto_id é obrigatório.',
                    'produtos.*.produto_id.integer' => 'O campo produto_id deve ser um inteiro.',
                    'produtos.*.produto_id.exists' => 'O produto especificado não existe.',
                    'produtos.*.quantidade.required' => 'O campo quantidade é obrigatório.',
                    'produtos.*.quantidade.integer' => 'O campo quantidade deve ser um inteiro.',
                    'produtos.*.quantidade.min' => 'O campo quantidade deve ser no mínimo 1.',
                    'produtos.*.preco.required' => 'O campo preco é obrigatório.',
                    'produtos.*.preco.numeric' => 'O campo preco deve ser numérico.',
                    'produtos.*.preco.min' => 'O campo preco deve ser no mínimo 0.',
                ]);

                // Criar o pedido com estado padrão 'aberto'
                $pedido = Pedido::create(['estado' => 'aberto']);
                $precoTotal = 0;

                // Adicionar produtos ao pedido e calcular o preço total
                foreach ($validatedData['produtos'] as $produto) {
                    $precoTotal += $produto['preco'] * $produto['quantidade'];
                    $pedido->produtos()->attach($produto['produto_id'], [
                        'quantidade' => $produto['quantidade'],
                        'preco' => (float) $produto['preco'],
                    ]);
                }

                // Atualizar o pedido com o preço total
                $pedido->update(['preco_total' => $precoTotal]);

                // Limpar o cache após criar um novo pedido
                Cache::tags(['pedidos'])->flush();

                // Carregar os produtos com o pivot ajustado
                $pedido->load(['produtos' => function ($query) {
                    $query->select('produtos.*', 'produto_pedido.preco as pivot_preco', 'produto_pedido.quantidade as pivot_quantidade')
                        ->withPivot('preco', 'quantidade');
                }]);

                // Ajustar o preço no pivot para float
                $pedido->produtos->each(function ($produto) {
                    $produto->pivot->preco = (float) $produto->pivot->preco;
                });

                // Preparar resposta
                $response = [
                    'sucesso' => true,
                    'mensagem_erro' => null,
                    'dados' => [
                        'pedido' => $pedido,
                    ],
                ];

                event(new criarPedido($pedido));

                return response()->json($response, 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response = [
                    'sucesso' => false,
                    'mensagem_erro' => is_array($errors) ? implode(', ', $errors) : $errors,
                    'dados' => null,
                ];

                return response()->json($response, 422);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao criar pedido', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                        'dados' => null,
                    ];

                    return response()->json($response, 500);
                }
            }
        }
    }

    /**
     * @OA\Get(
     *     path="/pedidos/{id}",
     *     summary="Mostrar pedido",
     *     description="Retorna um pedido específico. O resultado é armazenado em cache por 60 segundos.",
     *     operationId="visualizarPedido",
     *     tags={"Pedidos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID do pedido"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhes do pedido",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="pedido", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estado", type="string", example="aberto"),
     *                     @OA\Property(property="preco_total", type="number", format="float", example=90),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="categoria_id", type="integer", example=1),
     *                             @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                             @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                             @OA\Property(property="pivot", type="object",
     *                                 @OA\Property(property="pedido_id", type="integer", example=1),
     *                                 @OA\Property(property="produto_id", type="integer", example=1),
     *                                 @OA\Property(property="preco", type="number", format="float", example=45),
     *                                 @OA\Property(property="quantidade", type="integer", example=2)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pedido não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Pedido não encontrado."),
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
     * Exibe um pedido específico.
     *
     * @param int $id ID do pedido.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o pedido ou uma mensagem de erro.
     * 
     * @throws \Exception Se ocorrer um erro durante a recuperação do pedido.
     */
    public function show($id)
    {
        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                // Recupera o pedido pelo ID
                $pedido = Pedido::find($id);

                // Verifica se o pedido foi encontrado
                if (!$pedido) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Pedido não encontrado.',
                        'dados' => null,
                    ];

                    return response()->json($response, 404);
                }

                // Chave do cache
                $cacheKey = 'pedido_' . $pedido->id;

                // Recupera o pedido do cache ou do banco de dados
                $pedido = Cache::tags(['pedidos'])->remember($cacheKey, 60, function () use ($pedido) {
                    return $pedido->load('produtos');
                });

                // Ajustar os preços no pivot e preco_total para float
                $pedido->preco_total = (float) $pedido->preco_total;
                $pedido->produtos->each(function ($produto) {
                    $produto->pivot->preco = (float) $produto->pivot->preco;
                });

                // Preparar resposta
                $response = [
                    'sucesso' => true,
                    'mensagem_erro' => null,
                    'dados' => [
                        'pedido' => $pedido,
                    ],
                ];

                return response()->json($response, 200);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao exibir pedido', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                        'dados' => null,
                    ];

                    return response()->json($response, 500);
                }
            }
        }
    }

    /**
     * @OA\Put(
     *     path="/pedidos/{id}",
     *     summary="Atualiza pedido",
     *     description="Atualiza um pedido específico. O cache de pedidos é limpo após a atualização.",
     *     operationId="atualizarPedido",
     *     tags={"Pedidos"}, 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="estado", type="string", enum={"aprovado", "concluido", "cancelado"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pedido atualizado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=true),
     *             @OA\Property(property="mensagem_erro", type="null", nullable=true, example=null),
     *             @OA\Property(property="dados", type="object",
     *                 @OA\Property(property="pedido", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estado", type="string", example="aprovado"),
     *                     @OA\Property(property="preco_total", type="number", format="float", example=90),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                     @OA\Property(property="produtos", type="array", @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="categoria_id", type="integer", example=1),
     *                         @OA\Property(property="nome", type="string", example="Pizza de Strogonoff 😋"),
     *                         @OA\Property(property="preco", type="number", format="float", example=47.77),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-11T05:58:09.000000Z"),
     *                         @OA\Property(property="pivot", type="object",
     *                             @OA\Property(property="pedido_id", type="integer", example=1),
     *                             @OA\Property(property="produto_id", type="integer", example=1),
     *                             @OA\Property(property="preco", type="number", format="float", example=45),
     *                             @OA\Property(property="quantidade", type="integer", example=2)
     *                         )
     *                     ))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Não é possível alterar um pedido cancelado para outro estado."),
     *             @OA\Property(property="dados", type="null", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pedido não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucesso", type="boolean", example=false),
     *             @OA\Property(property="mensagem_erro", type="string", example="Pedido não encontrado."),
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
     * Atualiza o estado de um pedido específico.
     *
     * @param \Illuminate\Http\Request $request Objeto da requisição HTTP.
     * @param int $id ID do pedido.
     * 
     * @return \Illuminate\Http\JsonResponse Resposta JSON contendo o pedido atualizado ou uma mensagem de erro.
     * 
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados falhar.
     * @throws \Exception Se ocorrer um erro durante a atualização do pedido.
     */
    public function update(Request $request, $id)
    {
        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                // Recupera o pedido pelo ID
                $pedido = Pedido::find($id);

                // Verifica se o pedido foi encontrado
                if (!$pedido) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Pedido não encontrado.',
                        'dados' => null,
                    ];

                    return response()->json($response, 404);
                }

                // Validação dos dados
                $validatedData = $request->validate([
                    'estado' => 'in:aprovado,concluido,cancelado',
                ]);

                // Verificar se o estado já está atualizado
                if ($pedido->estado === $validatedData['estado']) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'O estado já está atualizado.',
                        'dados' => null,
                    ];

                    return response()->json($response, 400);
                }

                // Validações adicionais
                if ($pedido->estado === 'concluido' && $validatedData['estado'] === 'aprovado') {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Não é possível alterar um pedido concluído para aprovado.',
                        'dados' => null,
                    ];

                    return response()->json($response, 400);
                }

                if ($pedido->estado === 'cancelado' && in_array($validatedData['estado'], ['aprovado', 'concluido'])) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Não é possível alterar um pedido cancelado para outro estado.',
                        'dados' => null,
                    ];

                    return response()->json($response, 400);
                }

                // Atualiza o estado do pedido
                $pedido->update($validatedData);
                Cache::tags(['pedidos'])->flush();

                // Ajustar os preços no pivot e preco_total para float
                $pedido->preco_total = (float) $pedido->preco_total;
                $pedido->produtos->each(function ($produto) {
                    $produto->pivot->preco = (float) $produto->pivot->preco;
                });

                // Preparar resposta
                $response = [
                    'sucesso' => true,
                    'mensagem_erro' => null,
                    'dados' => [
                        'pedido' => $pedido,
                    ],
                ];

                event(new atualizarPedido($pedido));

                return response()->json($response, 200);
            } catch (\Exception $e) {
                // Log detalhado do erro
                Log::channel('apis')->error('Erro ao atualizar pedido', [
                    'mensagem' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'tentativa' => ($i + 1),
                ]);

                // Se for a última tentativa, retornar o erro
                if ($i === $tentativas - 1) {
                    $response = [
                        'sucesso' => false,
                        'mensagem_erro' => 'Algo deu errado. Tente novamente mais tarde.',
                        'dados' => null,
                    ];

                    return response()->json($response, 500);
                }
            }
        }
    }
}
