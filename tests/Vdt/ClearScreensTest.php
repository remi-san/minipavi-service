<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\ClearScreens;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClearScreensTest extends TestCase
{
    public function testFromTheLastKeepsWhatShows(): void
    {
        $vdt = new Vdt("\x1F\x41\x41A\x0C\x1F\x41\x41B\x0C\x1F\x42\x41C");

        self::assertSame(
            "\x0C\x1F\x42\x41C",
            (new ClearScreens())
                ->fromLast($vdt)
                ->toString(),
        );
        // Without a clear screen: all of it
        self::assertSame(
            "\x1F\x41\x41A",
            (new ClearScreens())
                ->fromLast(new Vdt("\x1F\x41\x41A"))
                ->toString(),
        );
    }

    public function testKeepsTheOtherSequencesAsTheyAre(): void
    {
        // A position in digits (row 7), 1, 2, A, B, a repetition of B, SEP and SS3 each
        // with their byte, then a clear screen
        $kept = "\x1F0712AB\x12\x43\x13\x49\x1D\x49";

        self::assertSame(
            "{$kept}\x1E\x1F\x41\x41C",
            (new ClearScreens())
                ->removed(new Vdt("{$kept}\x0C\x1F\x41\x41C"))
                ->toString(),
        );
        self::assertSame(
            $kept,
            (new ClearScreens())
                ->fromLast(new Vdt($kept))
                ->toString(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sequences(): iterable
    {
        yield 'cursor left (BS)' => ["\x08"];
        yield 'cursor right (HT)' => ["\x09"];
        yield 'cursor down (LF)' => ["\x0A"];
        yield 'cursor up (VT)' => ["\x0B"];
        yield 'carriage return (CR)' => ["\x0D"];
        yield 'home (RS)' => ["\x1E"];
        yield 'clear line (CAN)' => ["\x18"];
        yield 'mosaic (SO)' => ["\x0E"];
        yield 'text (SI)' => ["\x0F"];
        yield 'an attribute' => ["\x1BA"];
        yield 'another escape sequence' => ["\x1B\x3A\x69\x43"];
        yield 'a G2 character' => ["\x19\x42e"];
        yield 'an error character (SUB)' => ["\x1A"];
        yield 'cursor off' => ["\x14"];
    }

    #[DataProvider('sequences')]
    public function testKeepsEachSequenceAsItIs(string $sequence): void
    {
        self::assertSame(
            "A{$sequence}B",
            (new ClearScreens())
                ->fromLast(new Vdt("A{$sequence}B"))
                ->toString(),
        );
        self::assertSame(
            "\x0C{$sequence}",
            (new ClearScreens())
                ->fromLast(new Vdt("A\x0C{$sequence}"))
                ->toString(),
        );
        self::assertSame(
            "{$sequence}\x1E",
            (new ClearScreens())
                ->removed(new Vdt("{$sequence}\x0C"))
                ->toString(),
        );
    }

    public function testRemovedDrawsOverWhatIsShown(): void
    {
        $screen = Screen::of((new Vdt("\x1F\x43\x41shown"))->append((new ClearScreens())->removed(new Vdt("\x0CAB"))));

        self::assertSame('shown', rtrim($screen->line(3)));
        // From the top left corner, as after a clear screen
        self::assertSame('AB', rtrim($screen->line(1)));
    }
}
