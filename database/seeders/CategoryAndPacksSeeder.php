<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Pack;
use App\Models\ShowcaseVideo;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryAndPacksSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // 1. Criar Categorias Padrão
            $catPosicionamiento = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'posicionamiento'],
                ['name' => 'Posicionamiento de Marca', 'type' => 'general', 'icon' => '🎯', 'sort_order' => 1, 'is_active' => true]
            );

            $catAds = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'ads'],
                ['name' => 'Campaña de Anuncios (Ads)', 'type' => 'general', 'icon' => '📣', 'sort_order' => 2, 'is_active' => true]
            );

            $catInstitucional = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'institucional'],
                ['name' => 'Vídeo Institucional', 'type' => 'general', 'icon' => '🏢', 'sort_order' => 3, 'is_active' => true]
            );

            $catEventos = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'eventos'],
                ['name' => 'Evento Corporativo', 'type' => 'general', 'icon' => '🎉', 'sort_order' => 4, 'is_active' => true]
            );

            $catPodcast = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'podcast'],
                ['name' => 'Podcast Audiovisual', 'type' => 'general', 'icon' => '🎙️', 'sort_order' => 5, 'is_active' => true]
            );

            $catReels = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'reels-shorts'],
                ['name' => 'Reels / Shorts (9:16)', 'type' => 'general', 'icon' => '📱', 'sort_order' => 6, 'is_active' => true]
            );

            // 2. Vincular ShowcaseVideos existentes à sua categoria
            $showcaseVideos = ShowcaseVideo::where('tenant_id', $tenant->id)->get();
            foreach ($showcaseVideos as $sv) {
                if (! $sv->category_id) {
                    $catText = Str::lower($sv->category ?? '');
                    if (str_contains($catText, 'reel') || str_contains($catText, 'short')) {
                        $sv->update(['category_id' => $catReels->id]);
                    } elseif (str_contains($catText, 'instituc')) {
                        $sv->update(['category_id' => $catInstitucional->id]);
                    } elseif (str_contains($catText, 'anuncio') || str_contains($catText, 'ad')) {
                        $sv->update(['category_id' => $catAds->id]);
                    } elseif (str_contains($catText, 'event')) {
                        $sv->update(['category_id' => $catEventos->id]);
                    } elseif (str_contains($catText, 'pod')) {
                        $sv->update(['category_id' => $catPodcast->id]);
                    } else {
                        $sv->update(['category_id' => $catPosicionamiento->id]);
                    }
                }
            }

            // 3. Cadastrar Catálogo de Add-ons Oficiais no Banco de Dados
            $catalogAddons = [
                ['name' => 'Composición Visual & Efectos (VFX)', 'slug' => 'composicion-ia', 'default_price' => 100, 'description' => 'Composición visual avanzada, integración de efectos digitales y acabado cinematográfico de alto estándar.', 'sort_order' => 1],
                ['name' => 'Imágenes de apoyo / B-Roll cinematográfico', 'slug' => 'imagenes-apoyo', 'default_price' => 60, 'description' => 'Clips y planos de apoyo cinematográficos premium para enriquecer la narrativa visual.', 'sort_order' => 2],
                ['name' => 'Animación de texto & subtítulos cinéticos', 'slug' => 'animacion-texto', 'default_price' => 80, 'description' => 'Subtítulos dinámicos con tipografía animada para máxima retención de audiencia.', 'sort_order' => 3],
                ['name' => 'Animación gráfica / Motion graphics', 'slug' => 'motion-graphics', 'default_price' => 90, 'description' => 'Elementos de marca animados, identidades visuales y gráficos en movimiento.', 'sort_order' => 4],
                ['name' => 'Estrategia de distribución & Tráfico pagado', 'slug' => 'trafico-pagado', 'default_price' => 200, 'description' => 'Configuración y optimización estratégica de campañas en Meta Ads para amplificar el alcance.', 'sort_order' => 5],
                ['name' => 'Variaciones de ganchos (Hooks 0-3s)', 'slug' => 'ganchos-ab', 'default_price' => 90, 'description' => 'Versiones alternativas de introducción en los primeros 3 segundos para pruebas A/B de retención.', 'sort_order' => 6],
                ['name' => 'Formatos adicionales (Story / Feed / YouTube)', 'slug' => 'formatos-adicionais', 'default_price' => 80, 'description' => 'Adaptación y reencuadre técnico específico para cada plataforma de distribución.', 'sort_order' => 7],
                ['name' => 'Versión condensada para redes (30s / 60s)', 'slug' => 'versao-condensada', 'default_price' => 80, 'description' => 'Edición ágil condensada optimizada para Reels, TikTok y Stories.', 'sort_order' => 8],
                ['name' => 'Locución profesional en estudio', 'slug' => 'locucao-profissional', 'default_price' => 90, 'description' => 'Voz profesional grabada en estudio acústico por locutores especializados.', 'sort_order' => 9],
                ['name' => 'Cortes para redes sociales — desde 3', 'slug' => 'cortes-redes', 'default_price' => 120, 'description' => 'Extracción y edición de los momentos de mayor impacto en formato vertical 9:16.', 'sort_order' => 10],
                ['name' => 'Cortes de la ponencia — desde 3', 'slug' => 'cortes-ponencia', 'default_price' => 150, 'description' => 'Píldoras audiovisuales con las ideas clave y momentos destacados de la presentación.', 'sort_order' => 11],
                ['name' => 'Cápsulas de testimonios de clientes', 'slug' => 'testimonios', 'default_price' => 120, 'description' => 'Grabación y edición de testimonios espontáneos y declaraciones de valor.', 'sort_order' => 12],
                ['name' => 'Grabación de mesas redondas & debates', 'slug' => 'grabacion-conversaciones', 'default_price' => 150, 'description' => 'Captación multicámara de conversaciones, paneles y debates complementarios.', 'sort_order' => 13],
                ['name' => 'Pieza documental de Making of', 'slug' => 'making-of', 'default_price' => 180, 'description' => 'Cobertura cinematográfica detrás de cámaras capturando la atmósfera del proyecto.', 'sort_order' => 14],
                ['name' => 'Entrevistas a participantes e invitados', 'slug' => 'depoimentos-convidados', 'default_price' => 100, 'description' => 'Entrevistas cortas con asistentes y ponentes destacados del evento.', 'sort_order' => 15],
            ];

            $addonMap = [];
            foreach ($catalogAddons as $ca) {
                $createdAddon = \App\Models\Addon::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $ca['slug']],
                    array_merge($ca, ['tenant_id' => $tenant->id, 'currency' => 'eur', 'is_active' => true])
                );
                $addonMap[$ca['slug']] = $createdAddon;
            }

            $standardAddonSlugs = ['composicion-ia', 'imagenes-apoyo', 'animacion-texto', 'motion-graphics', 'trafico-pagado'];

            $packsData = [
                [
                    'name' => 'Pack Posicionamiento Básico',
                    'slug' => 'pack-posicionamiento-basico',
                    'category_id' => $catPosicionamiento->id,
                    'description' => 'Producción, grabación y edición de 5 vídeos para redes y comunicación de marca.',
                    'video_quantity' => 5,
                    'price' => 600.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        '5 vídeos editados e finalizados',
                        'Enfoque em redes sociais e branding',
                        'Revisão e aprovação no Portal do Cliente',
                    ],
                    'addon_slugs' => $standardAddonSlugs,
                    'status' => 'active',
                ],
                [
                    'name' => 'Pack Posicionamiento PRO',
                    'slug' => 'pack-posicionamiento-pro',
                    'category_id' => $catPosicionamiento->id,
                    'description' => 'Más contenido para mantener una comunicación constante y desarrollar diferentes líneas de contenido.',
                    'video_quantity' => 8,
                    'price' => 850.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        '8 vídeos editados e finalizados',
                        'Comunicação constante e múltiplas linhas editoriais',
                        'Roteiros persuasivos e retenção calibrada',
                    ],
                    'addon_slugs' => $standardAddonSlugs,
                    'status' => 'active',
                ],
                [
                    'name' => 'Pack Posicionamiento FULL',
                    'slug' => 'pack-posicionamiento-full',
                    'category_id' => $catPosicionamiento->id,
                    'description' => 'Una producción audiovisual más completa para desarrollar el posicionamiento de tu marca de forma continuada.',
                    'video_quantity' => 12,
                    'price' => 1150.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        '12 vídeos de alto impacto',
                        'Posicionamento de autoridade contínua no nicho',
                        'Suporte estratégico completo',
                    ],
                    'addon_slugs' => $standardAddonSlugs,
                    'status' => 'active',
                ],
                [
                    'name' => 'Campaña de Anuncios (Pack Ads)',
                    'slug' => 'pack-ads-campana',
                    'category_id' => $catAds->id,
                    'description' => '3 vídeos publicitarios de hasta 30 segundos pensados específicamente para campañas de captación y conversión.',
                    'video_quantity' => 3,
                    'price' => 700.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        'Concepto creativo estratégico',
                        'Guion de alta conversão',
                        'Producción y edición profesional',
                        '3 vídeos de até 30 segundos',
                        'Tráfego pago incluso',
                    ],
                    'addon_slugs' => ['ganchos-ab', 'formatos-adicionais'],
                    'status' => 'active',
                ],
                [
                    'name' => 'Vídeo Institucional Corporativo',
                    'slug' => 'video-institucional',
                    'category_id' => $catInstitucional->id,
                    'description' => 'Un vídeo profesional para presentar tu empresa, explicar lo que haces y transmitir la dimensión real de tu marca.',
                    'video_quantity' => 1,
                    'price' => 550.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        'Vídeo de apresentação de alto padrão',
                        'Roteiro institucional personalizado',
                        'Tratamento de cor e sonorização cinema',
                    ],
                    'addon_slugs' => ['versao-condensada', 'locucao-profissional'],
                    'status' => 'active',
                ],
                [
                    'name' => 'Pack Podcast Audiovisual',
                    'slug' => 'pack-podcast',
                    'category_id' => $catPodcast->id,
                    'description' => 'Grabación y edición de 1 hora de podcast (episodio completo).',
                    'video_quantity' => 1,
                    'price' => 650.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        '1 hora de gravação multicâmera',
                        'Edição e tratamento de áudio/vídeo completo',
                        'Exportação em alta resolução',
                    ],
                    'addon_slugs' => ['cortes-redes', 'animacion-texto', 'motion-graphics', 'composicion-ia', 'imagenes-apoyo'],
                    'status' => 'active',
                ],
                [
                    'name' => 'Evento Corporativo (Pack 1)',
                    'slug' => 'evento-corporativo-pack-1',
                    'category_id' => $catEventos->id,
                    'description' => 'Aftermovie + Fotografía: La historia de tu evento en una pieza audiovisual dinámica con fotografías profesionales.',
                    'video_quantity' => 1,
                    'price' => 1500.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        'Aftermovie dinâmico do evento',
                        'Seleção de fotografias profissionais tratadas',
                        'Cobertura presencial completa',
                    ],
                    'addon_slugs' => ['cortes-redes', 'depoimentos-convidados'],
                    'status' => 'active',
                ],
                [
                    'name' => 'Evento Corporativo (Pack 2)',
                    'slug' => 'evento-corporativo-pack-2',
                    'category_id' => $catEventos->id,
                    'description' => 'Aftermovie + Fotografía + Ponencia completa: Registro integral da palestra e peça dinâmica.',
                    'video_quantity' => 2,
                    'price' => 1900.00,
                    'vat_rate' => 21.00,
                    'currency' => 'eur',
                    'features' => [
                        'Aftermovie do evento + Fotos profissionais',
                        'Gravação integral da palestra/ponencia',
                        'Entrega pronta para acervo e divulgação',
                    ],
                    'addon_slugs' => ['cortes-ponencia', 'testimonios', 'grabacion-conversaciones', 'cortes-redes', 'making-of'],
                    'status' => 'active',
                ],
            ];

            $officialSlugs = collect($packsData)->pluck('slug')->toArray();
            Pack::where('tenant_id', $tenant->id)->whereNotIn('slug', $officialSlugs)->delete();

            foreach ($packsData as $data) {
                $addonSlugs = $data['addon_slugs'] ?? [];
                unset($data['addon_slugs']);

                // Constrói array estruturado também para retrocompatibilidade do campo json addons
                $structuredAddons = [];
                foreach ($addonSlugs as $aslug) {
                    if (isset($addonMap[$aslug])) {
                        $structuredAddons[] = [
                            'id' => $addonMap[$aslug]->id,
                            'name' => $addonMap[$aslug]->name,
                            'price' => (float) $addonMap[$aslug]->default_price,
                        ];
                    }
                }
                $data['addons'] = $structuredAddons;

                $pack = Pack::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $data['slug']],
                    $data
                );

                // Vincula na tabela pivot pack_addon
                $syncData = [];
                foreach ($addonSlugs as $order => $aslug) {
                    if (isset($addonMap[$aslug])) {
                        $syncData[$addonMap[$aslug]->id] = [
                            'custom_price' => null, // usa default_price
                            'is_default_selected' => false,
                            'sort_order' => $order + 1,
                        ];
                    }
                }
                $pack->addons()->sync($syncData);
            }
        }
    }
}
