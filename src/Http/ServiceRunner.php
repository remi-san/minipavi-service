<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\MiniPaviClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the service for the requests of a MiniPavi gateway: reads the request with the gateway
 * client, has the service answer it, and sends the answer. Sends browsers to an emulator that
 * opens the service through a gateway: by default the one of EmulatorConfig::init(), from the
 * environment (the local stack of this repository) or else minipavi.fr's.
 */
final class ServiceRunner
{
    private EmulatorConfig $emulator;

    private RequestUrl $url;

    private LoggerInterface $logger;

    /**
     * @param MiniPaviClient $client client of the gateway: reads its requests, sends the answers
     * @param ?RequestUrl $url URLs of the service, by default from the environment
     * @param ?LoggerInterface $logger where browsers sent to the emulator, and answers that
     *                                 can't be sent, are logged; none by default
     */
    public function __construct(
        private Service $service,
        private MiniPaviClient $client,
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

        try {
            $answer = $this->service->handle($this->client->receive());
        } catch (\Throwable $e) {
            // The request couldn't be read
            $answer = $this->service->error($e);
        }
        if ($answer === null) {
            return;
        }

        try {
            $this->client->send($answer);
        } catch (\Throwable $e) {
            $this->logger->critical('Cannot send the answer: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    private function redirectBrowser(): void
    {
        $url = $this->emulator->getEmulatorUrl($this->url->current());
        $this->logger->info('Browser sent to the emulator', ['url' => $url]);
        header('Location: ' . $url);
    }
}
