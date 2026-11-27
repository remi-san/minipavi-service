<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

/**
 * Where the user connects from, as the gateway tells it with each request
 */
final readonly class Terminal
{
    // What the gateway tells of the connection (typesocket, versionminitel)
    private const WEBSOCKET = 'websocket';
    private const OTHER = 'other';
    private const UNKNOWN_VERSION = '???';

    /**
     * @param string $remoteAddr IP of the user, or "CALLFROM xxxx" when calling by phone
     * @param ?string $typeSocket how the user connects (typesocket), read by isAdapter() and
     *                            isTelnetOrPhone()
     * @param ?string $versionMinitel the Minitel's version (versionminitel), read by
     *                                isAdapter()
     */
    public function __construct(
        public string $remoteAddr = '',
        private ?string $typeSocket = null,
        private ?string $versionMinitel = null,
    ) {}

    /**
     * Whether the user connects through an ESP32 or Minimit adapter: a websocket with a known
     * Minitel (emulators, websockets too, don't know it). Their function keys need Shift.
     */
    public function isAdapter(): bool
    {
        return (
            $this->typeSocket === self::WEBSOCKET
            && $this->versionMinitel !== null
            && $this->versionMinitel !== self::UNKNOWN_VERSION
        );
    }

    /**
     * Whether the user connects by telnet or by phone, rather than through a websocket
     */
    public function isTelnetOrPhone(): bool
    {
        return $this->typeSocket === self::OTHER;
    }
}
