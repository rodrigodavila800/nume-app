@extends('layouts.portal')

@php
    $titles = [
        'es' => ['privacy' => 'Política de privacidad', 'cookies' => 'Política de cookies', 'notice' => 'Aviso legal'],
        'pt' => ['privacy' => 'Política de privacidade', 'cookies' => 'Política de cookies', 'notice' => 'Aviso legal'],
    ];
    $routes = ['privacy' => 'legal.privacy', 'cookies' => 'legal.cookies', 'notice' => 'legal.notice'];
    $pending = $lang === 'es' ? 'pendiente de configurar' : 'pendente de configuração';
@endphp

@section('title', $titles[$lang][$page] . ' | ' . $identity['company'])

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <header class="border-b border-zinc-200 pb-5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100 text-orange-800 border border-orange-200">
                {{ $lang === 'es' ? 'RGPD · LOPDGDD · LSSI' : 'RGPD · LOPDGDD · LSSI (Espanha)' }}
            </span>
            <nav class="flex items-center gap-1 text-xs font-bold" aria-label="{{ $lang === 'es' ? 'Idioma' : 'Idioma' }}">
                <a href="{{ route('lang.switch', 'es') }}" @class(['px-2.5 py-1 rounded-lg', 'bg-zinc-900 text-white' => $lang === 'es', 'text-zinc-600 hover:bg-zinc-100' => $lang !== 'es']) hreflang="es">ES</a>
                <a href="{{ route('lang.switch', 'pt_BR') }}" @class(['px-2.5 py-1 rounded-lg', 'bg-zinc-900 text-white' => $lang === 'pt', 'text-zinc-600 hover:bg-zinc-100' => $lang !== 'pt']) hreflang="pt-BR">PT</a>
            </nav>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ $titles[$lang][$page] }}</h1>
        <p class="text-xs text-zinc-500">{{ $lang === 'es' ? 'Última actualización' : 'Última atualização' }}: {{ $updatedAt }}</p>
        <nav class="flex flex-wrap gap-2 text-xs font-semibold" aria-label="{{ $lang === 'es' ? 'Textos legales' : 'Textos legais' }}">
            @foreach ($routes as $key => $route)
                <a href="{{ route($route) }}" @class(['px-3 py-1.5 rounded-full border', 'border-orange-300 bg-orange-50 text-orange-800' => $key === $page, 'border-zinc-200 text-zinc-600 hover:border-zinc-300' => $key !== $page])>{{ $titles[$lang][$key] }}</a>
            @endforeach
        </nav>
    </header>

    <div class="space-y-4 text-sm text-zinc-700 leading-relaxed">
        @include('legal.' . $lang . '.' . $page, ['pending' => $pending])
    </div>
</div>
@endsection
