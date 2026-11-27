<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;

/**
 * The rows of a screen, as the screen tests compare them
 */
final class ScreenRows
{
    /**
     * Each cell of row, from column 1 to length, as ZonesTest::zonesOnTheRowAbove() gives them
     */
    public static function zones(Screen $screen, int $row, int $length): string
    {
        return self::cells($screen, $row, $length, static fn(Attributes $a): string => match (true) {
            $a->isMasked() => 'M',
            $a->isUnderlined() => 'U',
            default => match ($a->background()) {
                Color::Blue => 'B',
                Color::Red => 'R',
                Color::Green => 'G',
                Color::Black => '.',
                default => '?',
            },
        });
    }

    /**
     * Each cell of row, from column 1 to length, as shown: ' ' if empty
     *
     * @param \Closure(Attributes): string $shown
     */
    public static function cells(Screen $screen, int $row, int $length, \Closure $shown): string
    {
        $cells = '';
        for ($col = 1; $col <= $length; $col++) {
            $cell = $screen->cell($row, $col);
            $cells .= $cell === null ? ' ' : $shown($cell->attributes);
        }

        return $cells;
    }

    /**
     * Rows of the screen drawn by Videotex content that are not empty, as in Screen::preview()
     *
     * @return list<string>
     */
    public static function drawn(Vdt $vdt): array
    {
        $rows = explode("\n", rtrim(Screen::of($vdt)->preview(), characters: "\n"));

        return array_values(array_filter(
            $rows,
            static fn(string $row) => trim(substr($row, offset: 3), characters: ' |') !== '',
        ));
    }
}
