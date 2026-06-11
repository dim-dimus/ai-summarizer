<?php

namespace App\Services\Content;

/**
 * SSRF protection for server-side URL fetches (technical design §7).
 *
 * - Accept only http/https.
 * - Resolve the host and reject any IP in a private / reserved / link-local
 *   range (covers 127/8, 10/8, 172.16/12, 192.168/16, 169.254/16 incl. the AWS
 *   metadata endpoint 169.254.169.254, ::1, fc00::/7, etc).
 * - Used to validate the original URL *and* every redirect hop.
 */
class SsrfGuard
{
    /**
     * Validate a URL is safe to fetch. Throws SsrfException if not.
     * Returns the parsed components for the caller.
     *
     * @return array{scheme:string, host:string}
     */
    public function validate(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new SsrfException("Malformed URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfException("Unsupported URL scheme '{$scheme}'. Only http/https are allowed.");
        }

        $host = $parts['host'];

        // Reject if the host is itself a literal IP in a blocked range.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return ['scheme' => $scheme, 'host' => $host];
        }

        // Resolve the hostname; reject if it resolves to nothing or to a blocked IP.
        $ips = $this->resolveHost($host);
        if ($ips === []) {
            throw new SsrfException("Could not resolve host: {$host}");
        }
        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return ['scheme' => $scheme, 'host' => $host];
    }

    /**
     * Reject loopback, private, reserved, and link-local addresses.
     */
    private function assertPublicIp(string $ip): void
    {
        // Explicit denylist for the cloud metadata endpoint (belt-and-suspenders).
        if ($ip === '169.254.169.254' || $ip === 'fd00:ec2::254') {
            throw new SsrfException('Blocked: cloud metadata endpoint.');
        }

        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublic === false) {
            throw new SsrfException("Blocked private/reserved IP address: {$ip}");
        }
    }

    /**
     * Resolve a hostname to its IPv4 + IPv6 addresses.
     *
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        $v4 = gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
