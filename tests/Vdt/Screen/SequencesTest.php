<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sequences the screen draws nothing for
 */
final class SequencesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}> a sequence none of whose bytes is a character
     */
    public static function sequencesDrawingNothing(): iterable
    {
        yield 'PRO1 (ESC 9 x)' => ["\x1B\x39\x70"];
        yield 'PRO2 (ESC : x y)' => ["\x1B\x3A\x69\x43"];
        yield 'PRO3 (ESC ; x y z)' => ["\x1B\x3B\x60\x58\x52"];
        yield 'whole screen or row attributes (ESC # x y)' => ["\x1B\x23\x20\x54"];
        // Charset designations, G0 to G3 (ESC ( to ESC +), with a space before a DRCS's
        yield 'G0 designation (ESC ( x)' => ["\x1B\x28\x40"];
        yield 'G3 designation (ESC + x)' => ["\x1B\x2B\x40"];
        yield 'DRCS designation as G0 (ESC ( SP x)' => ["\x1B\x28\x20\x42"];
        yield 'DRCS designation as G3 (ESC + SP x)' => ["\x1B\x2B\x20\x42"];
        // Escape sequences of two bytes that don't set an attribute, the first two next to
        // the designations
        yield "ESC '" => ["\x1B\x27"];
        yield 'ESC ,' => ["\x1B\x2C"];
        yield 'cursor position request (ESC a)' => ["\x1B\x61"];
        // CSI, its parameters (bytes 0 to ?), then its final byte
        yield 'erase to the end of the row (CSI K)' => ["\x1B\x5B\x4B"];
        yield 'insert mode off (CSI 4 l)' => ["\x1B\x5B\x34\x6C"];
        yield 'cursor at row 1, column 1 (CSI 01 ; 01 H)' => ["\x1B\x5B\x30\x31\x3B\x30\x31\x48"];
        yield 'a private parameter (CSI ? 4 l)' => ["\x1B\x5B\x3F\x34\x6C"];
        // SEP and SS3, each followed by one byte
        yield 'SEP x' => ["\x13\x49"];
        yield 'SS3 x' => ["\x1D\x49"];
    }

    #[DataProvider('sequencesDrawingNothing')]
    public function testSequencesDrawNothing(string $sequence): void
    {
        // Read whole: none of its bytes drawn, nor the A after it taken as one of them
        self::assertStringStartsWith('A ', Screen::of(new Vdt("\x1F\x41\x41{$sequence}A"))->line(1));
    }

    public function testACsiCutAtTheEndOfTheContentDrawsNothing(): void
    {
        // A, then CSI and a parameter, without its final byte
        self::assertSame('A', trim(Screen::of(new Vdt("\x1F\x41\x41A\x1B\x5B\x34"))->text()));
    }
}
