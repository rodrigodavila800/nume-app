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

];
