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
    ],

];
