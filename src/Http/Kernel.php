<?php

declare(strict_types=1);

namespace MiniPaviService\Http;

use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * What public/index.php runs: creates the PSR-7 request from the globals (the only place they
 * are read), has the handler (PSR-15) handle it, and sends the response it returns
 */
final readonly class Kernel
{
    /**
     * @param ServerRequestCreatorInterface $requestCreator creates the request from the
     *                                                      globals, e.g. nyholm/psr7-server's
     *                                                      ServerRequestCreator
     */
    public function __construct(
        private RequestHandlerInterface $handler,
        private ServerRequestCreatorInterface $requestCreator,
        private ResponseEmitter $emitter,
    ) {}

    public function run(): void
    {
        $this->emitter->emit($this->handler->handle($this->requestCreator->fromGlobals()));
    }
}
