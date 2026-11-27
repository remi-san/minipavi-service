<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Line;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the screen shows of text: the G0 and G2 characters, accents, and the rows it gives
 */
final class TextTest extends TestCase
{
    public function testPreviewShowsTextMosaicAndG2Characters(): void
    {
        // Row 1: text, then "e" with an accent (G2), then mosaic cells: with pixels, empty
        // on black, empty on blue. Row 2: cleared with a blue background from column 3.
        $vdt = "\x1F\x41\x41Hi\x19\x42e\x0E\x21\x20\x1B\x54\x20\x1F\x42\x43\x1B\x54\x18";

        $lines = explode("\n", Screen::of(new Vdt($vdt))->preview());

        self::assertCount(26, $lines);
        self::assertSame(' 0|' . str_repeat(' ', times: 40) . '|', $lines[0]);
        // G2 decoded, as line() does
        self::assertSame(' 1|Hié# #' . str_repeat(' ', times: 34) . '|', $lines[1]);
        self::assertSame(' 2|  ' . str_repeat(' ', times: 38) . '|', $lines[2]);
        self::assertSame('', $lines[25]);
    }

    public function testLineGivesTheTextOfARowWithItsAccents(): void
    {
        $screen = Screen::of(Vdt::join(
            Vdt::position(new Position(row: 3)),
            Vdt::text('Dernière page, ça coûte 5£ ½ Œuvre'),
            Vdt::line0('Accès refusé'),
        ));

        $text = 'Dernière page, ça coûte 5£ ½ Œuvre';
        self::assertSame($text . str_repeat(' ', max(0, 40 - mb_strlen($text))), $screen->line(3));
        self::assertStringStartsWith('Accès refusé', ltrim($screen->line(0)));
        self::assertSame(str_repeat(' ', times: 40), $screen->line(4));
        self::assertSame('', $screen->line(99));
    }

    public function testLineShowsMosaicAsInThePreview(): void
    {
        // A mosaic cell with pixels, one empty on black, one empty on blue
        $screen = Screen::of(new Vdt("\x1F\x41\x41\x0E\x21\x20\x1B\x54\x20"));

        self::assertSame('# #', substr($screen->line(1), offset: 0, length: 3));
    }

    public function testTextGivesEveryRow(): void
    {
        $text = Screen::of(Vdt::position(new Position(row: 1))->append(Vdt::text('Hello')))->text();

        $lines = explode("\n", $text);
        self::assertCount(25, $lines);
        self::assertSame(str_pad('Hello', length: 40), $lines[1]);
    }

    public function testDrawsWhatTheContentPositionsAndRepeats(): void
    {
        // Clear screen, position at row 1 column 2, AB, B three more times, red mosaic,
        // clear to the end of the row, back one column, home
        $screen = Screen::of(new Vdt("\x0C\x1F\x41\x42AB\x12\x43\x1B\x41\x0E\x18\x08\x1E"));

        self::assertSame(' ABBBB', rtrim($screen->line(1)));
        self::assertTrue($screen->cell(row: 1, col: 7)?->attributes->isMosaic());
        self::assertSame(Color::Red, $screen->cell(row: 1, col: 40)?->attributes->foreground());
    }

