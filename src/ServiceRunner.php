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

    private RequestUrl $url;

    /**
     * @param ?RequestUrl $url URLs of the service, by default from the environment
     */
    public function __construct(
        private Service $service,
        ?EmulatorConfig $emulator = null,
        ?RequestUrl $url = null,
    ) {
        $this->emulator = $emulator ?? EmulatorConfig::init();
        $this->url = $url ?? RequestUrl::fromEnvironment();
    }

    public function run(): void
    {
        $this->check();
        $this->service->run();
    }

    private function check(): void
    {
        if (!str_contains(ServerVariable::get('HTTP_USER_AGENT'), 'MiniPAVI')) {
            $this->redirectBrowser();
            exit;
        }
    }

    private function redirectBrowser(): void
    {
        header('Location: ' . $this->emulator->getEmulatorUrl($this->url->current()));
    }
}
