<?php

/*
|--------------------------------------------------------------------------
| Sofi IA — integração com o Laravel AI SDK (piloto)
|--------------------------------------------------------------------------
|
| Enquanto `use_ai_sdk` for false, nada muda: os services continuam usando
| a chamada HTTP direta ao Gemini. Ao ligar, os services migrados passam a
| usar os Agents em app/Ai/Agents, mantendo o fallback heurístico.
|
| Migrado até agora: SofiTranslatorService.
|
*/

return [

    'use_ai_sdk' => (bool) env('SOFI_USE_AI_SDK', false),

    // Driver do provedor no SDK: 'gemini' ou 'anthropic' (outros: ver docs do laravel/ai).
    'provider' => env('SOFI_AI_PROVIDER', 'gemini'),

    // Nome exato do modelo. Vazio = padrão do provedor definido em config/ai.php.
    'model' => env('SOFI_AI_MODEL'),

    'timeout' => (int) env('SOFI_AI_TIMEOUT', 15),

    'keys' => [
        'gemini' => env('GEMINI_API_KEY'),
        'anthropic' => env('ANTHROPIC_API_KEY'),
    ],

    // Limites do chat público da vitrine (cada mensagem é uma chamada paga ao modelo).
    'chat_rate_limits' => [
        'per_minute' => (int) env('SOFI_CHAT_PER_MINUTE', 6),
        'per_hour_ip' => (int) env('SOFI_CHAT_PER_HOUR_IP', 40),
        'per_hour_tenant' => (int) env('SOFI_CHAT_PER_HOUR_TENANT', 400),
        'reset_per_minute' => (int) env('SOFI_CHAT_RESET_PER_MINUTE', 5),
    ],

    // Limites do chat da Sofi no portal, por cliente logado.
    'portal_chat_rate_limits' => [
        'per_minute' => (int) env('SOFI_PORTAL_CHAT_PER_MINUTE', 10),
        'per_hour' => (int) env('SOFI_PORTAL_CHAT_PER_HOUR', 60),
    ],

    // Orçamento mensal de tokens (input + output). Zero = sem limite.
    'budgets' => [
        'monthly_tokens_per_tenant' => (int) env('SOFI_MONTHLY_TOKENS_PER_TENANT', 0),
        'monthly_tokens_per_cliente' => (int) env('SOFI_MONTHLY_TOKENS_PER_CLIENTE', 0),
    ],

];
