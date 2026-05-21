<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LinkPreviewService
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function fetch(string $url): ?array
    {
        // Validate scheme
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return null;
        }

        // SSRF guard: resolve host and check for private/loopback ranges
        $host = $parsed['host'] ?? '';
        if (empty($host)) return null;
        $ip = gethostbyname($host);
        if ($ip === $host) return null; // DNS failed
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        // Cache check (1 hour)
        $cacheDir = dirname(__DIR__, 2) . '/var/cache/link_preview';
        $cacheFile = $cacheDir . '/' . md5($url) . '.json';
        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - 3600) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 5,
                'max_redirects' => 0,
                'resolve' => [$host => $ip], // pin DNS to pre-validated IP to prevent TOCTOU bypass
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; MessengerBot/1.0)'],
            ]);
            $html = $response->getContent(false); // false = don't throw on 4xx/5xx
        } catch (\Throwable) {
            return null;
        }

        // Parse OG tags
        $title = $description = $image = null;

        if (preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $m)) {
            foreach ($m[1] as $i => $prop) {
                if ($prop === 'title' && $title === null) $title = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($prop === 'description' && $description === null) $description = html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($prop === 'image' && $image === null) $image = $m[2][$i];
            }
        }
        // Also try reversed attribute order: content first, then property
        if ($title === null && preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']og:title["\'][^>]*>/i', $html, $m2)) {
            $title = html_entity_decode($m2[1][0] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: null;
        }
        // Fallback to <title>
        if (empty($title) && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $t)) {
            $title = html_entity_decode(trim($t[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (empty($title)) return null;

        $result = [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'url' => $url,
        ];

        // Write cache
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        file_put_contents($cacheFile, json_encode($result));

        return $result;
    }
}
