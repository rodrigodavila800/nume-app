<x-guest-layout>
    <x-slot name="title">Recuperar senha</x-slot>

    <p class="mb-4 text-sm text-[#706f6c]">
        Informe seu e-mail e enviaremos um link para redefinir sua senha.
    </p>

    @session('status')
        <div class="mb-4 text-sm font-medium text-green-600">
            {{ $value }}
        </div>
    @endsession

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="tenant_slug" class="block text-sm font-medium text-[#706f6c]">Identificador da produtora</label>
            <input id="tenant_slug" type="text" name="tenant_slug" value="{{ old('tenant_slug') }}" required autofocus autocomplete="organization"
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('tenant_slug')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-[#706f6c]">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-sm bg-[#1b1b18] px-5 py-2 text-sm text-white hover:bg-black">
            Enviar link de redefinição
        </button>
    </form>
</x-guest-layout>
