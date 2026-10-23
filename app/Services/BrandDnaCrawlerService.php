<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BrandDnaCrawlerService
{
    /**
     * Extrai metadados, títulos e conteúdo textual essencial de uma URL corporativa.
     */
    public function crawl(?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        $cleanUrl = $this->sanitizeUrl($url);
        if (! $cleanUrl) {
            return null;
        }

        // 1. Verificação de segurança anti-SSRF
        if (! $this->isSafeUrl($cleanUrl)) {
            Log::warning("BrandDnaCrawlerService: Tentativa de crawling bloqueada por SSRF: {$cleanUrl}");
            return null;
        }

        try {
            $response = Http::timeout(8.0)
                ->connectTimeout(4.0)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,pt-BR,pt;q=0.8,en;q=0.7',
                ])
                ->get($cleanUrl);

            // Se o servidor retornar 403 Forbidden ou 401 por bloqueio de WAF/Cloudflare, tenta com Googlebot
            if ($response->status() === 403 || $response->status() === 401 || ! $response->successful()) {
                Log::info("BrandDnaCrawlerService: HTTP {$response->status()} com Chrome UA para {$cleanUrl}. Tentando com Search Engine crawler...");
                $response = Http::timeout(10.0)
                    ->connectTimeout(4.0)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'es-ES,es;q=0.9,pt-BR,pt;q=0.8,en;q=0.7',
                    ])
                    ->get($cleanUrl);
            }

            if (! $response->successful()) {
                Log::warning("BrandDnaCrawlerService: HTTP {$response->status()} para {$cleanUrl} após fallback");
                return [
                    'url' => $cleanUrl,
                    'http_status' => $response->status(),
                    'error' => "HTTP {$response->status()}",
                    'text_summary' => '',
                ];
            }

            $html = substr($response->body(), 0, 1024 * 1024 * 2); // Limita a 2MB
            $extracted = $this->parseHtml($html, $cleanUrl);
            $extracted['http_status'] = $response->status();

            return $extracted;
        } catch (Exception $e) {
            Log::warning("BrandDnaCrawlerService erro ao acessar {$cleanUrl}: " . $e->getMessage());
            return [
                'url' => $cleanUrl,
                'http_status' => 0,
                'error' => $e->getMessage(),
                'text_summary' => '',
            ];
        }
    }

    /**
     * Normaliza e valida a URL.
     */
    protected function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    /**
     * Proteção contra SSRF (Server-Side Request Forgery).
     * Impede requisições a localhost, redes privadas ou metadados de nuvem (AWS/GCP).
     */
    protected function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || empty($host)) {
            return false;
        }

        // Bloqueia domínios locais óbvios
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        // Resolve DNS para IP
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($ips)) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host) {
                $ips = [['ip' => $ip]];
            }
        }

        if (empty($ips)) {
            return true; // Se não resolver DNS imediatamente, deixa o cURL tratar
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! $ip) continue;

            // Filtra IPs privados ou reservados
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            // Bloqueia metadados AWS/GCP (169.254.169.254)
            if (str_starts_with($ip, '169.254.')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extrai títulos, metatags e conteúdo textual estratégico do HTML.
     */
    protected function parseHtml(string $html, string $sourceUrl): array
    {
        // 1. Remove scripts, styles, iframes, svgs e formulários para isolar texto puro
        $cleanHtml = preg_replace('/<(script|style|svg|iframe|noscript|nav|footer)[^>]*?>.*?<\/\\1>/si', '', $html);

        // 2. Extrai Title
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 3. Extrai Meta Description e OG Description
        $metaDescription = '';
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/si', $html, $matches)) {
            $metaDescription = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']description["\']/si', $html, $matches)) {
            $metaDescription = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $ogDescription = '';
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/si', $html, $matches)) {
            $ogDescription = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $ogTitle = '';
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/si', $html, $matches)) {
            $ogTitle = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 4. Extrai Cabeçalhos H1, H2 e H3
        $headings = [];
        if (preg_match_all('/<(h1|h2|h3)[^>]*>(.*?)<\/\1>/si', $cleanHtml, $matches)) {
            foreach ($matches[2] as $h) {
                $stripped = trim(strip_tags($h));
                $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (mb_strlen($stripped) > 3 && mb_strlen($stripped) < 160 && ! in_array($stripped, $headings, true)) {
                    $headings[] = $stripped;
                }
            }
        }
        $headings = array_slice($headings, 0, 15);

        // 5. Extrai Parágrafos mais relevantes
        $paragraphs = [];
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $cleanHtml, $matches)) {
            foreach ($matches[1] as $p) {
                $stripped = trim(strip_tags($p));
                $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Ignora textos de cookies, avisos legais ou muito curtos
                if (mb_strlen($stripped) > 40 && mb_strlen($stripped) < 400) {
                    if (! preg_match('/cookie|política|privacy|direitos reservados|copyright/i', $stripped)) {
                        $paragraphs[] = $stripped;
                    }
                }
            }
        }
        $paragraphs = array_slice($paragraphs, 0, 10);

        // Resumo de texto unificado
        $textSummary = implode("\n", array_merge(
            $title ? ["Título do Site: {$title}"] : [],
            $metaDescription ? ["Descrição da Empresa: {$metaDescription}"] : [],
            $ogDescription ? ["Proposta Social (OG): {$ogDescription}"] : [],
            !empty($headings) ? ["Títulos e Serviços: " . implode(" | ", array_slice($headings, 0, 8))] : [],
            !empty($paragraphs) ? ["Destaques de Conteúdo:\n" . implode("\n- ", array_slice($paragraphs, 0, 5))] : []
        ));

        return [
            'url' => $sourceUrl,
            'title' => $title ?: $ogTitle,
            'meta_description' => $metaDescription ?: $ogDescription,
            'headings' => $headings,
            'paragraphs' => $paragraphs,
            'text_summary' => mb_substr($textSummary, 0, 2500),
        ];
    }
}
