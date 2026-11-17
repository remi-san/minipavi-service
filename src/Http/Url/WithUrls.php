<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Url;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Computes the URLs of the service for the request (RequestUrl), once, and passes it on with
 * them in its attributes (UrlAttribute)
 */
final readonly class WithUrls implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
        private RequestUrl $url,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request->withAttribute(
            UrlAttribute::Service->value,
            $this->url->script($request),
        )->withAttribute(UrlAttribute::Current->value, $this->url->current($request)));
    }
}
