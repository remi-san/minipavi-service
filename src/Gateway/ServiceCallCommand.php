<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * Command calling a service at once, without waiting for the user (Response::connectTo()):
 * another service, or this one as the continuation of the session
 */
final readonly class ServiceCallCommand implements Command
{
    /**
     * @param string $url URL of the service called
     * @param CallMode $mode as a new connection, or as the continuation of the session
     */
    public function __construct(
        public string $url,
        public CallMode $mode,
    ) {}

    public function sendWith(CommandSender $sender): mixed
    {
        return $sender->callService($this);
    }
}