    /**
     * What the Minitel shows (checked in MiniPavi's emulator, #90): an accent (SS2, then
     * grave 0x41, acute 0x42, circumflex 0x43, diaeresis 0x48 or cedilla 0x4B) takes the
     * byte after it, whatever it is, in one cell: the accented letter when the Minitel has
     * it, _ otherwise; any other G2 code it doesn't have is _ too
     *
     * @return iterable<string, array{string, non-empty-string}> content (after positioning at
     *                                                           row 1, column 1), and the
     *                                                           start of row 1
     */
    public static function g2Accents(): iterable
    {
        yield 'acute' => ["\x19\x42e!", 'é!'];
        yield 'grave' => ["\x19\x41e!", 'è!'];
        // A letter the Minitel doesn't have with that accent
        yield 'acute a' => ["\x19\x42a!", '_!'];
        yield 'acute u' => ["\x19\x42u!", '_!'];
        yield 'grave o' => ["\x19\x41o!", '_!'];
        yield 'circumflex I' => ["\x19\x43I!", '_!'];
        yield 'diaeresis u' => ["\x19\x48u!", '_!'];
        yield 'diaeresis a' => ["\x19\x48a!", '_!'];
        yield 'diaeresis o' => ["\x19\x48o!", '_!'];
        // 0x4F, 0x40 and 0x50 aren't accents: a G2 character of their own, then the letter
        yield 'the last code before the letters' => ["\x19\x4Fe!", '_e!'];
        yield 'a G2 code before the accents' => ["\x19\x40e!", '_e!'];
        yield 'a G2 code after the accents' => ["\x19\x50e!", '_e!'];
        yield 'ß' => ["\x19\x7Be!", '_e!'];
        yield '§' => ["\x19\x27e!", '_e!'];
        // A space or a control byte is the accent's byte too: the control byte doesn't act
        // (US, then 0x41 0x44 shown as text)
        yield 'followed by a space' => ["\x19\x42 !", '_!'];
        yield 'followed by a control byte' => ["\x19\x42\x1F\x41\x44e!", '_ADe!'];
        yield 'SS2 followed by a control byte' => ["\x19\x1F\x41\x44e!", '_ADe!'];
        yield 'with its letter at the end of the content' => ["\x19\x42e", 'é '];
        // Cut at the end of the content: nothing drawn yet. The Minitel waits for the rest,
        // which it takes from what it is sent next (e.g. the next page's first byte)
        yield 'an accent at the end of the content' => ["A\x19\x42", 'A '];
        yield 'SS2 at the end of the content' => ["A\x19", 'A '];
    }

    /**
     * @param non-empty-string $shown
     */
    #[DataProvider('g2Accents')]
    public function testAG2AccentGoesWithTheCharacterAfterIt(string $sent, string $shown): void
    {
        self::assertStringStartsWith($shown, Screen::of(new Vdt("\x1F\x41\x41{$sent}"))->line(1));
    }

    /**
     * Every G2 character the Minitel has (MiniPavi's emulator, #90)
     *
     * @return iterable<string, array{string, string}> its bytes after SS2, and its text
     */
    public static function g2Characters(): iterable
    {
        // A list: "\x30" (0) as an array key would be an integer
        $characters = [
            ["\x23", '£'],
            ["\x24", '$'],
            ["\x26", '#'],
            ["\x2C", '←'],
            ["\x2D", '↑'],
            ["\x2E", '→'],
            ["\x2F", '↓'],
            ["\x30", '°'],
            ["\x31", '±'],
            ["\x38", '÷'],
            ["\x3C", '¼'],
            ["\x3D", '½'],
            ["\x3E", '¾'],
            ["\x6A", 'Œ'],
            ["\x7A", 'œ'],
            ["\x41A", 'À'],
            ["\x41a", 'à'],
            ["\x41E", 'È'],
            ["\x41e", 'è'],
            ["\x41u", 'ù'],
            ["\x42E", 'É'],
            ["\x42e", 'é'],
            ["\x43A", 'Â'],
            ["\x43a", 'â'],
            ["\x43E", 'Ê'],
            ["\x43e", 'ê'],
            ["\x43u", 'û'],
            ["\x43i", 'î'],
            ["\x43o", 'ô'],
            ["\x48E", 'Ë'],
            ["\x48e", 'ë'],
            ["\x48i", 'ï'],
            ["\x4BC", 'Ç'],
            ["\x4Bc", 'ç'],
        ];
        foreach ($characters as [$bytes, $text]) {
            yield $text => [$bytes, $text];
        }
    }

    #[DataProvider('g2Characters')]
    public function testShowsTheG2CharactersOfTheMinitel(string $bytes, string $text): void
    {
        self::assertSame("{$text}!", rtrim(Screen::of(new Vdt("\x1F\x41\x41\x19{$bytes}!"))->line(1)));
    }

