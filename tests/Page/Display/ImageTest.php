<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\ScreenSize;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Display\Image;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    /**
     * Pictures whose zones change after they are drawn, in row 1 (#126): drawn at a position,
     * so moved right and down, none of their columns goes off the screen on the left
     *
     * @return iterable<string, array{string}> the picture
     */
    public static function zonesChangedAfterTheyAreDrawn(): iterable
    {
        // ESC R, CAN, its first space a green delimiter, then XY over it: the row is black
        yield 'a clear line, its delimiter drawn over' => ["\x1F\x41\x41\x1B\x52\x18XY"];
        // A clear screen (only bringing the cursor home), then the same
        yield 'after a clear screen' => ["\x0C\x1B\x52\x18XY"];
        // Spaces and ABC from column 2, then a green delimiter in column 1: they are green
        yield 'a delimiter drawn after the cells it colours' => ["\x1F\x41\x42   ABC\x1F\x41\x41\x1B\x52 "];
    }

    #[DataProvider('zonesChangedAfterTheyAreDrawn')]
    public function testImageShowsTheBackgroundsOfThePicture(string $picture): void
    {
        $shown = Screen::of(new Vdt($picture));
        $drawn = Screen::of((new Image(new Vdt($picture), new Position(5, 3)))->toVdt(new Terminal()));

        $expected = [];
        $actual = [];
        for ($col = 1; $col <= (ScreenSize::COLS - 2); $col++) {
            $expected[] = $shown->cell(1, $col)?->attributes->background() ?? Color::Black;
            $actual[] = $drawn->cell(5, $col + 2)?->attributes->background() ?? Color::Black;
        }
        self::assertSame($expected, $actual);
    }
}
