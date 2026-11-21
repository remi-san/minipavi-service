<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

/**
 * What the gateway sends as the key (fctn) of a request that isn't a function key
 * (FunctionKey): a connection, a disconnection, or a background call
 */
enum ConnectionCode: string implements Key
{
    // A new connection of the user
    case Connect = 'CNX';
    // A connection from another service (CallMode::Connect)
    case DirectConnect = 'DIRECTCNX';
    case Disconnect = 'FIN';
    // The start of the code of a background call of the gateway (BGCALL-WM-..., e.g. for web
    // media), which the user didn't make: nothing to respond to
    case BackgroundCall = 'BGCALL-WM-';

    /**
     * Code of a key the gateway sent, null for a function key (or anything else)
     */
    public static function of(string $key): ?self
    {
        $code = self::tryFrom($key);
        if ($code !== null && $code !== self::BackgroundCall) {
            return $code;
        }

        return str_starts_with($key, self::BackgroundCall->value) && $key !== self::BackgroundCall->value
            ? self::BackgroundCall
            : null;
    }

    /**
     * Null: a connection code isn't a function key
     */
    public function functionKey(): ?FunctionKey
    {
        return null;
    }

    /**
     * The code itself: it is a connection code
     */
    public function connectionCode(): self
    {
        return $this;
    }

    /**
     * Whether the user connects: a new connection, or one from another service
     */
    public function isConnection(): bool
    {
        return $this === self::Connect || $this === self::DirectConnect;
    }
}
