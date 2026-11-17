<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Decides who the HTTP request is for: the gateway's requests go to its handler (a
 * ServiceRunner with MiniPaviClient), browsers asking ?web=true to the web one (a
 * ServiceRunner with HtmlClient), and other browsers to the browser one (EmulatorRedirect).
 * Making the choice extensible is #76.
 */
final readonly class Dispatcher implements RequestHandlerInterface
{
    private LoggerInterface $logger;

    /**
     * @param RequestHandlerInterface $gateway handles the gateway's requests
     * @param RequestHandlerInterface $web handles a browser asking ?web=true
     * @param RequestHandlerInterface $browser handles other browsers
     * @param ?LoggerInterface $logger where browsers shown a screen are logged; none by default
     */
    public function __construct(
        private RequestHandlerInterface $gateway,
        private RequestHandlerInterface $web,
        private RequestHandlerInterface $browser,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (str_contains($request->getHeaderLine('User-Agent'), 'MiniPAVI')) {
            return $this->gateway->handle($request);
        }

        // Only web=true: any other value is a browser like the others
        $query = $request->getQueryParams();
        if (($query['web'] ?? null) === 'true') {
            $this->logger->info('Screen shown in a browser', ['route' => \is_string($query['route'] ?? null) ? $query['route'] : null]);

            return $this->web->handle($request);
        }

        return $this->browser->handle($request);
    }
}
