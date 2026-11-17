<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Core\Terminal;
use MiniPaviService\Gateway\GatewayRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCarriesTheUserInput(): void
    {
        $terminal = new Terminal('CALLFROM 0102030405', 'other', '???');
        $request = new Request(Controller::STATE_READ_INPUT, ['page' => 2], FunctionKey::Send->value, ['Hello ', 'world'], $terminal);

        self::assertSame(Controller::STATE_READ_INPUT, $request->state());
        self::assertSame(2, $request->parameter('page'));
        self::assertSame('ENVOI', $request->key());
        self::assertSame(['Hello ', 'world'], $request->content());
        self::assertSame('Hello world', $request->contentAsString());
        self::assertSame($terminal, $request->terminal());
    }

    public function testFunctionKeyIsNullForConnectionCodes(): void
    {
        self::assertSame(FunctionKey::Guide, (new Request(key: 'GUIDE'))->functionKey());
        self::assertNull((new Request(key: GatewayRequest::FCTN_CONNECT))->functionKey());
        self::assertNull((new Request())->functionKey());
    }

    public function testWithoutInputNothingWasTyped(): void
    {
        $request = new Request();

        self::assertSame(Controller::STATE_INIT, $request->state());
        self::assertSame('', $request->key());
        self::assertSame([], $request->content());
        self::assertSame('', $request->contentAsString());
        self::assertEquals(new Terminal(), $request->terminal());
    }

    public function testTerminalTellsWhereTheUserIs(): void
    {
        $terminal = new Terminal('192.0.2.1', 'websocket', 'Cv5');

        self::assertSame('192.0.2.1', $terminal->remoteAddr());
        self::assertSame('websocket', $terminal->typeSocket());
        self::assertSame('Cv5', $terminal->versionMinitel());
        self::assertNull((new Terminal())->typeSocket());
    }

    /**
     * Values a parameter may have (from the JSON of the context, possibly forged), and the
     * int read
     *
     * @return iterable<string, array{mixed, int}>
     */
    public static function intValues(): iterable
    {
        yield 'int' => [2, 2];
        yield 'negative int' => [-3, -3];
        yield 'integer text' => ['12', 12];
        yield 'integral float' => [3.0, 3];
        yield 'missing' => [null, 7];
        yield 'float' => [2.5, 7];
        yield 'huge float' => [1e20, 7];
        yield 'infinite float' => [INF, 7];
        yield 'integer text too large' => ['99999999999999999999', 7];
        yield 'other text' => ['2 pages', 7];
        yield 'empty text' => ['', 7];
        yield 'bool' => [true, 7];
        yield 'array' => [[1], 7];
    }

    #[DataProvider('intValues')]
    public function testIntCoercesOrFallsBackToTheDefault(mixed $value, int $expected): void
    {
        self::assertSame($expected, (new Request(parameters: ['page' => $value]))->int('page', 7));
    }

    public function testIntDefaultsToZero(): void
    {
        self::assertSame(0, (new Request())->int('page'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function stringValues(): iterable
    {
        yield 'text' => ['abc', 'abc'];
        yield 'empty text' => ['', ''];
        yield 'int' => [12, '12'];
        yield 'float' => [2.5, '2.5'];
        yield 'missing' => [null, 'default'];
        yield 'bool' => [false, 'default'];
        yield 'array' => [['abc'], 'default'];
    }

    #[DataProvider('stringValues')]
    public function testStringCoercesOrFallsBackToTheDefault(mixed $value, string $expected): void
    {
        self::assertSame($expected, (new Request(parameters: ['query' => $value]))->string('query', 'default'));
        self::assertSame($expected === 'default' ? '' : $expected, (new Request(parameters: ['query' => $value]))->string('query'));
    }

    /**
     * @return iterable<string, array{mixed, ?bool}>
     */
    public static function boolValues(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield '1' => [1, true];
        yield '0' => [0, false];
        yield 'text 1' => ['1', true];
        yield 'text true' => ['true', true];
        yield 'text false' => ['false', false];
        yield 'missing' => [null, null];
        yield 'other int' => [2, null];
        yield 'other text' => ['maybe', null];
        yield 'empty text' => ['', null];
        yield 'array' => [[true], null];
    }

    /**
     * @param ?bool $expected null: the default
     */
    #[DataProvider('boolValues')]
    public function testBoolCoercesOrFallsBackToTheDefault(mixed $value, ?bool $expected): void
    {
        $request = new Request(parameters: ['logged' => $value]);

        self::assertSame($expected ?? true, $request->bool('logged', true));
        self::assertSame($expected ?? false, $request->bool('logged'));
    }

    public function testArrayGivesAnArrayOrTheDefault(): void
    {
        $request = new Request(parameters: ['ids' => [1, 2], 'name' => 'abc']);

        self::assertSame([1, 2], $request->array('ids'));
        self::assertSame([], $request->array('name'));
        self::assertSame(['x'], $request->array('missing', ['x']));
    }
}
