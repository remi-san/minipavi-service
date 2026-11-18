<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Emulator;

use MiniPaviService\Http\Url\UrlAttribute;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Sends a browser to an emulator that opens the service, at the URL it asked for
 * (UrlAttribute::Current), through a gateway (EmulatorConfig)
 */
final readonly class EmulatorRedirect implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param ?LoggerInterface $logger where browsers sent to the emulator are logged; none by
     *                                 default
     */
    public function __construct(
        private EmulatorConfig $emulator,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $url = $this->emulator->emulatorUrl(UrlAttribute::Current->of($request));
        $this->logger->info('Browser sent to the emulator', ['url' => $url]);

        return new Response(302, ['Location' => $url]);
    }
}
