<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\ServerVariable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServerVariableTest extends TestCase
{
    /** @var array<mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testGivesTheVariableAsAString(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['argv'] = ['not', 'a', 'string'];
        unset($_SERVER['HTTP_X_MISSING']);

        self::assertSame('example.org', ServerVariable::get('HTTP_HOST'));
        self::assertSame('', ServerVariable::get('argv'));
        self::assertSame('', ServerVariable::get('HTTP_X_MISSING'));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function flags(): iterable
    {
        yield 'not set' => [null, false];
        yield 'empty' => ['', false];
        yield 'zero' => ['0', false];
        yield 'off' => ['off', false];
        yield 'OFF' => ['OFF', false];
        yield 'on' => ['on', true];
        yield '1' => ['1', true];
    }

    #[DataProvider('flags')]
    public function testFlagIsOnUnlessEmptyZeroOrOff(?string $value, bool $on): void
    {
        unset($_SERVER['HTTPS']);
        if ($value !== null) {
            $_SERVER['HTTPS'] = $value;
        }

        self::assertSame($on, ServerVariable::isOn('HTTPS'));
    }
}
