<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Core\Terminal;
use MiniPaviService\Page\Display\Color;
use MiniPaviService\Page\Display\EndKey;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class KeyTest extends TestCase
{
    public function testKeyIsABadgeInReverseVideo(): void
    {
        $screen = Vdt::fromString((new Key('Envoi', 20, 30, Color::Red))->getContent(new Terminal()))->screen();

        // An underlined space, then " Envoi " in reverse video, red
        self::assertSame('Envoi', implode('', array_map(fn(int $col) => $screen->cell(20, $col)?->bytes, range(32, 36))));
        $cell = $screen->cell(20, 32);
        if ($cell === null) {
            self::fail('Nothing drawn at row 20, column 32');
        }
        self::assertSame("\x5D", $cell->attributes['inverse']);
        self::assertSame(Color::Red->text()[1], $cell->attributes['fg']);
    }

    public function testEndKeyIsOnTheLastRowForAMinitel(): void
    {
        $vdt = (new EndKey())->getContent(new Terminal('CALLFROM 0102030405', 'other', '???'));

        self::assertStringNotContainsString('[Maj] +', $vdt);
        self::assertSame('Connexion/fin', self::text(Vdt::fromString($vdt), 24, 14, 13));
    }

    public function testEndKeyTellsAdaptersToPressShift(): void
    {
        // ESP32 / Minimit adapters: a websocket with a known Minitel
        $vdt = (new EndKey())->getContent(new Terminal('192.0.2.1', 'websocket', 'Cv5'));

        self::assertSame('[Maj] +', self::text(Vdt::fromString($vdt), 23, 9, 7));
        self::assertSame('Connexion/fin', self::text(Vdt::fromString($vdt), 23, 18, 13));
    }

    public function testEndKeyOfAnEmulatorIsOnTheLastRow(): void
    {
        // An emulator is a websocket without a known Minitel
        $vdt = (new EndKey(row: 22))->getContent(new Terminal('192.0.2.1', 'websocket', '???'));

        self::assertSame('Connexion/fin', self::text(Vdt::fromString($vdt), 22, 14, 13));
    }

    private static function text(Vdt $vdt, int $row, int $col, int $length): string
    {
        $screen = $vdt->screen();

        return implode('', array_map(fn(int $c) => $screen->cell($row, $c)->bytes ?? ' ', range($col, $col + $length - 1)));
    }
}
