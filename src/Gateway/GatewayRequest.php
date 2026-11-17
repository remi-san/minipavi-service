<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\Terminal;

/**
 * Request of the MiniPavi gateway: what the user did (the key pressed, the text typed), the
 * context sent with the previous answer, and where the user connects from
 */
final readonly class GatewayRequest
{
    // Codes of key() for connections (function keys: see FunctionKey)
    public const FCTN_CONNECT = 'CNX';
    public const FCTN_DIRECT_CONNECT = 'DIRECTCNX';
    public const FCTN_DISCONNECT = 'FIN';

    /**
     * @param string $key function key pressed (FunctionKey), or connection code (FCTN_*)
     * @param list<string> $content text typed, one string per line of the input field
     * @param string $context context sent with the previous answer ('' if none)
     */
    public function __construct(
        public string $key,
        public array $content = [],
        public string $context = '',
        public Terminal $terminal = new Terminal(),
    ) {}

    public function isConnecting(): bool
    {
        return $this->key === self::FCTN_CONNECT || $this->key === self::FCTN_DIRECT_CONNECT;
    }

    public function isDisconnecting(): bool
    {
        return $this->key === self::FCTN_DISCONNECT;
    }
}
