<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-950 text-zinc-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Presupuesto Expirado') }} · {{ $tenant?->name ?? 'NUME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full text-center space-y-6 bg-zinc-900/80 border border-zinc-800 rounded-3xl p-8 backdrop-blur-sm">
        <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-center justify-center mx-auto text-amber-400">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-amber-400">
                {{ __('Plazo Vencido') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white mt-1">
                {{ __('Presupuesto Expirado') }}
            </h1>
            <p class="text-xs text-zinc-400 font-mono mt-1">
                #{{ $quote->number }}
            </p>
        </div>

        <p class="text-sm text-zinc-400 leading-relaxed">
            {{ __('El periodo de validez para la aprobación directa de este presupuesto ha concluido. Para revalidar las tarifas o solicitar una nueva propuesta actualizada, por favor contacta con nosotros.') }}
        </p>

        <div class="pt-4 border-t border-zinc-800 text-xs text-zinc-400">
            {{ $tenant?->name ?? 'NUME Creative Studio' }}
        </div>
    </div>
</body>
</html>
