<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialProfile;
use App\Models\VideoPublication;
use App\Services\UploadPost\SocialAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UploadPostWebhookController extends Controller
{
    /**
     * Processa notificações em tempo real enviadas pela Upload-Post.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Upload-Post-Signature');

        $secret = Setting::get('upload_post_webhook_secret') ?: config('upload_post.webhook_secret');

        // Validação de assinatura HMAC-SHA256 (Timing attack safe)
        if (! empty($secret)) {
            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expectedSignature, (string) $signature)) {
                Log::warning('upload_post.webhook_rejected_signature', [
                    'ip' => $request->ip(),
                    'provided_signature' => $signature,
                ]);

                return response()->json(['error' => 'Assinatura inválida.'], 401);
            }
        }

        $data = $request->all();
        $event = $data['event'] ?? $data['type'] ?? 'upload_status';

        Log::info('upload_post.webhook_received', [
            'event' => $event,
            'request_id' => $data['request_id'] ?? null,
            'user' => $data['user'] ?? null,
        ]);

        // 1. Atualização de status de upload/publicação
        if (! empty($data['request_id'])) {
            $this->handleUploadStatus($data);
        }

        // 2. Atualização de conexão de contas sociais
        if (! empty($data['user']) && isset($data['social_accounts'])) {
            $this->handleAccountConnection($data);
        }

        return response()->json(['status' => 'processed', 'event' => $event]);
    }

    private function handleUploadStatus(array $data): void
    {
        $requestId = $data['request_id'];
        $status = strtolower($data['status'] ?? 'completed');

        $publication = VideoPublication::where('upload_post_request_id', $requestId)->first();

        if (! $publication) {
            Log::info('upload_post.webhook_publication_not_found', ['request_id' => $requestId]);
            return;
        }

        $mappedStatus = match ($status) {
            'completed', 'success', 'published' => 'publicado',
            'failed', 'error' => 'falhou',
            default => 'processando',
        };

        $publication->update([
            'status' => $mappedStatus,
            'resultado' => $data,
            'publicado_em' => $mappedStatus === 'publicado' ? ($publication->publicado_em ?? now()) : null,
        ]);

        // Se tiver vídeo associado e status for publicado, atualiza o vídeo
        if ($mappedStatus === 'publicado' && $publication->video && $publication->video->status !== 'published') {
            $publication->video->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }

    private function handleAccountConnection(array $data): void
    {
        $username = $data['user'];
        $accounts = $data['social_accounts'] ?? [];

        $profile = SocialProfile::where('upload_post_username', $username)->first();

        if (! $profile) {
            return;
        }

        // Plataforma com valor nulo/vazio ou `connected: false` não conta como conectada.
        $connected = SocialAccounts::connected($accounts);
        $connectedPlatforms = array_keys($connected);

        $profile->update([
            'connected' => ! empty($connectedPlatforms),
            'platforms_connected' => $connectedPlatforms,
            'account_details' => $connected,
            'last_synced_at' => now(),
        ]);
    }
}
