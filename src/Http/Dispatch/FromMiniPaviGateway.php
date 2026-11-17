<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A request of the MiniPavi gateway: its User-Agent names MiniPAVI
 */
final readonly class FromMiniPaviGateway implements RequestMatcher
{
    public function matches(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('User-Agent'), 'MiniPAVI');
    }
}
