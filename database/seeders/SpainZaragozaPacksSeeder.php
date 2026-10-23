<?php

namespace Database\Seeders;

use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SpainZaragozaPacksSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'nume')->first();

        if (! $tenant) {
            $tenant = Tenant::first();
        }

        if (! $tenant) {
            return;
        }

        $packs = [
            [
                'name' => 'Pack Impulso Local — Reels & TikTok PYME (Zaragoza)',
                'slug' => 'impulso-local-zaragoza',
                'description' => 'Especialmente diseñado para el comercio local, clínicas, restauración y servicios de Zaragoza. Incluye 4 vídeos verticales de alta conversión (Reels/TikTok/Shorts) con guion estratégico de retención, edición dinámica, subtítulos animados y gancho persuasivo para captar clientes en Aragón.',
                'video_quantity' => 4,
                'price' => 490.00,
                'currency' => 'eur',
                'status' => 'active',
                'billing_type' => 'one_off',
                'billing_mode' => 'split_50_50',
                'deposit_percentage' => 50,
                'pilot_discount_on_subscription' => 50.00,
            ],
            [
                'name' => 'Pack Autoridad B2B & Liderazgo (CEOs & Directivos)',
                'slug' => 'autoridad-b2b-liderazgo',
                'description' => 'Posicionamiento de marca personal y autoridad corporativa para fundadores y ejecutivos en el ecosistema empresarial de Zaragoza. 4 vídeos de alto impacto en formato entrevista/podcast con cortes optimizados para LinkedIn y YouTube.',
                'video_quantity' => 4,
                'price' => 890.00,
                'currency' => 'eur',
                'status' => 'active',
                'billing_type' => 'one_off',
                'billing_mode' => 'split_50_50',
                'deposit_percentage' => 50,
            ],
            [
                'name' => 'Pack Corporativo & Logística 4K (Hub Zaragoza)',
                'slug' => 'corporativo-logistica-4k',
                'description' => 'Producción cinematográfica institucional enfocada en industrias, operadores logísticos (PLAZA) y empresas B2B en expansión. 2 vídeos manifiesto/corporativos en 4K con narrativa de impacto, corrección de color, sound design y locución profesional.',
                'video_quantity' => 2,
                'price' => 1450.00,
                'currency' => 'eur',
                'status' => 'active',
                'billing_type' => 'one_off',
                'billing_mode' => 'full_upfront',
            ],
            [
                'name' => 'Pack Retainer Mensual — Escala Audiovisual 360°',
                'slug' => 'retainer-mensual-escala-360',
                'description' => 'Acompañamiento audiovisual continuo para marcas que buscan liderar su sector y potenciar campañas de paid media (Meta Ads y Google Ads). Incluye 8 vídeos estratégicos al mes (4 verticales + 2 testimonios de clientes + 2 ofertas directas) con entrega iterativa.',
                'video_quantity' => 8,
                'price' => 1950.00,
                'currency' => 'eur',
                'status' => 'active',
                'billing_type' => 'recurring',
                'billing_interval' => 'month',
                'commitment_months' => 3,
                'billing_mode' => 'full_upfront',
            ],
        ];

        foreach ($packs as $packData) {
            Pack::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $packData['name'],
                ],
                $packData
            );
        }
    }
}
