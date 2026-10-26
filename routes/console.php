<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Motor de Publicação Agendada Social da NUME (executa a cada minuto sem sobreposição)
Schedule::command('videos:publish-scheduled')->everyMinute()->withoutOverlapping();

// Automação de Follow-up de Aprovações Pendentes do Cliente (executa a cada hora)
Schedule::command('approvals:check-pending --hours=48')->hourly()->withoutOverlapping();

// Expiração automática de transferências bancárias vencidas (executa a cada hora)
Schedule::command('packs:expire-bank-transfers')->hourly()->withoutOverlapping();

// Arquivo final sai da hospedagem depois de postado (só com link de entrega no Drive)
Schedule::command('videos:purge-posted')->hourly()->withoutOverlapping();

// RGPD: leads perdidos além do prazo de conservação perdem os dados pessoais
Schedule::command('crm:anonymize-lost')->dailyAt('03:30')->withoutOverlapping();

// Saúde das contas conectadas pelo app próprio (renova tokens e marca as revogadas para reconexão)
Schedule::command('social:refresh-tokens')->dailyAt('04:15')->withoutOverlapping();
