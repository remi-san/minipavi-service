<?php

declare(strict_types=1);

namespace MiniPaviService;

/**
 * Emulator that browsers are sent to, and gateway through which it opens the service
 */
class EmulatorConfig
{
    public const PUBLIC_EMULATOR_URL = 'http://www.minipavi.fr/emulminitel/';
    public const PUBLIC_GATEWAY = DEFAULT_MINIPAVI_GW;
    public const LOCAL_SERVICES_HOST = 'services';

    /**
     * @param ?string $serviceHost host at which the gateway reaches the service, when it is not
     *                             the one the browser used (e.g. the gateway runs in Docker)
     */
    public function __construct(
        private string $url = self::PUBLIC_EMULATOR_URL,
        private string $gateway = self::PUBLIC_GATEWAY,
        private ?string $serviceHost = null,
    ) {}

    public function getEmulatorUrl(string $serviceUrl): string
    {
        return rtrim($this->url, '/')
            . '/index.php?gw='
            . urlencode(
                rtrim($this->gateway, '/')
                . '/?url=' . urlencode($this->gatewayServiceUrl($serviceUrl)),
            );
    }

    /**
     * From the environment, where set: EMULATOR_URL, GATEWAY_URL and SERVICES_INTERNAL_HOST
     * (default "services" when EMULATOR_URL is set, as in the local stack of this repository).
     * Otherwise, the minipavi.fr emulator and gateway, reaching the service at its public URL.
     */
    public static function init(): self
    {
        $url = getenv('EMULATOR_URL') ?: null;

        return new self(
            $url ?? self::PUBLIC_EMULATOR_URL,
            getenv('GATEWAY_URL') ?: self::PUBLIC_GATEWAY,
            getenv('SERVICES_INTERNAL_HOST') ?: ($url !== null ? self::LOCAL_SERVICES_HOST : null),
        );
    }

    // The service URL with the host of the gateway's side, if any (over http, as inside Docker)
    private function gatewayServiceUrl(string $serviceUrl): string
    {
        if ($this->serviceHost === null) {
            return $serviceUrl;
        }

        $path = parse_url($serviceUrl, PHP_URL_PATH) ?? '/';
        $query = parse_url($serviceUrl, PHP_URL_QUERY);

        return 'http://' . $this->serviceHost . $path . ($query !== null ? '?' . $query : '');
    }
}
