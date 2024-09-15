<?php

namespace App\Http\Controllers;

use App\Events\atualizarPedido;
use App\Events\criarPedido;
use App\Events\listarPedido;
use App\Models\Pedido;
use App\Services\PedidoServiceInterface;
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
    protected $pedidoService;

    public function __construct(PedidoServiceInterface $pedidoService)
    {
        $this->pedidoService = $pedidoService;
    }

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
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $pedidos = $this->pedidoService->listarPedidos($request);

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'pedidos' => $pedidos->items(),
                    'ultima_pagina' => $pedidos->lastPage(),
                    'total_itens' => $pedidos->total(),
                    'filtro' => [
                        'itens_por_pagina' => $pedidos->perPage(),
                        'ordenar_por' => $request->query('ordenar_por', 'id'),
                        'ordem' =>  $request->query('ordem', 'asc'),
                        'pagina' => $pedidos->currentPage(),
                    ],
                ];

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
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
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
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $pedido = $this->pedidoService->criarPedido($request);

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'pedido' => $pedido,
                ];

                return response()->json($response, 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response['mensagem_erro'] = is_array($errors) ? implode(', ', $errors) : $errors;
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
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
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
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $pedido = $this->pedidoService->mostrarPedido($id);

                if (!$pedido) {
                    $response['mensagem_erro'] = 'Pedido não encontrado.';
                    return response()->json($response, 404);
                }

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'pedido' => $pedido,
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
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
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
        // Preparar resposta
        $response = [
            'sucesso' => false,
            'mensagem_erro' => 'Erro desconhecido.',
            'dados' => null,
        ];

        $tentativas = 3;
        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $pedido = $this->pedidoService->atualizarPedido($request, $id);

                // Verifica se houve erro na atualização
                if ($pedido['sucesso'] === false) {
                    $response['mensagem_erro'] = $pedido['mensagem_erro'];
                    return response()->json($response, 422);
                }

                // Verifica se a pedido foi encontrada
                if (!$pedido['pedido']) {
                    $response['mensagem_erro'] = 'Pedido não encontrado.';
                    return response()->json($response, 404);
                }

                $response['sucesso'] = true;
                $response['mensagem_erro'] = null;
                $response['dados'] = [
                    'pedido' => $pedido['pedido'],
                ];

                return response()->json($response, 200);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Resposta de erro de validação
                $errors = collect($e->errors())->flatten()->first();
                $response['mensagem_erro'] = is_array($errors) ? implode(', ', $errors) : $errors;
                return response()->json($response, 422);
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
                    $response['mensagem_erro'] = 'Algo deu errado. Tente novamente mais tarde.';
                    return response()->json($response, 500);
                }
            }
        }
        return response()->json($response, 500);
    }
}
