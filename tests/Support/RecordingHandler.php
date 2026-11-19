<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler keeping the requests it handles, responding to them with its name
 */
final class RecordingHandler implements RequestHandlerInterface
{
    /** @var list<ServerRequestInterface> */
    public array $requests = [];

    public function __construct(
        private string $name = 'handled',
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response(200, [], $this->name);
    }
}
