<?php

return [
    // Chave da API Upload-Post — lida via .env ou das configurações criptografadas no banco (Setting)
    'api_key' => env('UPLOAD_POST_API_KEY'),

    'base_url' => env('UPLOAD_POST_BASE_URL', 'https://api.upload-post.com/api'),

    // URL para onde o cliente volta após conectar as redes sociais no fluxo seguro
    'redirect_url' => env('UPLOAD_POST_REDIRECT_URL', env('APP_URL', 'http://localhost') . '/portal/redes-sociais/conectado'),

    // Logotipo da NUME / Produtora exibido na tela de conexão white-label
    'logo_image' => env('UPLOAD_POST_LOGO_URL'),

    // Secret para validação de assinatura HMAC-SHA256 dos webhooks
    'webhook_secret' => env('UPLOAD_POST_WEBHOOK_SECRET'),

    // Driver padrão de publicação: 'upload_post' (multi-rede unificado), 'meta_graph', 'youtube_data', 'tiktok_native'
    'driver' => env('SOCIAL_PUBLISHER_DRIVER', 'upload_post'),

    // Timeout de rede (segundos)
    'timeout' => (int) env('UPLOAD_POST_TIMEOUT', 20),
];
