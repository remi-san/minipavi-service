<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Decides who the HTTP request is for: the gateway's requests go to its runner (a
 * ServiceRunner with MiniPaviClient), browsers asking ?web=true to the web runner (a
 * ServiceRunner with HtmlClient), and other browsers are sent to an emulator that opens the
 * service through a gateway: by default the one of EmulatorConfig::init(), from the
 * environment (the local stack of this repository) or else minipavi.fr's.
 */
final class Dispatcher implements Runner
{
    private EmulatorConfig $emulator;

    private RequestUrl $url;

    private LoggerInterface $logger;

    /**
     * @param Runner $gateway runs the service for the gateway
     * @param Runner $web runs the service for a browser asking ?web=true
     * @param ?RequestUrl $url URLs of the service, by default from the environment
     * @param ?LoggerInterface $logger where browsers sent to the emulator and shown a screen
     *                                 are logged; none by default
     */
    public function __construct(
        private Runner $gateway,
        private Runner $web,
        ?EmulatorConfig $emulator = null,
        ?RequestUrl $url = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->emulator = $emulator ?? EmulatorConfig::init();
        $this->url = $url ?? RequestUrl::fromEnvironment();
        $this->logger = $logger ?? new NullLogger();
    }

    public function run(): void
    {
        if (str_contains(ServerVariable::get('HTTP_USER_AGENT'), 'MiniPAVI')) {
            $this->gateway->run();

            return;
        }

        // Only web=true: any other value sends the browser to the emulator
        if (ServerVariable::query('web') === 'true') {
            $this->logger->info('Screen shown in a browser', ['route' => ServerVariable::query('route')]);
            $this->web->run();

            return;
        }

        $url = $this->emulator->getEmulatorUrl($this->url->current());
        $this->logger->info('Browser sent to the emulator', ['url' => $url]);
        header('Location: ' . $url);
    }
}
