<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

/**
 * Where the user connects from, as the gateway tells it with each request
 */
final readonly class Terminal
{
    public function __construct(
        private string $remoteAddr = '',
        private ?string $typeSocket = null,
        private ?string $versionMinitel = null,
    ) {}

    /**
     * IP of the user, or "CALLFROM xxxx" when calling by phone
     */
    public function remoteAddr(): string
    {
        return $this->remoteAddr;
    }

    /**
     * 'websocket' (emulators, ESP32 / Wi-Fi adapters) or 'other' (telnet, phone), null if unknown
     */
    public function typeSocket(): ?string
    {
        return $this->typeSocket;
    }

    /**
     * Version of the Minitel if known, '???' otherwise
     */
    public function versionMinitel(): ?string
    {
        return $this->versionMinitel;
    }
}
