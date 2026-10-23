<x-guest-layout>
    <x-slot name="title">Confirme sua senha</x-slot>

    <p class="mb-4 text-sm text-[#706f6c]">
        Esta é uma área protegida. Confirme sua senha antes de continuar.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="block text-sm font-medium text-[#706f6c]">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="mt-1 block w-full rounded-sm border border-[#e3e3e0] px-3 py-2 text-sm focus:border-[#f53003] focus:outline-none">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-sm bg-[#1b1b18] px-5 py-2 text-sm text-white hover:bg-black">
            Confirmar
        </button>
    </form>
</x-guest-layout>
