<?php

declare(strict_types=1);

namespace MiniPaviService;

/**
 * Runs the service for the requests of a MiniPavi gateway, and sends browsers to an emulator
 * that opens the service through a gateway: by default the one of EmulatorConfig::init(), from
 * the environment (the local stack of this repository) or else minipavi.fr's.
 */
final class ServiceRunner
{
    private EmulatorConfig $emulator;

    public function __construct(
        private Service $service,
        ?EmulatorConfig $emulator = null,
    ) {
        $this->emulator = $emulator ?? EmulatorConfig::init();
    }

    public function run(): void
    {
        $this->check();
        $this->service->run();
    }

    private function check(): void
    {
        if (!str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'MiniPAVI')) {
            $this->redirectBrowser();
            exit;
        }
    }

    private function redirectBrowser(): void
    {
        $serviceUrl = self::scheme() . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $this->emulator->getEmulatorUrl($serviceUrl));
    }

    // https if the request came over TLS, directly or through a proxy that says so
    private static function scheme(): string
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return 'https';
        }
        if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return 'https';
        }
        return 'http';
    }
}
