<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\KeyHandler;

/**
 * Reads the function key a key is, for Request::functionKey(): null for a connection code
 *
 * @internal
 *
 * @implements KeyHandler<?FunctionKey>
 */
final readonly class FunctionKeyReader implements KeyHandler
{
    public function pressed(FunctionKey $key): FunctionKey
    {
        return $key;
    }

    public function connected(ConnectionCode $code): null
    {
        return null;
    }

    public function disconnected(): null
    {
        return null;
    }

    public function calledInBackground(): null
    {
        return null;
    }
}
