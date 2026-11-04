<?php

namespace App\Http\Controllers;

use App\Support\LegalIdentity;
use Illuminate\View\View;

/**
 * Páginas legais públicas (aviso legal, privacidade, cookies, termos e exclusão de dados), em espanhol ou português conforme o
 * idioma da sessão, com o titular lido das configurações fiscais (LegalIdentity).
 */
class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return $this->page('privacy');
    }

    public function cookies(): View
    {
        return $this->page('cookies', [
            'sessionCookie' => (string) config('session.cookie'),
            'sessionMinutes' => (int) config('session.lifetime'),
        ]);
    }

    public function notice(): View
    {
        return $this->page('notice');
    }

    public function terms(): View
    {
        return $this->page('terms');
    }

    public function dataDeletion(): View
    {
        return $this->page('data-deletion');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $name, array $data = []): View
    {
        return view('legal.page', $data + [
            'page' => $name,
            'lang' => str_starts_with(app()->getLocale(), 'es') ? 'es' : 'pt',
            'identity' => LegalIdentity::current(),
            'updatedAt' => '04/11/2026',
        ]);
    }
}
