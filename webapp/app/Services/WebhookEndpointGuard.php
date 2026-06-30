<?php

namespace App\Services;

use Illuminate\Support\Str;

class WebhookEndpointGuard
{
    public function validate(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return 'Enter a valid HTTP or HTTPS endpoint URL.';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'Webhook endpoints must use HTTP or HTTPS with a valid host.';
        }

        if ($scheme !== 'https' && app()->environment('production')) {
            return 'Production webhook endpoints must use HTTPS.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Webhook endpoint URLs must not contain embedded credentials.';
        }

        if ($this->isLocalHostName($host) || $this->isUnsafeIpAddress($host)) {
            return 'Webhook endpoints must not target local, private, loopback, link-local, or reserved hosts.';
        }

        return null;
    }

    private function isLocalHostName(string $host): bool
    {
        return $host === 'localhost'
            || Str::endsWith($host, '.localhost')
            || $host === 'host.docker.internal';
    }

    private function isUnsafeIpAddress(string $host): bool
    {
        $host = trim($host, '[]');

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
