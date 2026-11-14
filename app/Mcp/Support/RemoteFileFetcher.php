<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Downloads a file from a public HTTPS URL for MCP tools. Guards against SSRF: only https on port
 * 443, every host (incl. redirect targets) must resolve to public IPs only, and the connection is
 * pinned to the checked IP so DNS cannot be rebound in between.
 */
class RemoteFileFetcher
{
    public const MAX_REDIRECTS = 3;

    public function fetch(string $url, int $maxBytes, string $field = 'image_url'): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $ip] = $this->check($url, $field);

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    // Aborts oversized downloads early (cURL handler; streaming would ignore CURLOPT_RESOLVE).
                    'progress' => function (int $total, int $downloaded) use ($maxBytes): void {
                        if ($total > $maxBytes || $downloaded > $maxBytes) {
                            throw new FileTooLargeException;
                        }
                    },
                    'connect_timeout' => 5,
                    'timeout' => 20,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]],
                ])
                    ->withUserAgent('AurumStock/1.0 (+'.config('app.url').')')
                    ->accept('image/*,application/pdf;q=0.9,*/*;q=0.5')
                    ->get($url);
            } catch (Throwable $e) {
                $tooLarge = false;

                for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                    $tooLarge = $tooLarge || $cause instanceof FileTooLargeException || str_contains($cause->getMessage(), 'Callback aborted');
                }

                throw ValidationException::withMessages([$field => $tooLarge ? $this->tooLarge($maxBytes) : 'The file could not be downloaded.']);
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    break;
                }

                $url = $this->absolute($location, $url);

                continue;
            }

            if (! $response->successful()) {
                throw ValidationException::withMessages([$field => "The download failed with HTTP status {$response->status()}."]);
            }

            $bytes = $response->body();

            if ((int) $response->header('Content-Length') > $maxBytes || strlen($bytes) > $maxBytes) {
                throw ValidationException::withMessages([$field => $this->tooLarge($maxBytes)]);
            }

            if ($bytes === '') {
                throw ValidationException::withMessages([$field => 'The downloaded file is empty.']);
            }

            return $bytes;
        }

        throw ValidationException::withMessages([$field => 'Too many redirects.']);
    }

    /**
     * Resolves a host name to its IPv4/IPv6 addresses.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    /**
     * @return array{0: string, 1: string} host and the checked IP to connect to
     */
    private function check(string $url, string $field): array
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
        ) {
            throw ValidationException::withMessages([$field => 'Only public https:// URLs (port 443, without credentials) are allowed.']);
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $ips = $this->resolve($host);

        $public = fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($ips === [] || count(array_filter($ips, $public)) !== count($ips)) {
            throw ValidationException::withMessages([$field => 'The URL must point to a public host.']);
        }

        $ip = $ips[0];

        return [$host, str_contains($ip, ':') ? "[{$ip}]" : $ip];
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = 'https://'.($parts['host'] ?? '');

        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';

        return $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
    }

    private function tooLarge(int $maxBytes): string
    {
        return 'The file must not be larger than '.intdiv($maxBytes, 1024 * 1024).' MB.';
    }
}
