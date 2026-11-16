<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

/**
 * URLs of the service for the current request: the script, given to the gateway as the next
 * URL to call, and the URL asked for, to which the emulator sends browsers back.
 *
 * The scheme is https when the web server says the request came over TLS (HTTPS, port
 * 443). Behind a reverse proxy that terminates TLS, X-Forwarded-Proto is used too, but only
 * from the proxies listed in trustedProxies: any client could send it. serviceUrl, when
 * set, gives the scheme and host instead of the request (e.g. https://minitel.example.org).
 */
final readonly class RequestUrl
{
    // scheme://host[:port] of serviceUrl, null to take them from the request
    private ?string $origin;

    /** @var list<array{string, int}> trusted proxies: packed address and prefix length */
    private array $proxies;

    /**
     * @param ?string $serviceUrl public URL of the service, without path (SERVICE_URL)
     * @param string $trustedProxies IP addresses or ranges (10.0.0.0/8), separated by commas
     *                               (TRUSTED_PROXIES)
     */
    public function __construct(?string $serviceUrl = null, string $trustedProxies = '')
    {
        $this->origin = $serviceUrl === null ? null : self::origin($serviceUrl);
        $this->proxies = self::proxies($trustedProxies);
    }

    /**
     * From the environment: SERVICE_URL and TRUSTED_PROXIES, both optional
     */
    public static function fromEnvironment(): self
    {
        return new self(getenv('SERVICE_URL') ?: null, getenv('TRUSTED_PROXIES') ?: '');
    }

    /**
     * URL of the script serving the request, without query: the next URL for the gateway
     */
    public function script(): string
    {
        return $this->requestOrigin() . ServerVariable::get('PHP_SELF');
    }

    /**
     * URL asked for, with its query
     */
    public function current(): string
    {
        return $this->requestOrigin() . ServerVariable::get('REQUEST_URI');
    }

    private function requestOrigin(): string
    {
        return $this->origin ?? $this->scheme() . '://' . ServerVariable::get('HTTP_HOST');
    }

    private function scheme(): string
    {
        if (ServerVariable::isOn('HTTPS') || (int) ServerVariable::get('SERVER_PORT') === 443) {
            return 'https';
        }
        if ($this->fromTrustedProxy() && strtolower(ServerVariable::get('HTTP_X_FORWARDED_PROTO')) === 'https') {
            return 'https';
        }

        return 'http';
    }

    private function fromTrustedProxy(): bool
    {
        $remote = @inet_pton(ServerVariable::get('REMOTE_ADDR'));
        if ($remote === false) {
            return false;
        }
        foreach ($this->proxies as [$address, $prefix]) {
            if (self::inRange($remote, $address, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // Whether the first prefix bits of two packed addresses of the same family are the same
    private static function inRange(string $remote, string $address, int $prefix): bool
    {
        if (\strlen($remote) !== \strlen($address)) {
            return false;
        }
        $bytes = intdiv($prefix, 8);
        if (substr($remote, 0, $bytes) !== substr($address, 0, $bytes)) {
            return false;
        }
        $bits = $prefix % 8;
        if ($bits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (\ord($remote[$bytes]) & $mask) === (\ord($address[$bytes]) & $mask);
    }

    private static function origin(string $serviceUrl): string
    {
        $parts = parse_url($serviceUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (
            $parts === false || !\in_array($scheme, ['http', 'https'], true) || !isset($parts['host'])
            || !\in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])
        ) {
            throw new \InvalidArgumentException("SERVICE_URL \"$serviceUrl\" must be the scheme and host of the service, e.g. https://minitel.example.org");
        }

        return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * @return list<array{string, int}>
     */
    private static function proxies(string $trustedProxies): array
    {
        $proxies = [];
        foreach (explode(',', $trustedProxies) as $proxy) {
            $proxy = trim($proxy);
            if ($proxy === '') {
                continue;
            }
            $parts = explode('/', $proxy, 2);
            $ip = $parts[0];
            $prefix = $parts[1] ?? null;
            $address = @inet_pton($ip);
            $bits = $address === false ? 0 : \strlen($address) * 8;
            if ($address === false || ($prefix !== null && (!ctype_digit($prefix) || (int) $prefix > $bits))) {
                throw new \InvalidArgumentException("TRUSTED_PROXIES: \"$proxy\" is not an IP address or range");
            }
            $proxies[] = [$address, $prefix === null ? $bits : (int) $prefix];
        }

        return $proxies;
    }
}
