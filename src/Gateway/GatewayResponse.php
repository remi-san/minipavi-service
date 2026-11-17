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
     * @param string $context serialized context (Context::serialize())
     * @param ?string $next URL the gateway calls next; null: this service's
     * @param ?array<string, mixed> $command command for the gateway (e.g. InputCommand::toArray())
     * @param ?CallMode $directCall calls next at once, without waiting for the user; null: waits
     * @param bool $echo whether the gateway echoes what the user types
     */
    public function __construct(
        public Vdt $vdt,
        public string $context,
        public ?string $next = null,
        public ?array $command = null,
        public ?CallMode $directCall = null,
        public bool $echo = true,
    ) {}
}
