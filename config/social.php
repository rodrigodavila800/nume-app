<?php

use App\Services\SocialPublishing\Connectors\MetaConnector;
use App\Services\SocialPublishing\Meta\FacebookReelsPublisher;
use App\Services\SocialPublishing\Meta\InstagramReelsPublisher;
use App\Services\SocialPublishing\YouTube\YouTubeUploader;
use App\Services\SocialPublishing\YouTubeConnector;

/*
|--------------------------------------------------------------------------
| Nume Social Engine
|--------------------------------------------------------------------------
|
| Vídeo próprio da NUME (arquivo enviado) e, nas próximas fases, o roteamento
| de redes sociais por driver. Credenciais nunca ficam aqui: ver config/services.php.
|
*/

return [

    'video' => [
        // Disco (config/filesystems.php) onde ficam os MP4 enviados. Padrão: privado, no servidor.
        'disk' => env('VIDEO_DISK', 'videos'),

        // Tamanho máximo do upload. O PHP (upload_max_filesize, post_max_size) e o nginx
        // (client_max_body_size) do servidor precisam aceitar pelo menos este valor.
        'max_upload_mb' => (int) env('VIDEO_MAX_UPLOAD_MB', 512),

        'allowed_mimes' => ['video/mp4', 'video/quicktime'],
        'allowed_extensions' => ['mp4', 'mov'],

        // Validade das URLs assinadas de reprodução.
        'signed_url_minutes' => (int) env('VIDEO_SIGNED_URL_MINUTES', 120),

        // Validade da URL assinada entregue a serviços externos que buscam o arquivo (hubs, redes).
        'fetch_url_minutes' => (int) env('VIDEO_FETCH_URL_MINUTES', 720),

        // Leitura opcional de largura, altura e duração (se não existir, o upload segue sem metadados).
        'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
    ],

    // Quem publica cada rede: 'native' (app próprio, exige conta conectada; sem ela cai no
    // Upload-Post) ou 'upload_post'. Só libere uma rede quando o app dela estiver aprovado.
    'routing' => [
        'youtube' => env('SOCIAL_ROUTE_YOUTUBE', 'native'),
        'instagram' => env('SOCIAL_ROUTE_INSTAGRAM', 'native'),
        'facebook' => env('SOCIAL_ROUTE_FACEBOOK', 'native'),
        'tiktok' => 'upload_post',
        'linkedin' => 'upload_post',
        'x' => 'upload_post',
        'threads' => env('SOCIAL_ROUTE_THREADS', 'native'),
        'pinterest' => 'upload_post',
    ],

    // Publicador de cada rede no app próprio (classe que implementa NativePublisher).
    // Uma rede só entra aqui quando o envio dela está pronto; a liberação para clientes é o 'routing'.
    'native_publishers' => [
        'youtube' => YouTubeUploader::class,
        'instagram' => InstagramReelsPublisher::class,
        'facebook' => FacebookReelsPublisher::class,
    ],

    // Conectores OAuth do portal (chave usada na rota /portal/redes-sociais/conectar/{chave}).
    'connectors' => [
        'youtube' => YouTubeConnector::class,
        'meta' => MetaConnector::class,
    ],

    // Qual conector conecta cada rede (Instagram e Facebook saem do mesmo login da Meta).
    'platform_connectors' => [
        'youtube' => 'youtube',
        'instagram' => 'meta',
        'facebook' => 'meta',
        'threads' => 'threads',
    ],

    'oauth' => [
        // Validade do state/PKCE guardado na sessão entre a ida ao provedor e o retorno.
        'state_ttl_minutes' => 10,
    ],

    'meta' => [
        // Versão fixa da Graph API (atualizar conscientemente; a Meta desativa versões antigas).
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),

        // Mínimo para publicar Reels na Página e no Instagram profissional ligado a ela.
        'scopes' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'instagram_basic',
            'instagram_content_publish',
        ],

        // Validade da lista de Páginas guardada (criptografada) enquanto o cliente escolhe uma.
        'page_selection_minutes' => 10,

        // Espera do processamento do vídeo pela Meta antes de publicar (a Meta sugere checar
        // por até 5 minutos). Passou disso, o job tenta de novo mais tarde sem reenviar o arquivo.
        'poll_attempts' => 20,
        'poll_seconds' => 15,
    ],

    'youtube' => [
        // Mínimo necessário: enviar vídeos e ler o próprio canal (handle, nome, foto).
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
        ],

        // Privacidade do vídeo enviado. Enquanto o app não passar pela auditoria do Google, o próprio
        // YouTube força "private" em envios pela API; só troque para public depois da aprovação.
        'privacy' => env('SOCIAL_YOUTUBE_PRIVACY', 'private'),

        'category_id' => '22',

        // Tempo máximo de um envio (segundos). O job usa um pouco mais que isso.
        'upload_timeout' => (int) env('SOCIAL_YOUTUBE_UPLOAD_TIMEOUT', 3600),
    ],

];
