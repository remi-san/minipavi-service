<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Emulator;

/**
 * Emulator that browsers are sent to, and gateway through which it opens the service
 */
final readonly class EmulatorConfig
{
    public const PUBLIC_EMULATOR_URL = 'http://www.minipavi.fr/emulminitel/';
    public const PUBLIC_GATEWAY = 'wss://go.minipavi.fr:8181';
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

    public function emulatorUrl(string $serviceUrl): string
    {
        return (
            rtrim($this->url, characters: '/')
            . '/index.php?gw='
            . urlencode(
                rtrim($this->gateway, characters: '/') . '/?url=' . urlencode($this->gatewayServiceUrl($serviceUrl)),
            )
        );
    }

    /**
     * From the values of the environment (EMULATOR_URL, GATEWAY_URL, SERVICES_INTERNAL_HOST),
     * read by the service's container: with an emulator (the local stack), its gateway and the
     * services' host on the gateway's side ("services" unless given); without one,
     * minipavi.fr's emulator and gateway
     */
    public static function fromValues(?string $emulatorUrl, ?string $gatewayUrl, ?string $servicesInternalHost): self
    {
        $emulatorUrl = $emulatorUrl === '' ? null : $emulatorUrl;
        // With a local emulator, its gateway reaches the services at the local stack's host
        $defaultServicesHost = $emulatorUrl !== null ? self::LOCAL_SERVICES_HOST : null;

        return new self(
            $emulatorUrl ?? self::PUBLIC_EMULATOR_URL,
            $gatewayUrl === null || $gatewayUrl === '' ? self::PUBLIC_GATEWAY : $gatewayUrl,
            $servicesInternalHost === null || $servicesInternalHost === ''
                ? $defaultServicesHost
                : $servicesInternalHost,
        );
    }

    // The service URL with the host of the gateway's side, if any (over http, as inside Docker)
    private function gatewayServiceUrl(string $serviceUrl): string
    {
        if ($this->serviceHost === null) {
            return $serviceUrl;
        }

        // false for a URL it can't parse: its path is then /
        $path = parse_url($serviceUrl, PHP_URL_PATH);
        $query = parse_url($serviceUrl, PHP_URL_QUERY);

        return (
            'http://'
            . $this->serviceHost
            . (\is_string($path) ? $path : '/')
            . (\is_string($query) ? '?' . $query : '')
        );
    }
}
