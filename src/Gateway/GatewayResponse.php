<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Vdt\Vdt;

/**
 * Answer to a request of the MiniPavi gateway: the Videotex content to show, the context to
 * give back with the next request, and what the gateway does next
 */
final readonly class GatewayResponse
{
    /**
     * @param Vdt $vdt Videotex content, stringified by MiniPaviClient only
     * @param string $context serialized and signed context (ContextSerializer::serialize()),
     *                        given back with the next request
     * @param Command $command what the gateway does next (e.g. reading an input field,
     *                         Page::inputCommand()); by default, waits for a key, then calls
     *                         this service
     */
    public function __construct(
        public Vdt $vdt,
        public string $context,
        public Command $command = new WaitForKeyCommand(),
    ) {}
}