    /**
     * SYN (0x16) starts a G2 character as SS2 (0x19) does (MiniPavi's emulator, #139)
     *
     * @return iterable<string, array{string, string}> content (after positioning at row 1,
     *                                                 column 1), and row 1 shown
     */
    public static function g2AfterSyn(): iterable
    {
        yield 'an accent' => ["\x16\x42e!", 'é!'];
        yield 'another accent' => ["\x16\x43u!", 'û!'];
        yield 'a character of its own' => ["\x16\x23!", '£!'];
        yield 'a code the Minitel does not have' => ["\x16Z!", '_!'];
        // The second SYN is the G2 code, which the Minitel doesn't have: the accent's code
        // and its letter are then text
        yield 'followed by SYN' => ["\x16\x16\x42e!", '_Be!'];
        yield 'followed by SS2' => ["\x16\x19\x42e!", '_Be!'];
        yield 'SS2 followed by SYN' => ["\x19\x16\x42e!", '_Be!'];
        // Cut at the end of the content: nothing drawn yet
        yield 'at the end of the content' => ["A\x16", 'A'];
    }

    #[DataProvider('g2AfterSyn')]
    public function testSynStartsAG2CharacterAsSs2Does(string $sent, string $shown): void
    {
        self::assertSame($shown, rtrim(Screen::of(new Vdt("\x1F\x41\x41{$sent}"))->line(1)));
    }

    /**
     * The G0 characters the Minitel draws otherwise than in ASCII (MiniPavi's emulator, its
     * sprite sheet font/ef9345-g0.png, #143), and the closest character to what it draws
     *
     * @return iterable<string, array{string, string}> the G0 character, and its text
     */
    public static function g0Characters(): iterable
    {
        yield '0x5E, an arrow up' => ["\x5E", '↑'];
        yield '0x60, a line across the middle' => ["\x60", '─'];
        yield '0x7B, a line down the left edge' => ["\x7B", '▏'];
        yield '0x7D, a line down the right edge' => ["\x7D", '▕'];
        yield '0x7E, a line across the top' => ["\x7E", '▔'];
        yield '0x7F, a full block' => ["\x7F", '█'];
        // As in ASCII
        yield '0x5F, a line across the bottom' => ["\x5F", '_'];
        yield '0x7C, a line down the middle' => ["\x7C", '|'];
    }

    #[DataProvider('g0Characters')]
    public function testShowsTheG0CharactersAsTheMinitelDrawsThem(string $char, string $text): void
    {
        self::assertSame("{$text}!", rtrim(Screen::of(new Vdt("\x1F\x41\x41{$char}!"))->line(1)));
    }

    public function testShowsTheLinesOfAPageAsTheMinitelDrawsThem(): void
    {
        $page = Vdt::join(
            (new Line(new Span(new Position(2, 1), 3), Line::TOP, Color::White))->toVdt(new Terminal()),
            (new Line(new Span(new Position(3, 1), 3), Line::MIDDLE, Color::White))->toVdt(new Terminal()),
            (new Line(new Span(new Position(4, 1), 3), Line::BOTTOM, Color::White))->toVdt(new Terminal()),
        );

        $screen = Screen::of($page);

        self::assertSame(['___', '───', '▔▔▔'], [
            rtrim($screen->line(2)),
            rtrim($screen->line(3)),
            rtrim($screen->line(4)),
        ]);
    }

    /**
     * MiniPavi's emulator prints the error character (SUB) as 0x7F, as the preview draws it
     * (Client\Html\HtmlRenderer): a full block in text, all six pixels in mosaic
     */
    public function testErrorCharacterIsAFullBlockInTheText(): void
    {
        $screen = Screen::of(new Vdt("\x1F\x41\x41A" . Videotex::SUB . 'B'));

        self::assertSame('A█B', rtrim($screen->line(1)));
    }
}
