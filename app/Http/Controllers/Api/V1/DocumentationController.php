<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlataformaAlvo;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    /**
     * Renderiza a página de documentação pública estilo docs.upload-post.com.
     */
    public function index(Request $request): View
    {
        $baseUrl = url('/api/v1');
        $platforms = PlataformaAlvo::cases();

        return view('api.docs', compact('baseUrl', 'platforms'));
    }

    /**
     * Download do blueprint do Make.com.
     */
    public function downloadMake(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = resource_path('integrations/make-blueprint-nume-social.json');
        abort_unless(file_exists($path), 404);

        return response()->download($path, 'nume-social-make-blueprint.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Download do workflow do n8n.
     */
    public function downloadN8n(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = resource_path('integrations/n8n-workflow-nume-social.json');
        abort_unless(file_exists($path), 404);

        return response()->download($path, 'nume-social-n8n-workflow.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Retorna a especificação OpenAPI 3.0 em formato JSON.
     */
    public function openApiJson(): JsonResponse
    {
        $platforms = array_column(PlataformaAlvo::cases(), 'value');

        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Nume Social Engine Public API',
                'description' => 'API RESTful White-label para orquestração e distribuição multi-rede de vídeos verticais de alta retenção (Instagram, TikTok, YouTube Shorts, LinkedIn, Facebook, X, Threads, Pinterest).',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Nume Creative SaaS Developer Support',
                    'url' => 'https://nume.app',
                ],
            ],
            'servers' => [
                ['url' => url('/api/v1'), 'description' => 'API Server'],
            ],
            'security' => [
                ['BearerAuth' => []],
                ['ApiKeyAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'nume_live_***',
                    ],
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
            ],
            'paths' => [
                '/social/connect-url' => [
                    'post' => [
                        'summary' => 'Gerar URL White-label de Conexão',
                        'description' => 'Gera uma URL segura JWT para seu cliente final conectar suas redes sociais diretamente.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'cliente@empresa.com'],
                                            'name' => ['type' => 'string', 'example' => 'Alejandro Martínez'],
                                            'redirect_url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://suaplataforma.com/callback'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'URL gerada com sucesso'],
                            '401' => ['description' => 'Não autorizado / Chave de API inválida'],
                        ],
                    ],
                ],
                '/social/publish' => [
                    'post' => [
                        'summary' => 'Publicar Vídeo Multi-rede',
                        'description' => 'Dispara a publicação de um vídeo vertical em uma ou mais redes sociais conectadas do cliente.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['video_url', 'title', 'platforms'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'cliente@empresa.com'],
                                            'client_id' => ['type' => 'integer', 'example' => 12],
                                            'video_url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://storage.nume.app/video.mp4'],
                                            'title' => ['type' => 'string', 'example' => '3 Segredos para Escalar seu Negócio #viral'],
                                            'platforms' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string', 'enum' => $platforms],
                                                'example' => ['instagram', 'tiktok', 'youtube'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Publicação enfileirada e disparada'],
                            '403' => ['description' => 'Limite de quota excedido'],
                        ],
                    ],
                ],
                '/social/publications/{id}' => [
                    'get' => [
                        'summary' => 'Consultar Status da Publicação',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Detalhes e status da publicação'],
                            '404' => ['description' => 'Publicação não encontrada'],
                        ],
                    ],
                ],
                '/social/profiles/{id}' => [
                    'get' => [
                        'summary' => 'Consultar Redes Conectadas do Cliente',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'ID numérico ou e-mail do cliente'],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Perfil e redes conectadas'],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
