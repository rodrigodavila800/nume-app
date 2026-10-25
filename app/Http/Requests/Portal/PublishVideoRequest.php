<?php

namespace App\Http\Requests\Portal;

use App\Enums\PlataformaAlvo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = auth('cliente')->user();

        if (! $cliente) {
            return false;
        }

        // Permite disparar se possuir perfil conectado
        if ($cliente->socialProfile?->connected !== true) {
            return false;
        }

        // Governança de Cota & Monetização: exige cota disponível ou Add-on contratado
        return $cliente->canDistributeSocial();
    }

    protected function failedAuthorization(): void
    {
        $cliente = auth('cliente')->user();

        if ($cliente && $cliente->socialProfile?->connected !== true) {
            throw new \Illuminate\Auth\Access\AuthorizationException(__('Por favor, conecte suas redes sociais antes de realizar publicações.'));
        }

        throw new \Illuminate\Auth\Access\AuthorizationException(__('Você atingiu o limite de publicações do seu plano. Adquira o Add-on de Distribuição Automática para envios ilimitados.'));
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $video = $this->route('video');
            $hasSourceFile = $video instanceof \App\Models\Video && $video->hasSourceFile();

            if (! $hasSourceFile && blank($this->input('video_url'))) {
                $validator->errors()->add('video_url', __('Informe a URL pública do vídeo.'));
            }
        });
    }

    public function rules(): array
    {
        return [
            // URL pública do vídeo (CDN / S3 / MP4). Dispensável quando o vídeo é um arquivo da NUME:
            // nesse caso a URL de busca é gerada no servidor e a digitada é ignorada.
            'video_url' => ['nullable', 'url', 'max:2048'],
            'titulo' => ['required', 'string', 'min:3', 'max:150'],
            'plataformas' => ['required', 'array', 'min:1', 'max:8'],
            'plataformas.*' => ['string', Rule::in(PlataformaAlvo::values())],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'video_url.required' => 'A URL do vídeo é obrigatória para a distribuição.',
            'video_url.url' => 'A URL informada para o vídeo não é válida.',
            'titulo.required' => 'Informe o título do post.',
            'plataformas.required' => 'Selecione ao menos uma rede social para publicar.',
            'plataformas.*.in' => 'Uma ou mais plataformas selecionadas não são suportadas.',
        ];
    }
}
