<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    /**
     * Lista todos os orçamentos e propostas enviados para o cliente autenticado.
     */
    public function index(Request $request): View
    {
        $cliente = $request->user('cliente');

        $quotes = $cliente->allQuotes()
            ->whereIn('status', ['sent', 'accepted', 'rejected'])
            ->latest()
            ->paginate(10);

        return view('portal.quotes.index', compact('quotes', 'cliente'));
    }
}
