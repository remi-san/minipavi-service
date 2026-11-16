<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPaviService\Vdt\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VdtTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/vdt';

    public function testOffsetMovesTextAndKeepsItsAttributes(): void
    {
        // HELLO on row 1, then four red A (the last three as a repetition) on row 2
        $moved = Vdt::fromString("\x1F\x41\x41HELLO\x1F\x42\x41\x1B\x41AA\x12\x42")->offset(2, 3);

        // Positioning resets the attributes, so red is sent again after it
        self::assertSame("\x1F\x44\x43HELLO\x1F\x45\x43\x1B\x41A\x12\x43", $moved->toString());
        self::assertSame("\x41", $moved->screen()->cell(5, 6)?->attributes['fg']);
    }

    public function testOffsetDropsWhatGoesOffTheScreenInsteadOfWrapping(): void
    {
        self::assertSame("\x1F\x41\x41CDE", Vdt::fromString("\x1F\x41\x41ABCDE")->offset(-2, 0)->toString());
        self::assertSame("\x1F\x41\x66ABC", Vdt::fromString("\x1F\x41\x41ABCDE")->offset(37, 0)->toString());
        self::assertSame('', Vdt::fromString("\x1F\x41\x41ABC")->offset(0, -1)->toString());
    }

    public function testOffsetDoesNotMoveTheStatusRow(): void
    {
        $moved = Vdt::fromString("\x1F\x40\x41status\x1F\x41\x41X")->offset(1, 1);

        self::assertSame("\x1F\x40\x41status\x1F\x42\x42X", $moved->toString());
    }

    public function testDoubleWidthTakesTwoColumnsInTextButNotInMosaic(): void
    {
        $text = Vdt::fromString("\x1F\x41\x41\x1B\x4EAB")->screen();
        $mosaic = Vdt::fromString("\x1F\x41\x41\x0E\x1B\x4E\x21\x22")->screen();

        self::assertSame(['A', 'A', 'B', 'B'], array_map(fn(int $col) => $text->cell(1, $col)?->bytes, [1, 2, 3, 4]));
        self::assertSame(["\x21", "\x22", null], array_map(fn(int $col) => $mosaic->cell(1, $col)?->bytes, [1, 2, 3]));
    }

    public function testWithoutClearTurnsClearScreensIntoHome(): void
    {
        self::assertSame("\x1EAB", Vdt::fromString("\x0CAB")->withoutClear()->toString());
    }

    /**
     * background.vdt: a page made with a Videotex editor (Octoblue's); features.vdt: made
     * by hand to have every kind of token (status row, CAN with a background color, double
     * width, mosaic and repetitions, G2, wrapping at column 40, BS/LF/CR/HT/VT, US in digits)
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function offsets(): iterable
    {
        foreach (['background', 'features'] as $file) {
            yield "$file, none" => [$file, 0, 0];
            yield "$file, right and up" => [$file, 8, -2];
            yield "$file, left and down" => [$file, -5, 3];
            yield "$file, mostly off the screen" => [$file, 30, 20];
            yield "$file, one column left" => [$file, -1, 0];
        }
    }

    /**
     * The screen drawn by the moved content is the original one, moved: every cell is found
     * x columns right and y rows down, with the same character and attributes, except on
     * row 0 (the status row), which stays as it is; nothing else is drawn. A double-width
     * character that would be partly off the screen is not drawn at all.
     */
    #[DataProvider('offsets')]
    public function testOffsetDrawsTheSameScreenMoved(string $file, int $x, int $y): void
    {
        $original = Vdt::fromFile(self::FIXTURES . "/$file.vdt");
        $before = $original->screen();
        $after = $original->offset($x, $y)->screen();
        $glyphs = self::glyphColumns($original);

        for ($row = 0; $row <= Screen::ROWS; $row++) {
            for ($col = 1; $col <= Screen::COLS; $col++) {
                if ($row === 0) {
                    $expected = $before->cell(0, $col);
                } else {
                    [$fromRow, $fromCol] = [$row - $y, $col - $x];
                    [$start, $width] = $glyphs[$fromRow][$fromCol] ?? [$fromCol, 1];
                    $onScreen = $fromRow >= 1 && $fromRow <= Screen::ROWS
                        && $start + $x >= 1 && $start + $x + $width - 1 <= Screen::COLS;
                    $expected = $onScreen ? $before->cell($fromRow, $fromCol) : null;
                }
                self::assertEquals($expected, $after->cell($row, $col), "row $row, column $col");
            }
        }
    }

    /**
     * For each cell drawn by a character: the first column of that character, and its width
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private static function glyphColumns(Vdt $vdt): array
    {
        $columns = [];
        $vdt->play(function (int $row, int $col, string $bytes, int $width) use (&$columns): void {
            for ($i = 0; $i < $width; $i++) {
                $columns[$row][$col + $i] = [$col, $width];
            }
        });

        return $columns;
    }

    /**
     * Byte-for-byte output, to notice any change in how the content is written
     */
    #[DataProvider('offsets')]
    public function testOffsetMatchesTheGoldenFile(string $file, int $x, int $y): void
    {
        $golden = self::FIXTURES . "/$file.offset_{$x}_{$y}.vdt";
        $vdt = Vdt::fromFile(self::FIXTURES . "/$file.vdt")->offset($x, $y)->toString();

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents($golden, $vdt);
        }
        self::assertFileExists($golden, 'Run the tests with UPDATE_GOLDEN=1 to write it');
        self::assertSame((string) file_get_contents($golden), $vdt);
    }

    public function testFromFileThrowsWhenTheFileIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Videotex file not found');

        @Vdt::fromFile(self::FIXTURES . '/missing.vdt');
    }
}
