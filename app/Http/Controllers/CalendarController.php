<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\VideoPublication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Exibe o Calendário Geral de Conteúdo e Entregas da Produtora.
     */
    public function index(Request $request): View|JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        // Mês e ano selecionados
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $selectedDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $startOfMonth = $selectedDate->copy()->startOfMonth();
        $endOfMonth = $selectedDate->copy()->endOfMonth();

        // Limites para a grade do calendário (inicia na segunda-feira da semana de início e termina no domingo da semana de fim)
        $calendarStart = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $clienteId = $request->input('cliente_id');

        // 1. Busca publicações de vídeos (agendadas, publicadas, processando)
        $publicationsQuery = VideoPublication::with(['cliente', 'video.production'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($calendarStart, $calendarEnd) {
                $q->whereBetween('scheduled_at', [$calendarStart, $calendarEnd])
                    ->orWhere(function ($sub) use ($calendarStart, $calendarEnd) {
                        $sub->whereNull('scheduled_at')
                            ->whereBetween('created_at', [$calendarStart, $calendarEnd]);
                    });
            });

        if ($clienteId) {
            $publicationsQuery->where('cliente_id', $clienteId);
        }

        $publications = $publicationsQuery->get();

        // 2. Busca prazos de produções audiovisuais
        $productionsQuery = Production::with(['cliente', 'videos'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('target_publication_date')
            ->whereBetween('target_publication_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()]);

        if ($clienteId) {
            $productionsQuery->where('cliente_id', $clienteId);
        }

        $productions = $productionsQuery->get();

        // 3. Agrupa os eventos por data ('Y-m-d')
        $eventsByDate = [];

        foreach ($publications as $pub) {
            $dateKey = ($pub->scheduled_at ?? $pub->publicado_em ?? $pub->created_at)->format('Y-m-d');
            $eventsByDate[$dateKey][] = [
                'id' => 'pub_' . $pub->id,
                'type' => 'publication',
                'title' => $pub->titulo,
                'status' => $pub->status,
                'scheduled_at' => $pub->scheduled_at?->format('H:i'),
                'platforms' => $pub->plataformas ?? [],
                'cliente' => $pub->cliente?->name ?? 'Cliente',
                'company' => $pub->cliente?->company_name,
                'video_url' => $pub->video?->video_url,
                'thumbnail_url' => $pub->video?->thumbnail_url ?: 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?auto=format&fit=crop&w=400&q=80',
                'production_title' => $pub->video?->production?->title,
                'view_url' => $pub->video?->production ? route('productions.show', $pub->video->production) : null,
            ];
        }

        foreach ($productions as $prod) {
            $dateKey = $prod->target_publication_date->format('Y-m-d');
            $eventsByDate[$dateKey][] = [
                'id' => 'prod_' . $prod->id,
                'type' => 'production_deadline',
                'title' => $prod->title,
                'status' => $prod->status,
                'cliente' => $prod->cliente?->name ?? 'Cliente',
                'company' => $prod->cliente?->company_name,
                'view_url' => route('productions.show', $prod),
            ];
        }

        // 4. Monta a matriz de semanas e dias
        $weeks = [];
        $currentDay = $calendarStart->copy();

        while ($currentDay <= $calendarEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $currentDay->format('Y-m-d');
                $week[] = [
                    'date' => $currentDay->copy(),
                    'date_string' => $dateKey,
                    'day_number' => $currentDay->day,
                    'is_current_month' => $currentDay->month === $month,
                    'is_today' => $currentDay->isToday(),
                    'events' => $eventsByDate[$dateKey] ?? [],
                ];
                $currentDay->addDay();
            }
            $weeks[] = $week;
        }

        // Estatísticas do Mês Selecionado
        $stats = [
            'total_scheduled' => VideoPublication::where('tenant_id', $tenantId)
                ->where('status', 'agendado')
                ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
                ->count(),
            'total_published' => VideoPublication::where('tenant_id', $tenantId)
                ->where('status', 'publicado')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count(),
            'total_productions' => Production::where('tenant_id', $tenantId)
                ->whereBetween('target_publication_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->count(),
        ];

        $clientes = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'company_name']);

        if ($request->wantsJson()) {
            return response()->json([
                'selected_date' => $selectedDate->format('Y-m'),
                'stats' => $stats,
                'events_by_date' => $eventsByDate,
            ]);
        }

        return view('calendar.index', compact(
            'selectedDate',
            'startOfMonth',
            'endOfMonth',
            'weeks',
            'stats',
            'clientes',
            'clienteId'
        ));
    }
}
