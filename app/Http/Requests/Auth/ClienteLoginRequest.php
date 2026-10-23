<?php

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClienteLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_slug' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $slug = $this->filled('tenant_slug') ? $this->string('tenant_slug')->toString() : 'nume';
        $tenant = Tenant::where('slug', $slug)->first() ?? Tenant::first();

        if (! $tenant || ! Auth::guard('cliente')->attempt([
            'tenant_id' => $tenant->id,
            'email' => $this->string('email')->lower()->toString(),
            'password' => $this->input('password'),
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $slug = $this->filled('tenant_slug') ? $this->string('tenant_slug')->toString() : 'nume';
        return Str::transliterate(Str::lower($slug.'|'.$this->string('email')).'|'.$this->ip());
    }
}
