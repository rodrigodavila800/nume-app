<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiTranslatorAgent;
use App\Models\AiUsageLog;
use App\Services\SofiTranslatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\TextResponse;
use Tests\TestCase;

class RecordAiUsageTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'Nosso roteiro de produção, texto fora do dicionário';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'anthropic', 'sofi.model' => 'claude-haiku-5-5', 'sofi.keys.anthropic' => 'chave-de-teste']);
    }

    public function test_records_tokens_driver_and_model_without_content(): void
    {
        SofiTranslatorAgent::fake([
            new TextResponse('Nuestro guion', new TextUsage(120, 30, cacheReadInputTokens: 40, cacheWriteInputTokens: 8), new Meta),
        ])->preventStrayPrompts();

        $this->assertSame('Nuestro guion', app(SofiTranslatorService::class)->translate(self::TEXT, 'es'));

        $log = AiUsageLog::sole();
        $this->assertNull($log->tenant_id);
        $this->assertSame(SofiTranslatorAgent::class, $log->agent);
        $this->assertSame('anthropic', $log->provider);
        $this->assertSame('claude-haiku-5-5', $log->model);
        $this->assertSame(120, $log->input_tokens);
        $this->assertSame(30, $log->output_tokens);
        $this->assertSame(40, $log->cache_read_input_tokens);
        $this->assertSame(8, $log->cache_write_input_tokens);
        $this->assertNotNull($log->invocation_id);
        $this->assertStringNotContainsString('guion', json_encode($log->getAttributes()));
    }

    public function test_failure_to_record_never_breaks_the_ai_response(): void
    {
        Schema::drop('ai_usage_logs');
        Log::spy();

        SofiTranslatorAgent::fake(['Nuestro guion'])->preventStrayPrompts();

        $this->assertSame('Nuestro guion', app(SofiTranslatorService::class)->translate(self::TEXT, 'es'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_starts_with($message, 'RecordAiUsage falhou ao gravar uso: '))->once();
    }
}
