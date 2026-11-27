<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Tests\Support\VdtFixtures;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Videotex content: text, repetitions, and joining contents
 */
final class VdtTest extends TestCase
{
    public function testTextShowsItsAccents(): void
    {
        self::assertStringStartsWith('Déjà vu ', Screen::of(Vdt::text('Déjà vu'))->line(1));
        // Control characters can't slip in as text: a clear screen would erase the "a"
        self::assertStringStartsWith('a b ', Screen::of(Vdt::text("a\x0Cb"))->line(1));
    }

    /**
     * Uppercase letters keep their case (#58): Ç is the Minitel's Ç, not ç. The Minitel has
     * no Î, which it would show as _ (as MiniPavi's emulator does): Î is shown as I
     */
    public function testTextShowsUppercaseLettersAsTheMinitelHasThem(): void
    {
        self::assertStringStartsWith('ÇA ç ILE île ', Screen::of(Vdt::text('ÇA ç ÎLE île'))->line(1));
        self::assertStringStartsWith('ÇA ç ILE île ', Screen::of(Vdt::line0('ÇA ç ÎLE île'))->line(0));
    }

    /**
     * The Minitel has no §, which MiniPaviCli::send() sends as ? (#137): the screen shows the ?
     * the Minitel shows, not §
     */
    public function testTextShowsACharacterTheMinitelDoesntHaveAsTheMinitelDoes(): void
    {
        self::assertStringStartsWith('? 1 ', Screen::of(Vdt::text('§ 1'))->line(1));
    }

    public function testJoinsContents(): void
    {
        $a = new Vdt("\x0C");

        self::assertSame("\x0Cab", Vdt::join($a, Vdt::text('a'), Vdt::text('b'))->toString());
        self::assertSame("\x0Cab", $a->append(Vdt::text('a'), Vdt::text('b'))->toString());
        // Immutable
        self::assertSame("\x0C", $a->toString());
    }

    public function testEmptyContentIsNothing(): void
    {
        self::assertTrue((new Vdt())->isEmpty());
        self::assertTrue(Vdt::join()->isEmpty());
        self::assertFalse(Vdt::text(' ')->isEmpty());
    }

    public function testGivesBackItsBytesAsTheyAre(): void
    {
        $vdt = (string) file_get_contents(VdtFixtures::FOLDER . '/background.vdt');

        self::assertSame($vdt, (new Vdt($vdt))->toString());
        // Equal by its bytes, drawn or not
        $drawn = new Vdt("\x1F\x41\x41Hi");
        Screen::of($drawn);
        self::assertEquals(new Vdt("\x1F\x41\x41Hi"), $drawn);
    }

    /**
     * A REP counts 63 more at most (0x7F): beyond, the count is no longer a 7-bit byte, which
     * the gateway's client (MiniPaviCli::send()) turns into "?", so the character would be shown
     * once. More repetitions follow.
     */
    public function testRepeatsACharacterAsManyTimesAsAsked(): void
    {
        foreach ([1, 40, 64, 65, 100, 128, 129] as $count) {
            $vdt = Vdt::repeat('-', $count);

            self::assertSame(0, preg_match('/[\x80-\xFF]/', $vdt->toString()), "{$count} times: 7-bit");
            self::assertSame(
                str_repeat('-', $count),
                str_replace(["\n", ' '], replace: '', subject: Screen::of($vdt)->text()),
                "{$count} times",
            );
        }
        // The character, 63 more, then 1 more
        self::assertSame("-\x12\x7F\x12\x41", Vdt::repeat('-', count: 65)->toString());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function countsBelowOne(): iterable
    {
        yield 'none' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('countsBelowOne')]
    public function testRefusesToRepeatACharacterLessThanOnce(int $count): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("A character is repeated once at least, got {$count}");

        Vdt::repeat('-', $count);
    }

    public function testDoubleWidthTakesTwoColumnsInTextButNotInMosaic(): void
    {
        $text = Screen::of(new Vdt("\x1F\x41\x41\x1B\x4EAB"));
        $mosaic = Screen::of(new Vdt("\x1F\x41\x41\x0E\x1B\x4E\x21\x22"));

        self::assertSame(['A', 'A', 'B', 'B'], array_map(static fn(int $col) => $text->cell(1, $col)?->bytes, [
            1,
            2,
            3,
            4,
        ]));
        self::assertSame(
            ["\x21", "\x22", null],
            array_map(static fn(int $col) => $mosaic->cell(1, $col)?->bytes, [1, 2, 3]),
        );
    }
}
