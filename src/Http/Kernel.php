<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * What public/index.php runs: creates the HTTP request from the globals (the only place they
 * are read), with symfony/http-foundation, converted to PSR-7; has the handler (PSR-15) handle
 * it; and sends the response it returns
 */
final readonly class Kernel
{
    /**
     * @param HttpMessageFactoryInterface $requestFactory converts the request to PSR-7, e.g.
     *                                                    the bridge's PsrHttpFactory
     */
    public function __construct(
        private RequestHandlerInterface $handler,
        private HttpMessageFactoryInterface $requestFactory,
        private ResponseEmitter $emitter,
    ) {}

    public function run(): void
    {
        $response = $this->handler->handle(
            $this->requestFactory->createRequest(
                Request::createFromGlobals(),
            ),
        );

        // Already written (MiniPaviCli::send()), or nothing to answer. To revisit (#75): the
        // one type check of this design
        if ($response instanceof EmptyHttpResponse) {
            return;
        }

        $this->emitter->emit($response);
    }
}
