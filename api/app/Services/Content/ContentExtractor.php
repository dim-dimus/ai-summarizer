<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches a user-supplied URL server-side and reduces it to readable text.
 *
 * SSRF-guarded: the original URL and every redirect target are validated by
 * SsrfGuard before any connection. Auto-redirects are disabled so each hop is
 * re-validated. Enforces a timeout, a max response size, and a redirect cap.
 */
class ContentExtractor
{
    public function __construct(
        private readonly SsrfGuard $guard,
    ) {}

    public function extract(string $url): ExtractedContent
    {
        $timeout = (int) config('services.fetch.timeout_seconds', 10);
        $maxBytes = (int) config('services.fetch.max_bytes', 2_000_000);
        $maxRedirects = (int) config('services.fetch.max_redirects', 3);
        $userAgent = (string) config('services.fetch.user_agent', 'AISummarizerBot/1.0');

        $currentUrl = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $this->guard->validate($currentUrl); // re-validate every hop (DNS rebinding / redirect SSRF)

            $response = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout($timeout)
                ->withOptions([
                    'allow_redirects' => false, // follow manually so we can re-validate
                    'stream' => false,
                ])
                ->get($currentUrl);

            // Manual redirect handling.
            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '' || $location === null) {
                    throw new RuntimeException('Redirect with no Location header.');
                }
                $currentUrl = $this->resolveRedirect($currentUrl, $location);

                continue;
            }

            if ($response->failed()) {
                throw new RuntimeException("Fetch failed (HTTP {$response->status()}) for {$currentUrl}");
            }

            // Reject oversized bodies (declared or actual).
            $declared = (int) ($response->header('Content-Length') ?: 0);
            if ($declared > $maxBytes) {
                throw new RuntimeException('Response exceeds max allowed size.');
            }

            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
            }

            return new ExtractedContent(
                text: $this->htmlToText($body),
                title: $this->extractTitle($body),
                finalUrl: $currentUrl,
            );
        }

        throw new RuntimeException('Too many redirects.');
    }

    private function resolveRedirect(string $base, string $location): string
    {
        // Absolute URL → use as-is; relative → resolve against the base origin.
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/'.ltrim($location, '/');
    }

    /**
     * Strip scripts/styles and tags, collapse whitespace into readable text.
     */
    private function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('#<title\b[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title !== '' ? mb_substr($title, 0, 255) : null;
        }

        return null;
    }
}
