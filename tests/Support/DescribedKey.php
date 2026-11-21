<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\KeyHandler;

/**
 * Says what a key told it happened, and with which key
 *
 * @implements KeyHandler<string>
 */
final class DescribedKey implements KeyHandler
{
    public function pressed(FunctionKey $key): string
    {
        return 'pressed ' . $key->value;
    }

    public function connected(ConnectionCode $code): string
    {
        return 'connected with ' . $code->value;
    }

    public function disconnected(): string
    {
        return 'disconnected';
    }

    public function calledInBackground(): string
    {
        return 'called in background';
    }
}
