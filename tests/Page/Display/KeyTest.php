<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\EndKey;
use MiniPaviService\Page\Display\Key;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class KeyTest extends TestCase
{
    public function testKeyIsABadgeInReverseVideo(): void
    {
        $screen = Screen::of((new Key('Envoi', new Position(20, 30), Color::Red, bg: null))->toVdt(new Terminal()));

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

    public function testKeyEndingTheRowWritesNothingOnTheNextOne(): void
    {
        // The label in the last columns: no room for the reverse video space after it, nor
        // for the space ending the badge
        $screen = Screen::of((new Key('Envoi', new Position(20, 34), Color::Green, bg: null))->toVdt(new Terminal()));

        self::assertSame('Envoi', self::cells($screen, row: 20, col: 36, length: 5));
        self::assertNull($screen->cell(row: 21, col: 1));
        self::assertNull($screen->cell(row: 21, col: 2));
    }

    public function testKeyWithRoomForItsReverseVideoSpaceOnlyWritesNothingOnTheNextRow(): void
    {
        $screen = Screen::of((new Key('Envoi', new Position(20, 33), Color::Green, bg: null))->toVdt(new Terminal()));

        $cell = $screen->cell(row: 20, col: 40);
        if ($cell === null) {
            self::fail('Nothing drawn at row 20, column 40');
        }
        self::assertSame(' ', $cell->bytes);
        self::assertTrue($cell->attributes->isInverse());
        self::assertNull($screen->cell(row: 21, col: 1));
    }

    public function testKeyCountsACellPerAccentedCharacter(): void
    {
        // "Validé", 6 characters in columns 34 to 39, leaves room for the space after it
        $screen = Screen::of((new Key('Validé', new Position(20, 32), Color::Green, bg: null))->toVdt(new Terminal()));

        $cell = $screen->cell(row: 20, col: 40);
        if ($cell === null) {
            self::fail('Nothing drawn at row 20, column 40');
        }
        self::assertTrue($cell->attributes->isInverse());
        self::assertNull($screen->cell(row: 21, col: 1));
    }

    public function testKeyFittingTheRowEndsWithASpaceInItsLastColumn(): void
    {
        $screen = Screen::of((new Key('Envoi', new Position(20, 32), Color::Green, bg: null))->toVdt(new Terminal()));

        $cell = $screen->cell(row: 20, col: 40);
        if ($cell === null) {
            self::fail('Nothing drawn at row 20, column 40');
        }
        self::assertSame(' ', $cell->bytes);
        self::assertFalse($cell->attributes->isInverse());
        self::assertNull($screen->cell(row: 21, col: 1));
    }

    public function testEndKeyAsWideAsTheRowWritesNothingOnTheNextOne(): void
    {
        // 37 characters: the badge, 41 cells, starts in column 1, the label ends in column 39
        $label = str_repeat('a', times: 37);
        $screen = Screen::of((new EndKey($label, '[Maj] +', new Position(22), Color::Green))->toVdt(new Terminal(
            'CALLFROM 0102030405',
            'other',
            '???',
        )));

        self::assertSame($label, self::cells($screen, row: 22, col: 3, length: 37));
        self::assertNull($screen->cell(row: 23, col: 1));
    }

    public function testEndKeyOfAnAdapterAsWideAsTheRowWritesNothingOnTheNextOne(): void
    {
        // "Shift" in columns 1 to 5, the badge from column 6, its label ending in column 39
        $label = str_repeat('a', times: 32);
        $screen = Screen::of((new EndKey($label, 'Shift', position: null, color: Color::Green))->toVdt(new Terminal(
            '192.0.2.1',
            'websocket',
            'Cv5',
        )));

        self::assertSame($label, self::cells($screen, row: 23, col: 8, length: 32));
        self::assertNull($screen->cell(row: 24, col: 1));
    }

    public function testEndKeyIsOnTheLastRowForAMinitel(): void
    {
        $vdt = (new EndKey('Connexion/fin', '[Maj] +', position: null, color: Color::Green))->toVdt(new Terminal(
            'CALLFROM 0102030405',
            'other',
            '???',
        ));

        self::assertStringNotContainsString('[Maj] +', $vdt->toString());
        self::assertSame('Connexion/fin', self::text($vdt, row: 24, col: 14, length: 13));
    }

    public function testEndKeyTellsAdaptersToPressShift(): void
    {
        // ESP32 / Minimit adapters: a websocket with a known Minitel
        $vdt = (new EndKey('Connexion/fin', '[Maj] +', position: null, color: Color::Green))->toVdt(new Terminal(
            '192.0.2.1',
            'websocket',
            'Cv5',
        ));

        self::assertSame('[Maj] +', self::text($vdt, row: 23, col: 9, length: 7));
        self::assertSame('Connexion/fin', self::text($vdt, row: 23, col: 18, length: 13));
    }

    public function testEndKeyIsCenteredWhateverItsLabels(): void
    {
        $minitel = (new EndKey('Fin', 'Shift', position: null, color: Color::Green))->toVdt(new Terminal(
            'CALLFROM 0102030405',
            'other',
            '???',
        ));
        $adapter = (new EndKey('Fin', 'Shift', position: null, color: Color::Green))->toVdt(new Terminal(
            '192.0.2.1',
            'websocket',
            'Cv5',
        ));

        // The badge " Fin " has a cell on each side: 7 cells, from column 17
        self::assertSame('Fin', self::text($minitel, row: 24, col: 19, length: 3));
        // "Shift" and the badge: 12 cells, from column 15
        self::assertSame('Shift', self::text($adapter, row: 23, col: 15, length: 5));
        self::assertSame('Fin', self::text($adapter, row: 23, col: 22, length: 3));
    }

    public function testEndKeyOfAnEmulatorIsOnTheLastRow(): void
    {
        // An emulator is a websocket without a known Minitel
        $vdt = (new EndKey('Connexion/fin', '[Maj] +', new Position(22), Color::Green))->toVdt(new Terminal(
            '192.0.2.1',
            'websocket',
            '???',
        ));

        self::assertSame('Connexion/fin', self::text($vdt, row: 22, col: 14, length: 13));
    }

    private static function text(Vdt $vdt, int $row, int $col, int $length): string
    {
        return self::cells(Screen::of($vdt), $row, $col, $length);
    }

    private static function cells(Screen $screen, int $row, int $col, int $length): string
    {
        return implode('', array_map(
            static fn(int $c) => $screen->cell($row, $c)->bytes ?? ' ',
            range($col, $col + $length - 1),
        ));
    }
}
