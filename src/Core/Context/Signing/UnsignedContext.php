<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context\Signing;

/**
 * Doesn't sign the context (null object): anyone can send one, with any route and parameters.
 * For a service without a key (e.g. the local stack), and for tests.
 */
final readonly class UnsignedContext implements ContextSigner
{
    public function sign(string $context): string
    {
        return $context;
    }

    public function verify(string $signed): string
    {
        return $signed;
    }
}
