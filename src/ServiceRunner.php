<?php

declare(strict_types=1);

namespace MiniPaviService;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the service for the requests of a MiniPavi gateway, and sends browsers to an emulator
 * that opens the service through a gateway: by default the one of EmulatorConfig::init(), from
 * the environment (the local stack of this repository) or else minipavi.fr's.
 */
final class ServiceRunner
{
    private EmulatorConfig $emulator;

    private RequestUrl $url;

    private LoggerInterface $logger;

    /**
     * @param ?RequestUrl $url URLs of the service, by default from the environment
     * @param ?LoggerInterface $logger where browsers sent to the emulator are logged; none
     *                                 by default
     */
    public function __construct(
        private Service $service,
        ?EmulatorConfig $emulator = null,
        ?RequestUrl $url = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->emulator = $emulator ?? EmulatorConfig::init();
        $this->url = $url ?? RequestUrl::fromEnvironment();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Runs the service if the request comes from a gateway, sends the browser to the
     * emulator otherwise
     */
    public function run(): void
    {
        if (!str_contains(ServerVariable::get('HTTP_USER_AGENT'), 'MiniPAVI')) {
            $this->redirectBrowser();

            return;
        }
        $this->service->run();
    }

    private function redirectBrowser(): void
    {
        $url = $this->emulator->getEmulatorUrl($this->url->current());
        $this->logger->info('Browser sent to the emulator', ['url' => $url]);
        header('Location: ' . $url);
    }
}
