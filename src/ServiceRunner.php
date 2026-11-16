<?php

namespace MiniPaviService;

/**
 * Runs the service for the requests of a MiniPavi gateway, and sends browsers to an emulator
 * that opens the service through a gateway:
 *  - with EMULATOR_URL set (the local stack of this repository): that emulator, and the gateway
 *    GATEWAY_URL, which reaches the service at SERVICES_INTERNAL_HOST (default "services");
 *  - otherwise (a service on a public server): the minipavi.fr emulator and gateway, reaching
 *    the service at the URL the browser asked for.
 */
final class ServiceRunner {

    public const PUBLIC_EMULATOR_URL = 'http://www.minipavi.fr/emulminitel/';

    public function __construct(private Service $service) {
    }

    public function run(): void {

        $this->check();

        $this->service->run();
    }

    private function check(): void {
        if (strpos(@$_SERVER['HTTP_USER_AGENT'],'MiniPAVI') === false) {
            $this->redirectBrowser();
            exit;
        }
    }

    private function redirectBrowser(): void {
        $emulator = getenv('EMULATOR_URL');
        if ($emulator) {
            $serviceUrl = 'http://'.(getenv('SERVICES_INTERNAL_HOST') ?: 'services').$_SERVER['REQUEST_URI'];
            $gateway = getenv('GATEWAY_URL') ?: 'ws://localhost:8182/';
        } else {
            $serviceUrl = $this->scheme().'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            $emulator = self::PUBLIC_EMULATOR_URL;
            $gateway = DEFAULT_MINIPAVI_GW;
        }
        $gw = rtrim($gateway,'/').'/?url='.urlencode($serviceUrl);
        header('Location: '.rtrim($emulator,'/').'/index.php?gw='.urlencode($gw));
    }

    // https if the request came over TLS, directly or through a proxy that says so
    private function scheme(): string {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            return 'https';
        if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            return 'https';
        return 'http';
    }
}
