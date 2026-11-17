<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * What public/index.php runs: creates the HTTP request from the globals (the only place they
 * are read), has the handler handle it, and sends the response it returns
 */
final readonly class Kernel
{
    public function __construct(
        private RequestHandlerInterface $handler,
    ) {}

    public function run(): void
    {
        $factory = new Psr17Factory();
        $request = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest(Request::createFromGlobals());

        $response = $this->handler->handle($request);

        // Already written (MiniPaviCli::send()), or nothing to answer. To revisit (#75): the
        // one type check of this design
        if ($response instanceof EmptyHttpResponse) {
            return;
        }

        (new HttpFoundationFactory())->createResponse($response)->send();
    }
}
