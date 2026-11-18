<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Core\Signing\ContextSigner;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * Answer to a request of the MiniPavi gateway: the Videotex content to show, the context to
 * give back with the next request, and what the gateway does next
 */
final readonly class GatewayResponse
{
    /**
     * @param Vdt $vdt Videotex content, stringified by MiniPaviClient only
     * @param string $context serialized context (Context::serialize()), signed by sign() before
     *                        it goes to the gateway
     * @param ?string $next URL the gateway calls next; null: this service's
     * @param ?InputCommand $command command reading an input field (Page::inputCommand()); null: none
     * @param ?CallMode $directCall calls next at once, without waiting for the user; null: waits
     * @param bool $echo whether the gateway echoes what the user types
     */
    public function __construct(
        public Vdt $vdt,
        public string $context,
        public ?string $next = null,
        public ?InputCommand $command = null,
        public ?CallMode $directCall = null,
        public bool $echo = true,
    ) {}

    /**
     * The answer with its context signed by signer, so that it can't be forged on its way back
     * (GatewayRequest::verify())
     */
    public function sign(ContextSigner $signer): self
    {
        return new self(
            $this->vdt,
            $signer->sign($this->context),
            $this->next,
            $this->command,
            $this->directCall,
            $this->echo,
        );
    }
}
