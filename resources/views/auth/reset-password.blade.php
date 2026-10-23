<x-guest-layout>
    <x-slot name="title">Redefinir senha</x-slot>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="tenant_slug" class="block text-sm font-medium text-[#706f6c]">Identificador da produtora</label>
            <input id="tenant_slug" type="text" name="tenant_slug" value="{{ old('tenant_slug', $request->tenant_slug) }}" required autofocus autocomplete="organization"
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('tenant_slug')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-[#706f6c]">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-[#706f6c]">Nova senha</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-[#706f6c]">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('password_confirmation')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-sm bg-[#1b1b18] px-5 py-2 text-sm text-white hover:bg-black">
            Redefinir senha
        </button>
    </form>
</x-guest-layout>
