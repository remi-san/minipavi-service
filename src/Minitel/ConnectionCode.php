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
     * The user connected (Connect, DirectConnect) or disconnected, or the gateway called in
     * the background
     */
    public function handleWith(KeyHandler $handler): mixed
    {
        return match ($this) {
            self::Connect, self::DirectConnect => $handler->connected($this),
            self::Disconnect => $handler->disconnected(),
            self::BackgroundCall => $handler->calledInBackground(),
        };
    }
}
