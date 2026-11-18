<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Signing;

/**
 * Signs the context sent to the gateway, and checks the one given back (ContextSerializer),
 * so that a context the service didn't send (e.g. POSTed by anyone, with another route or
 * other parameters) isn't restored: HmacContextSigner, or UnsignedContext not to sign it
 */
interface ContextSigner
{
    /**
     * The context, with what verify() checks
     */
    public function sign(string $context): string;

    /**
     * The context signed by sign()
     *
     * @throws ForgedContextException if sign() didn't give it
     */
    public function verify(string $signed): string;
}
