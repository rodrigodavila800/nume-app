<x-guest-layout>
    <x-slot name="title">Verifique seu e-mail</x-slot>

    <p class="mb-4 text-sm text-[#706f6c]">
        Antes de continuar, verifique seu e-mail clicando no link que enviamos. Se não recebeu, podemos enviar outro.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-green-600">
            Um novo link de verificação foi enviado para o e-mail cadastrado.
        </div>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="rounded-sm bg-[#1b1b18] px-5 py-2 text-sm text-white hover:bg-black">
                Reenviar e-mail de verificação
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-[#706f6c] underline">Sair</button>
        </form>
    </div>
</x-guest-layout>
