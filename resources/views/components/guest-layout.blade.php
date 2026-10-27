<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-zinc-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'NUME' }} | Plataforma Audiovisual</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}">
        @include('partials.favicons')
</head>
    <body class="bg-zinc-50 text-zinc-900 flex min-h-screen items-center justify-center p-6 antialiased selection:bg-orange-500 selection:text-white">
        <div class="w-full max-w-sm space-y-6">
            <div class="text-center">
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center transition hover:opacity-90">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-10 w-auto object-contain mx-auto">
                </a>
            </div>

            <div class="bg-white border border-zinc-200/80 shadow-xl rounded-2xl p-6 sm:p-8 space-y-4">
                @if(isset($title) && $title)
                    <div class="border-b border-zinc-100 pb-3">
                        <h1 class="text-lg font-extrabold text-zinc-900">{{ $title }}</h1>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
