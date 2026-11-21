<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\KeyHandler;

/**
 * Reads the connection code a key is, for Request::connectionCode(): null for a function key
 *
 * @internal
 *
 * @implements KeyHandler<?ConnectionCode>
 */
final readonly class ConnectionCodeReader implements KeyHandler
{
    public function pressed(FunctionKey $key): null
    {
        return null;
    }

    public function connected(ConnectionCode $code): ConnectionCode
    {
        return $code;
    }

    public function disconnected(): ConnectionCode
    {
        return ConnectionCode::Disconnect;
    }

    public function calledInBackground(): ConnectionCode
    {
        return ConnectionCode::BackgroundCall;
    }
}
