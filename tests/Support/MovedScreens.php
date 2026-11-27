<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Vdt\Offset;
use MiniPaviService\Vdt\Screen\Attribute\Masking;
use MiniPaviService\Vdt\Screen\Attribute\Underlining;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\Part;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Assert;

/**
 * Assertions that a screen shows another one, moved, as the Minitel shows what Offset
 * writes
 */
final class MovedScreens
{
    /**
     * The screen drawn by the original content, moved by offset(x, y), is the original one, moved:
     * every cell is found x columns right and y rows down, with the same character and attributes,
     * except on row 0 (the status row), which stays as it is; nothing else is drawn. A double-width
     * character moved partly off the screen on the right shows its left half in the last column, as
     * the Minitel shows one written there (MiniPavi's emulator, decoder.js, printG0Char()); moved
     * partly off on the left, its right half shows as a space one column wide (narrowed()) does: a
     * space's right half as it is, another character's without its glyph (its background, or its
     * colour when inverted), as the Minitel can't show a right half alone. The right half the
     * Minitel doesn't show of a character other than a space in the last column, moved left, isn't
     * compared: it is written whole. On a row moved left, a zone whose delimiter goes off the
     * screen can only start again at a space (Writer): on a row whose text space went off the
     * screen, the cells before the first one left show what they do without it
     * (withoutZoneFromOffTheScreen()), and that space as its delimiter does: its background,
     * neither underlined nor masked (asADelimiter()); the right half of a wide character in
     * the column moved to column 1, written as a space, is that space (#170).
     * Each cell is compared as it shows (asShown()): a tall character's top half without its
     * underline, which the Minitel draws in the bottom half only, and each half of a tall
     * space as the space at normal height it shows as
     */
    public static function assertScreenMoved(Vdt $original, int $x, int $y): void
    {
        self::assertShowsMoved(Screen::of($original), Screen::of((new Offset($x, $y))->apply($original)), $x, $y);
    }

