<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SofiTranslatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(
        protected SofiTranslatorService $translator
    ) {}

    /**
     * Endpoint da API Sofi Translator para tradução instantânea com cache.
     *
     * POST /api/v1/translate
     * Payload: { "text": "...", "to": "es", "from": "pt_BR" }
     * Ou lote: { "texts": ["..."], "to": "es", "from": "pt_BR" }
     */
    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:5000',
            'texts' => 'nullable|array',
            'texts.*' => 'string|max:5000',
            'to' => 'nullable|string|in:es,pt_BR,es_ES',
            'from' => 'nullable|string|in:es,pt_BR,es_ES',
        ]);

        $targetLocale = $validated['to'] ?? app()->getLocale() ?: 'es';
        $sourceLocale = $validated['from'] ?? ($targetLocale === 'es' ? 'pt_BR' : 'es');

        if (!empty($validated['texts'])) {
            $translations = $this->translator->translateBatch($validated['texts'], $targetLocale, $sourceLocale);
            return response()->json([
                'success' => true,
                'translations' => $translations,
                'target' => $targetLocale,
                'source' => $sourceLocale,
            ]);
        }

        $text = $validated['text'] ?? '';
        $translation = $this->translator->translate($text, $targetLocale, $sourceLocale);

        return response()->json([
            'success' => true,
            'original' => $text,
            'translation' => $translation,
            'target' => $targetLocale,
            'source' => $sourceLocale,
        ]);
    }
}
