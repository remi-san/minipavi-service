<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Gateway;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Core\Terminal;
use MiniPaviService\Gateway\MiniPaviClient;
use PHPUnit\Framework\TestCase;

/**
 * Reading the gateway's request: MiniPaviCli::start() puts the decoded JSON in its static
 * properties, set here directly
 */
final class MiniPaviClientTest extends TestCase
{
    protected function tearDown(): void
    {
        MiniPaviCli::$fctn = '';
        MiniPaviCli::$content = [];
        MiniPaviCli::$context = '';
        MiniPaviCli::$remoteAddr = '';
        MiniPaviCli::$typeSocket = null;
        MiniPaviCli::$versionMinitel = null;
    }

    public function testReadsTheRequest(): void
    {
        MiniPaviCli::$fctn = 'ENVOI';
        MiniPaviCli::$content = ['ab', 'cd'];
        MiniPaviCli::$context = 'a:0:{}';
        MiniPaviCli::$remoteAddr = 'CALLFROM 0102030405';
        MiniPaviCli::$typeSocket = 'websocket';
        MiniPaviCli::$versionMinitel = 'Cv5';
        $client = new MiniPaviClient();

        self::assertSame('ENVOI', $client->fctn());
        self::assertSame(['ab', 'cd'], $client->content());
        self::assertSame('abcd', $client->contentAsString());
        self::assertSame('a:0:{}', $client->context());
        self::assertSame('CALLFROM 0102030405', $client->remoteAddr());
        self::assertSame('websocket', $client->typeSocket());
        self::assertSame('Cv5', $client->versionMinitel());
        self::assertEquals(new Terminal('CALLFROM 0102030405', 'websocket', 'Cv5'), $client->terminal());
    }

    public function testRecognizesConnectionsAndDisconnections(): void
    {
        $client = new MiniPaviClient();

        foreach (['CNX' => [true, false], 'DIRECTCNX' => [true, false], 'FIN' => [false, true], 'ENVOI' => [false, false]] as $fctn => $expected) {
            MiniPaviCli::$fctn = $fctn;
            self::assertSame($expected, [$client->isConnecting(), $client->isDisconnecting()], $fctn);
        }
    }

    public function testValuesThatAreNotTextBecomeEmpty(): void
    {
        MiniPaviCli::$fctn = null;
        MiniPaviCli::$context = ['not', 'text'];
        MiniPaviCli::$typeSocket = new \stdClass();
        MiniPaviCli::$versionMinitel = null;
        $client = new MiniPaviClient();

        self::assertSame('', $client->fctn());
        self::assertSame('', $client->context());
        self::assertNull($client->typeSocket());
        self::assertNull($client->versionMinitel());
    }

    public function testContentIsAListOfTexts(): void
    {
        $client = new MiniPaviClient();

        MiniPaviCli::$content = 'one line';
        self::assertSame(['one line'], $client->content());

        MiniPaviCli::$content = [2 => 'a', 5 => 12, 7 => null, 9 => ['x']];
        self::assertSame(['a', '12', '', ''], $client->content());

        MiniPaviCli::$content = null;
        self::assertSame([], $client->content());

        $client->clearContent();
        self::assertSame([], $client->content());
    }
}