    /**
     * The after screen shows the before one, moved by x columns right and y rows down, as
     * assertScreenMoved() says
     */
    public static function assertShowsMoved(Screen $before, Screen $after, int $x, int $y): void
    {
        $before = self::withTopHalvesAsSpaces($before, ScreenSize::ROWS - $y);
        $glyphs = self::glyphColumns($before);

        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                if ($row === 0) {
                    // The status line isn't moved
                    Assert::assertEquals($before->cell(0, $col), $after->cell(0, $col), "row 0, column {$col}");
                    continue;
                }
                [$fromRow, $fromCol] = [$row - $y, $col - $x];
                [$start, $width] = $glyphs[$fromRow][$fromCol] ?? [$fromCol, 1];
                $onScreen = $fromRow >= 1 && $fromRow <= ScreenSize::ROWS && ($start + $x) >= 1;
                $source = $before->cell($fromRow, $fromCol);
                $expected = $onScreen ? $source : null;
                $actual = $after->cell($row, $col);
                if ($x < 0 && $fromCol === ScreenSize::COLS && ($start + $width - 1) > ScreenSize::COLS) {
                    // The left half of a double-width character in the last column, moved left
                    if ($source?->bytes === ' ') {
                        [$expected, $actual] = [self::narrowed($source), self::narrowed($actual)];
                    }
                }
                if ($x < 0 && $fromCol === (ScreenSize::COLS + 1)) {
                    $cut = $before->cell($fromRow, ScreenSize::COLS);
                    if (
                        $cut !== null
                        && $cut->bytes !== ' '
                        && $cut->attributes->width() === 2
                        && !$cut->part->isRight()
                    ) {
                        // The right half the Minitel doesn't show of a character in the last column
                        continue;
                    }
                }
                if ($fromRow >= 1 && $fromRow <= ScreenSize::ROWS && ($start + $x) < 1) {
                    // The right half of a character, which the Minitel can't show alone: as a
                    // space one column wide, with its attributes
                    $space = $source === null ? null : new Cell(' ', $source->attributes, $source->part);
                    [$expected, $actual] = [self::narrowed($space), self::narrowed($actual)];
                }
                if (
                    $x < 0
                    && self::losesADelimiterOffTheScreen($before, $fromRow, firstCol: 1 - $x)
                    && self::isUpToTheRestart($before, $fromRow, $start, firstCol: 1 - $x)
                ) {
                    $shown = self::isTheRestart($before, $fromRow, $fromCol, firstCol: 1 - $x)
                        ? self::asADelimiter(...)
                        : self::withoutZoneFromOffTheScreen(...);
                    [$expected, $actual] = [$shown($expected), $shown($actual)];
                }
                Assert::assertEquals(self::asShown($expected), self::asShown($actual), "row {$row}, column {$col}");
            }
        }
    }

    /**
     * The screen, the top halves of the tall cells on row, whose bottom row is moved off the
     * screen, below the last row, each as a space at normal height with its attributes: its
     * background (or its colour, inverted) and its zone's underline, without its glyph. The
     * Minitel can't show a top half alone on the last row, as it draws a tall character from
     * its bottom row: Offset writes such a space instead, in which a zone can start again
     * (#173)
     */
    private static function withTopHalvesAsSpaces(Screen $screen, int $row): Screen
    {
        $cells = $screen->cells;
        foreach ($cells[$row] ?? [] as $col => $cell) {
            if ($cell === null || $cell->attributes->height() === 1 || $cell->part->isBottom()) {
                continue;
            }
            $cells[$row][$col] = new Cell(' ', $cell->attributes->flattened(), $cell->part);
        }

        return new Screen($cells);
    }

    /**
     * The cell as it shows. The top half of a tall (text) cell shows no underline: the
     * Minitel draws it at the bottom of the character, in its bottom half only (MiniPavi's
     * emulator, font-sprite.js, writeChar()), so a tall delimiter's top half shows as an
     * underlined top half does (#168). Each half of a tall text space shows as a space at
     * normal height does, its background, and the bottom half its underline at the bottom of
     * the cell. Offset writes such a space where a zone starts again over a tall space's
     * bottom half, leaving its top half on the row above, or over its top half, leaving its
     * bottom half (#153)
     */
    private static function asShown(?Cell $cell): ?Cell
    {
        if ($cell === null || $cell->attributes->height() === 1) {
            return $cell;
        }
        $attributes = $cell->part->isBottom()
            ? $cell->attributes
            : $cell->attributes->withUnderlining(Underlining::NoUnderline);
        if ($cell->bytes !== ' ') {
            return new Cell($cell->bytes, $attributes, $cell->part);
        }

        return new Cell($cell->bytes, $attributes->flattened(), new Part($cell->part->isRight(), bottom: false));
    }

    /**
     * The cell as a space one column wide shows: the right half of a double-width space shows
     * as a space at normal width does, its background, and its underline across the cell
     * (MiniPavi's emulator, font-sprite.js, writeChar()). Offset writes such a space where only
     * the right half of a double-width one stays on the screen
     */
    private static function narrowed(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }

        return new Cell(
            $cell->bytes,
            $cell->attributes->narrowed(),
            new Part(right: false, bottom: $cell->part->isBottom()),
        );
    }

    /**
     * Whether the row, moved left so that firstCol is its first column, has a text space (a
     * delimiter may be one) that goes off the screen
     */
    private static function losesADelimiterOffTheScreen(Screen $screen, int $row, int $firstCol): bool
    {
        for ($col = 1; $col < $firstCol; $col++) {
            if (self::isTextSpace($screen->cell($row, $col))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the cell at row, col comes before the first cell of its row a zone can start
     * again in, once moved left so that firstCol is its column 1, or is that cell
     * (isTheRestart())
     */
    private static function isUpToTheRestart(Screen $screen, int $row, int $col, int $firstCol): bool
    {
        for ($before = $firstCol; $before < $col; $before++) {
            if (self::isTheRestart($screen, $row, $before, $firstCol)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a zone can start again in the cell at row, col, once moved left so that
     * firstCol is its column 1: a text space, or the right half of a double-width character
     * in firstCol, which Offset writes as a space (#170)
     */
    private static function isTheRestart(Screen $screen, int $row, int $col, int $firstCol): bool
    {
        $cell = $screen->cell($row, $col);

        return self::isTextSpace($cell) || $col === $firstCol && $cell?->part->isRight() === true;
    }

    // Whether the cell shows a text space (a delimiter is one)
    private static function isTextSpace(?Cell $cell): bool
    {
        return $cell?->bytes === ' ' && !$cell->attributes->isMosaic();
    }

    /**
     * The cell as the delimiter starting its zone again shows: its background, but neither
     * underlined, as a delimiter isn't (Writer), nor masked, which shows nothing more on a
     * space (#170)
     */
    private static function asADelimiter(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }
        $attributes = $cell->attributes->withUnderlining(Underlining::NoUnderline)->withMasking(Masking::Unmask);

        return new Cell($cell->bytes, $attributes, $cell->part);
    }

    /**
     * A cell without the zone attributes it may have from a zone started off the screen: its
     * masking, and in text its underline and background, unless a mosaic or empty cell
     * before it gives it (compared apart, as the cells around them are)
     */
    private static function withoutZoneFromOffTheScreen(?Cell $cell): ?Cell
    {
        if ($cell === null) {
            return null;
        }
        $zone = $cell->attributes->isMosaic() ? Zone::none()->on($cell->attributes->background()) : Zone::none();

        return $cell->inZone($zone);
    }

    /**
     * For each cell of the screen a character takes: the first column of that character, and
     * its width. A double-width text character takes two cells: the cell showing its right
     * half (Part) is in the column after its first one, even when its left half was drawn
     * over since.
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private static function glyphColumns(Screen $screen): array
    {
        $columns = [];
        for ($row = 0; $row <= ScreenSize::ROWS; $row++) {
            for ($col = 1; $col <= ScreenSize::COLS; $col++) {
                $cell = $screen->cell($row, $col);
                $columns[$row][$col] = [
                    $cell?->part->isRight() === true ? $col - 1 : $col,
                    $cell?->attributes->width() ?? 1,
                ];
            }
        }

        return $columns;
    }
}
