@php $isEs = str_starts_with(app()->getLocale(), 'es'); @endphp
{{-- Aviso de cookies (RGPD/LSSI). Só "conteúdo externo" (YouTube, Vimeo) é opcional e começa desligado. --}}
<link rel="stylesheet" href="{{ asset('css/cookie-consent.css') }}?v={{ file_exists(public_path('css/cookie-consent.css')) ? filemtime(public_path('css/cookie-consent.css')) : '1' }}">
<div id="nume-cookie-consent" class="nume-consent" role="dialog" aria-modal="false" aria-labelledby="nume-consent-title" hidden
     data-embed-text="{{ $isEs ? 'Este vídeo se carga desde un servicio externo (YouTube o Vimeo), que puede usar cookies.' : 'Este vídeo é carregado de um serviço externo (YouTube ou Vimeo), que pode usar cookies.' }}"
     data-embed-accept="{{ $isEs ? 'Aceptar y ver' : 'Aceitar e assistir' }}"
     data-embed-youtube="{{ $isEs ? 'Ver en YouTube' : 'Ver no YouTube' }}"
     data-embed-vimeo="{{ $isEs ? 'Ver en Vimeo' : 'Ver no Vimeo' }}">
    <p id="nume-consent-title" class="nume-consent-title">{{ $isEs ? 'Cookies y contenido externo' : 'Cookies e conteúdo externo' }}</p>
    <p class="nume-consent-text">
        {{ $isEs
            ? 'Usamos solo las cookies necesarias para que la web funcione. Los vídeos de YouTube y Vimeo son contenido externo y solo se cargan si lo aceptas.'
            : 'Usamos só os cookies necessários para o site funcionar. Os vídeos do YouTube e do Vimeo são conteúdo externo e só carregam se você aceitar.' }}
        <a href="{{ route('legal.cookies') }}">{{ $isEs ? 'Política de cookies' : 'Política de cookies' }}</a>
    </p>

    <div class="nume-consent-prefs" data-consent-preferences hidden>
        <label class="nume-consent-option">
            <input type="checkbox" checked disabled>
            <span><strong>{{ $isEs ? 'Necesarias' : 'Necessários' }}</strong> · {{ $isEs ? 'sesión, seguridad y tu elección (siempre activas)' : 'sessão, segurança e a sua escolha (sempre ativos)' }}</span>
        </label>
        <label class="nume-consent-option">
            <input type="checkbox" data-consent-external>
            <span><strong>{{ $isEs ? 'Contenido externo' : 'Conteúdo externo' }}</strong> · {{ $isEs ? 'reproductores de YouTube y Vimeo' : 'players do YouTube e do Vimeo' }}</span>
        </label>
        <button type="button" class="nume-consent-btn nume-consent-btn-primary" data-consent-save>{{ $isEs ? 'Guardar elección' : 'Salvar escolha' }}</button>
    </div>

    <div class="nume-consent-actions">
        <button type="button" class="nume-consent-btn" data-consent-configure>{{ $isEs ? 'Configurar' : 'Configurar' }}</button>
        <button type="button" class="nume-consent-btn" data-consent-reject>{{ $isEs ? 'Rechazar' : 'Rejeitar' }}</button>
        <button type="button" class="nume-consent-btn nume-consent-btn-primary" data-consent-accept>{{ $isEs ? 'Aceptar' : 'Aceitar' }}</button>
    </div>
</div>
<script src="{{ asset('js/cookie-consent.js') }}?v={{ file_exists(public_path('js/cookie-consent.js')) ? filemtime(public_path('js/cookie-consent.js')) : '1' }}" defer></script>
