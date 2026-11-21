<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Minitel;

use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Tests\Support\DescribedKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeyTest extends TestCase
{
    /**
     * @return iterable<string, array{Key, string}>
     */
    public static function keys(): iterable
    {
        foreach (FunctionKey::cases() as $key) {
            yield $key->name => [$key, 'pressed ' . $key->value];
        }
        yield 'connection' => [ConnectionCode::Connect, 'connected with CNX'];
        yield 'connection from another service' => [ConnectionCode::DirectConnect, 'connected with DIRECTCNX'];
        yield 'disconnection' => [ConnectionCode::Disconnect, 'disconnected'];
        yield 'background call' => [ConnectionCode::BackgroundCall, 'called in background'];
    }

    #[DataProvider('keys')]
    public function testAKeyTellsTheHandlerWhatHappened(Key $key, string $happened): void
    {
        self::assertSame($happened, $key->handleWith(new DescribedKey()));
    }
}
