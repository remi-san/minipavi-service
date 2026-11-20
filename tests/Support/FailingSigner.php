<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Core\Signing\ContextSigner;

/**
 * Signer that cannot sign: no context can be sent, not even with the error response, so the
 * service gives no response at all
 */
final class FailingSigner implements ContextSigner
{
    /**
     * @throws \RuntimeException always
     */
    public function sign(string $context): string
    {
        throw new \RuntimeException('Cannot sign');
    }

    public function verify(string $signed): string
    {
        return $signed;
    }
}
