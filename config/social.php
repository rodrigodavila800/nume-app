<?php

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
        'instagram' => 'upload_post',
        'facebook' => 'upload_post',
        'tiktok' => 'upload_post',
        'linkedin' => 'upload_post',
        'x' => 'upload_post',
        'threads' => 'upload_post',
        'pinterest' => 'upload_post',
    ],

    'oauth' => [
        // Validade do state/PKCE guardado na sessão entre a ida ao provedor e o retorno.
        'state_ttl_minutes' => 10,
    ],

    'youtube' => [
        // Mínimo necessário: enviar vídeos e ler o próprio canal (handle, nome, foto).
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
        ],
    ],

];
