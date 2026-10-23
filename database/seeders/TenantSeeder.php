<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Tenant Oficial: NUME Creative
        $numeTenant = Tenant::firstOrCreate(
            ['slug' => 'nume'],
            [
                'name' => 'NUME Creative',
                'description' => 'Produtora estratégica oficial da plataforma',
                'status' => 'active',
                'primary_contact_email' => 'admin@numecreative.com',
                'company_cnpj' => '00.000.000/0001-00',
                'subscription_plan' => 'enterprise',
                'subscribed_at' => now(),
                'subscription_expires_at' => now()->addYears(10),
            ]
        );

        // 2. Usuário Super Admin da Produtora NUME
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@numecreative.com'],
            [
                'tenant_id' => $numeTenant->id,
                'name' => 'Admin NUME',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 3. Cliente de Teste vinculado à NUME Creative
        $cliente = Cliente::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $numeTenant->id,
                'email' => 'cliente@teste.com',
            ],
            [
                'name' => 'Alejandro Martínez',
                'company_name' => 'Zaragoza Tech Solutions S.L.',
                'password' => Hash::make('password123'),
                'phone' => '+34 976 892 100',
                'website' => 'https://zaragozatech.es',
                'instagram_handle' => '@zaragozatech',
                'linkedin_url' => 'https://linkedin.com/company/zaragozatech',
                'tiktok_handle' => '@zaragozatech_es',
                'brand_niche' => 'Logística B2B & Automação Industrial no Hub PLAZA (Zaragoza)',
                'target_audience' => 'CEOs, Diretores de Operações e Gestores de Logística em Aragão e Espanha',
                'key_differentials' => 'Mais de 12 anos de atuação em PLAZA, tecnologia proprietária e suporte 24/7 para frotas industriais',
                'email_verified_at' => now(),
            ]
        );

        // 4. Garante que os Pacotes Oficiais de Zaragoza existam no banco
        $this->call(SpainZaragozaPacksSeeder::class);

        $retainerPack = Pack::where('tenant_id', $numeTenant->id)->where('billing_type', 'recurring')->first();
        $splitPack = Pack::where('tenant_id', $numeTenant->id)->where('billing_mode', 'split_50_50')->first();

        // 5. Compra 1: Contrato Recorrente Ativo (Retainer Mensal com Fatura Paga)
        if ($retainerPack) {
            $subPurchase = PackPurchase::updateOrCreate(
                [
                    'tenant_id' => $numeTenant->id,
                    'cliente_id' => $cliente->id,
                    'pack_id' => $retainerPack->id,
                ],
                [
                    'amount' => $retainerPack->price,
                    'subtotal' => $retainerPack->price,
                    'tax_rate' => 21.00,
                    'tax_amount' => round($retainerPack->price * 0.21, 2),
                    'total_amount' => round($retainerPack->price * 1.21, 2),
                    'status' => 'paid',
                    'billing_type' => 'subscription',
                    'subscription_status' => 'active',
                    'subscription_interval' => 'month',
                    'commitment_months' => 3,
                    'subscription_cycle_current' => 2,
                    'subscription_cycles_total' => 3,
                    'subscription_renews_at' => now()->addDays(14),
                    'video_quantity' => $retainerPack->video_quantity,
                    'videos_used' => 2,
                    'paid_at' => now()->subDays(16),
                    'payment_gateway' => 'stripe',
                    'invoice_number' => 'FAC-2026-0001',
                    'billing_legal_name' => 'Zaragoza Tech Solutions S.L.',
                    'billing_tax_id' => 'B50123456',
                    'billing_address' => 'Calle Alfonso I, 12, 50003 Zaragoza, España',
                ]
            );

            // Produção 1 vinculada ao Retainer: Em Produção
            $production1 = Production::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'cliente_id' => $cliente->id,
                'title' => 'Campanha Zaragoza — Vídeo de Autoridade 01',
            ]);

            if (! $production1->exists || empty($production1->uuid)) {
                $production1->uuid = (string) Str::uuid();
            }

            $production1->fill([
                'pack_purchase_id' => $subPurchase->id,
                'created_by' => $adminUser->id,
                'description' => 'Vídeo vertical para Reels e TikTok focado em autoridade e captação de clientes.',
                'status' => 'in_production',
                'target_publication_date' => now()->addDays(5),
                'seo_keywords' => 'marketing digital, zaragoza, pyme, autoridade',
            ])->save();

            // Roteiro Aprovado da Produção 1
            $roteiro1 = Roteiro::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'production_id' => $production1->id,
            ]);

            if (! $roteiro1->exists || empty($roteiro1->uuid)) {
                $roteiro1->uuid = (string) Str::uuid();
            }

            $roteiro1->fill([
                'created_by' => $adminUser->id,
                'content' => "🎬 GANCHO (0-3s):\nVocê sabia que 87% das empresas em Zaragoza ainda perdem vendas por não terem vídeos estratégicos nas redes?\n\n💡 CORPO (4-45s):\nNeste vídeo demonstramos como a sua marca se posiciona à frente do mercado com produções de alto impacto, roteiros persuasivos e gravação em 4K no ecossistema de Aragão.\n\n🎯 CHAMADA PARA AÇÃO (46-60s):\nClique no link da nossa bio e agende uma consultoria sem compromisso com o time da NUME Creative.",
                'status' => 'approved',
                'submitted_at' => now()->subDays(2),
                'approved_at' => now()->subDay(),
            ])->save();

            // Vídeo Final da Produção 1
            $video1 = Video::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'production_id' => $production1->id,
                'title' => 'Corte Final — Zaragoza Tech Autoridade 01',
            ]);

            $video1->fill([
                'description' => 'Vídeo vertical 9:16 de alta conversão renderizado para Reels, TikTok e YouTube Shorts.',
                'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?auto=format&fit=crop&w=800&q=80',
                'duration_seconds' => 58,
                'status' => 'ready',
                'sofi_seo_title' => 'Logística B2B em Zaragoza: Como reduzir custos de frota em até 34%',
                'sofi_seo_description' => "Descubra como otimizar suas operações no polo logístico PLAZA com automação inteligente e governança de ponta a ponta.\n\n👉 Acesse o link da bio e receba o diagnóstico gratuito da NUME Creative.",
                'sofi_hashtags' => ['#LogisticaZaragoza', '#TechAragon', '#PLAZAZaragoza', '#B2BSpain'],
                'sofi_seo_keywords' => 'logistica zaragoza, transporte aragon, automacao frota b2b',
                'sofi_seo_generated_at' => now(),
            ])->save();
        }

        // 6. Compra 2: Modalidade Split 50/50 com Custódia Ativa (Saldo Pendente)
        if ($splitPack) {
            $deposit = round($splitPack->price * 0.50, 2);
            $balance = round($splitPack->price * 0.50, 2);
            $taxAmount = round($splitPack->price * 0.21, 2);

            $splitPurchase = PackPurchase::updateOrCreate(
                [
                    'tenant_id' => $numeTenant->id,
                    'cliente_id' => $cliente->id,
                    'pack_id' => $splitPack->id,
                ],
                [
                    'amount' => $splitPack->price,
                    'subtotal' => $splitPack->price,
                    'tax_rate' => 21.00,
                    'tax_amount' => $taxAmount,
                    'total_amount' => round($splitPack->price + $taxAmount, 2),
                    'billing_mode' => 'split_50_50',
                    'deposit_amount' => $deposit,
                    'balance_amount' => $balance,
                    'deposit_paid_at' => now()->subDays(3),
                    'balance_status' => 'pending',
                    'status' => 'paid', // Entrada quitada com sucesso
                    'video_quantity' => $splitPack->video_quantity,
                    'videos_used' => 1,
                    'payment_gateway' => 'redsys_bizum',
                    'paid_at' => now()->subDays(3),
                    'invoice_number' => 'FAC-2026-0002',
                    'billing_legal_name' => 'Zaragoza Tech Solutions S.L.',
                    'billing_tax_id' => 'B50123456',
                    'billing_address' => 'Calle Alfonso I, 12, 50003 Zaragoza, España',
                ]
            );

            // Produção 2: Aguardando Revisão e Liberação de Custódia
            $production2 = Production::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'cliente_id' => $cliente->id,
                'title' => 'Inovação Hub PLAZA — Vídeo Piloto de Conversão',
            ]);

            if (! $production2->exists || empty($production2->uuid)) {
                $production2->uuid = (string) Str::uuid();
            }

            $production2->fill([
                'pack_purchase_id' => $splitPurchase->id,
                'created_by' => $adminUser->id,
                'description' => 'Produção em modelo 50/50 Split com custódia de entrega até aprovação final pelo cliente.',
                'status' => 'pending_review',
                'target_publication_date' => now()->addDays(7),
                'seo_keywords' => 'plaza zaragoza, logistica, automacao',
            ])->save();

            // Roteiro submetido da Produção 2
            $roteiro2 = Roteiro::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'production_id' => $production2->id,
            ]);

            if (! $roteiro2->exists || empty($roteiro2->uuid)) {
                $roteiro2->uuid = (string) Str::uuid();
            }

            $roteiro2->fill([
                'created_by' => $adminUser->id,
                'content' => "🎬 HOOK (0-3s):\nComo as empresas do PLAZA estão economizando mais de € 40.000 ao ano com automação de ponta?\n\n💼 CORPO (4-40s):\nEntenda a metodologia ágil aplicada a operações logísticas e reduza gargalos na sua operação em menos de 30 dias.\n\n🎯 CTA:\nSolicite o estudo de caso completo nos comentários.",
                'status' => 'submitted',
                'submitted_at' => now(),
            ])->save();

            // Vídeo da Produção 2 com Custódia Ativa
            $video2 = Video::withoutGlobalScope('tenant')->firstOrNew([
                'tenant_id' => $numeTenant->id,
                'production_id' => $production2->id,
                'title' => 'Corte Piloto v1 — PLAZA Tech',
            ]);

            $video2->fill([
                'description' => 'Corte preliminar de aprovação para validação de tom, ritmo e chamada final.',
                'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?auto=format&fit=crop&w=800&q=80',
                'duration_seconds' => 45,
                'status' => 'ready',
                'sofi_seo_title' => 'Como economizar € 40.000 em logística no Hub PLAZA Zaragoza',
                'sofi_hashtags' => ['#ZaragozaLogistica', '#PLAZA', '#B2B', '#EspanhaTech'],
            ])->save();
        }

        // 7. Perfil Social Conectado para Demonstração Imediata
        SocialProfile::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $numeTenant->id,
                'cliente_id' => $cliente->id,
            ],
            [
                'upload_post_username' => (string) Str::uuid(),
                'connected' => true,
                'platforms_connected' => ['instagram', 'tiktok', 'youtube'],
                'last_synced_at' => now(),
            ]
        );

        // 8. Add-on de Distribuição Social nos Pacotes
        $distributionAddon = Addon::withoutGlobalScope('tenant')->firstOrCreate(
            [
                'tenant_id' => $numeTenant->id,
                'slug' => 'distribuicao-multi-rede',
            ],
            [
                'name' => 'Distribuição Automática Multi-Rede (Reels + TikTok + Shorts)',
                'description' => 'Publicação automatizada em 1 clique nas redes sociais da marca com legendas e hashtags calibradas pela Sofi IA.',
                'default_price' => 49.00,
                'currency' => 'EUR',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Vincula o add-on a todos os packs ativos do tenant
        foreach (Pack::withoutGlobalScope('tenant')->where('tenant_id', $numeTenant->id)->get() as $p) {
            $p->addons()->syncWithoutDetaching([
                $distributionAddon->id => [
                    'custom_price' => 49.00,
                    'is_default_selected' => false,
                    'sort_order' => 1,
                ],
            ]);
        }

        // Garante UUID para todas as produções e roteiros existentes
        foreach (Production::withoutGlobalScope('tenant')->whereNull('uuid')->get() as $p) {
            $p->uuid = (string) Str::uuid();
            $p->save();
        }

        foreach (Roteiro::withoutGlobalScope('tenant')->whereNull('uuid')->get() as $r) {
            $r->uuid = (string) Str::uuid();
            $r->save();
        }
    }
}
