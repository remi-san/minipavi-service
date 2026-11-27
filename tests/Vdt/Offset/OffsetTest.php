<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Offset;

use MiniPaviService\Tests\Support\MovedScreens;
use MiniPaviService\Tests\Support\VdtFixtures;
use MiniPaviService\Tests\Vdt\Screen\CursorTest;
use MiniPaviService\Tests\Vdt\Screen\SequencesTest;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Offset moves the screen a content draws: what goes off it, the status row, and the golden
 * files
 */
final class OffsetTest extends TestCase
{
    // PHPUnit's assertions take no named arguments: their messages are constants
    private const GOLDEN_MISSING = 'Run the tests with UPDATE_GOLDEN=1 to write it';

    public function testOffsetDropsWhatGoesOffTheScreenInsteadOfWrapping(): void
    {
        $abcde = new Vdt("\x1F\x41\x41ABCDE");

        self::assertSame('CDE', rtrim(Screen::of((new Offset(x: -2, y: 0))->apply($abcde))->line(1)));
        self::assertSame(
            str_repeat(' ', times: 37) . 'ABC',
            Screen::of((new Offset(x: 37, y: 0))->apply($abcde))->line(1),
        );
        self::assertSame('', trim(Screen::of((new Offset(x: 37, y: 0))->apply($abcde))->line(2)));
        self::assertSame('', trim(Screen::of((new Offset(x: 0, y: -1))->apply(new Vdt("\x1F\x41\x41ABC")))->text()));
    }

    #[DataProviderExternal(SequencesTest::class, 'sequencesDrawingNothing')]
    public function testOffsetKeepsTheSequencesThatDrawNothing(string $sequence): void
    {
        $moved = (new Offset(x: 1, y: 1))
            ->apply(new Vdt("\x1F\x41\x41{$sequence}A"))
            ->toString();

        // Sent whole, and only the A after it shown, moved
        self::assertStringContainsString($sequence, $moved);
        $screen = Screen::of(new Vdt($moved));
        self::assertSame('A', trim($screen->text()));
        self::assertStringStartsWith(' A ', $screen->line(2));
    }

    #[DataProviderExternal(CursorTest::class, 'positionsOutsideTheScreen')]
    public function testOffsetDropsAPositioningTheMinitelIgnores(string $position): void
    {
        // Row 5: red A, B, the positioning, then C, drawn after B, still red
        self::assertSame(
            "\x1F\x46\x43\x1B\x41ABC",
            (new Offset(x: 2, y: 1))
                ->apply(new Vdt("\x1F\x45\x41\x1B\x41AB{$position}C"))
                ->toString(),
        );
    }

    public function testOffsetDoesNotMoveTheStatusRow(): void
    {
        $moved = Screen::of((new Offset(x: 1, y: 1))->apply(new Vdt("\x1F\x40\x41status\x1F\x41\x41X")));

        self::assertSame('status', rtrim($moved->line(0)));
        self::assertSame(' X', rtrim($moved->line(2)));
    }

    public function testOffsetMovesWhatALineFeedFromTheStatusRowDraws(): void
    {
        // X at row 10, column 7, L on row 0, then LF: M back where the cursor was before row 0,
        // in red, as set before it
        $moved = (new Offset(x: 2, y: 3))->apply(new Vdt("\x1F\x4A\x47\x1B\x41X\x1F\x40\x45L\x0AM"));

        self::assertSame("\x1F\x4D\x49\x1B\x41X\x1F\x40\x45L\x1F\x4D\x4A\x1B\x41M", $moved->toString());
    }

    /**
     * background.vdt: a page made with a Videotex editor; features.vdt: made
     * by hand to have every kind of token (status row, CAN with a background color, double
     * width, mosaic and repetitions, G2, wrapping at column 40, BS/LF/CR/HT/VT, US in digits)
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function offsets(): iterable
    {
        foreach (['background', 'features'] as $file) {
            yield "{$file}, none" => [$file, 0, 0];
            yield "{$file}, right and up" => [$file, 8, -2];
            yield "{$file}, left and down" => [$file, -5, 3];
            yield "{$file}, mostly off the screen" => [$file, 30, 20];
            yield "{$file}, one column left" => [$file, -1, 0];
        }
    }

    /**
     * The screen drawn by the moved content is the original one, moved: every cell is found
     * x columns right and y rows down, with the same character and attributes, except on
     * row 0 (the status row), which stays as it is; nothing else is drawn (see
     * MovedScreens::assertScreenMoved() for what shows of a double-width character partly off
     * the screen)
     */
    #[DataProvider('offsets')]
    public function testOffsetDrawsTheSameScreenMoved(string $file, int $x, int $y): void
    {
        MovedScreens::assertScreenMoved(VdtFixtures::content($file), $x, $y);
    }

    /**
     * Byte-for-byte output, to notice any change in how the content is written
     */
    #[DataProvider('offsets')]
    public function testOffsetMatchesTheGoldenFile(string $file, int $x, int $y): void
    {
        $golden = VdtFixtures::FOLDER . "/{$file}.offset_{$x}_{$y}.vdt";
        $vdt = (new Offset($x, $y))
            ->apply(VdtFixtures::content($file))
            ->toString();

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents($golden, $vdt);
        }
        self::assertFileExists($golden, self::GOLDEN_MISSING);
        self::assertSame((string) file_get_contents($golden), $vdt);
    }
}
