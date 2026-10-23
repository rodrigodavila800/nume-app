<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tenant_slug' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
        ]);

        $tenantId = Tenant::where('slug', $request->string('tenant_slug'))->value('id');

        if (! $tenantId) {
            return back()->withInput($request->only('tenant_slug', 'email'))
                ->withErrors(['email' => trans('passwords.user')]);
        }

        $status = Password::sendResetLink(
            ['email' => $request->string('email')->lower()->toString(), 'tenant_id' => $tenantId]
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('tenant_slug', 'email'))
                        ->withErrors(['email' => __($status)]);
    }
}
