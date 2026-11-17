<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the service for a client: reads the request with it, has the service answer it, and
 * sends the answer with it. MiniPaviClient for the gateway, HtmlClient for a browser; the
 * Dispatcher decides which runner a request is for.
 */
final class ServiceRunner implements Runner
{
    private LoggerInterface $logger;

    /**
     * @param Client $client reads the requests, sends the answers
     * @param ?LoggerInterface $logger where answers that can't be sent are logged; none by
     *                                 default
     */
    public function __construct(
        private Service $service,
        private Client $client,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Runs the service for the request of the client
     */
    public function run(): void
    {
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
}
