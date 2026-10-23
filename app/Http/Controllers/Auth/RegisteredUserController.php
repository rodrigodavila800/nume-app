<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Cadastro auto-serviço da produtora: cria um novo Tenant (like Udemy/instrutor),
     * sem intervenção manual da Nume. A conexão com o Stripe é feita depois pela própria produtora.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('register')
            ->with('info', __('O cadastro de novas produtoras está temporariamente indisponível. A única produtora ativa no momento é a NUME Creative.'));
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

