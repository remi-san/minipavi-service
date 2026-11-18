<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Color;
use MiniPaviService\Page\Display\EndKey;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class KeyTest extends TestCase
{
    public function testKeyIsABadgeInReverseVideo(): void
    {
        $screen = (new Key('Envoi', new Position(20, 30), Color::Red))
            ->toVdt(new Terminal())
            ->screen();

        // An underlined space, then " Envoi " in reverse video, red
        self::assertSame('Envoi', implode('', array_map(
            static fn(int $col) => $screen->cell(20, $col)?->bytes,
            range(32, end: 36),
        )));
        $cell = $screen->cell(row: 20, col: 32);
        if ($cell === null) {
            self::fail('Nothing drawn at row 20, column 32');
        }
        self::assertTrue($cell->attributes->isInverse());
        self::assertSame(Color::Red, $cell->attributes->foreground());
    }

    public function testEndKeyIsOnTheLastRowForAMinitel(): void
    {
        $vdt = (new EndKey('Connexion/fin', '[Maj] +'))->toVdt(new Terminal('CALLFROM 0102030405', 'other', '???'));

        self::assertStringNotContainsString('[Maj] +', $vdt->toString());
        self::assertSame('Connexion/fin', self::text($vdt, row: 24, col: 14, length: 13));
    }

    public function testEndKeyTellsAdaptersToPressShift(): void
    {
        // ESP32 / Minimit adapters: a websocket with a known Minitel
        $vdt = (new EndKey('Connexion/fin', '[Maj] +'))->toVdt(new Terminal('192.0.2.1', 'websocket', 'Cv5'));

        self::assertSame('[Maj] +', self::text($vdt, row: 23, col: 9, length: 7));
        self::assertSame('Connexion/fin', self::text($vdt, row: 23, col: 18, length: 13));
    }

    public function testEndKeyIsCenteredWhateverItsLabels(): void
    {
        $minitel = (new EndKey('Fin', 'Shift'))->toVdt(new Terminal('CALLFROM 0102030405', 'other', '???'));
        $adapter = (new EndKey('Fin', 'Shift'))->toVdt(new Terminal('192.0.2.1', 'websocket', 'Cv5'));

        // The badge " Fin " has a cell on each side: 7 cells, from column 17
        self::assertSame('Fin', self::text($minitel, row: 24, col: 19, length: 3));
        // "Shift" and the badge: 12 cells, from column 15
        self::assertSame('Shift', self::text($adapter, row: 23, col: 15, length: 5));
        self::assertSame('Fin', self::text($adapter, row: 23, col: 22, length: 3));
    }

    public function testEndKeyOfAnEmulatorIsOnTheLastRow(): void
    {
        // An emulator is a websocket without a known Minitel
        $vdt = (new EndKey('Connexion/fin', '[Maj] +', position: new Position(22)))->toVdt(new Terminal(
            '192.0.2.1',
            'websocket',
            '???',
        ));

        self::assertSame('Connexion/fin', self::text($vdt, row: 22, col: 14, length: 13));
    }

    private static function text(Vdt $vdt, int $row, int $col, int $length): string
    {
        $screen = $vdt->screen();

        return implode('', array_map(
            static fn(int $c) => $screen->cell($row, $c)->bytes ?? ' ',
            range($col, $col + $length - 1),
        ));
    }
}
