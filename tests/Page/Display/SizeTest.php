<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Display;

use MiniPaviService\Page\Display\Size;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\TestCase;

final class SizeTest extends TestCase
{
    public function testEachSizeIsItsVideotexAttributeAndWidth(): void
    {
        $sizes = array_map(static fn(Size $size): array => [$size->toVdt()->toString(), $size->width()], Size::cases());

        self::assertSame(
            [
                [Videotex::SIZE_NORMAL,        1],
                [Videotex::SIZE_DOUBLE_HEIGHT, 1],
                [Videotex::SIZE_DOUBLE_WIDTH,  2],
                [Videotex::SIZE_DOUBLE,        2],
            ],
            $sizes,
        );
    }

    public function testEachSizeNarrowsToOneColumnKeepingItsHeight(): void
    {
        $narrowed = array_map(static fn(Size $size): string => $size->narrowedToVdt()->toString(), Size::cases());

        self::assertSame(
            [
                Videotex::SIZE_NORMAL,
                Videotex::SIZE_DOUBLE_HEIGHT,
                Videotex::SIZE_NORMAL,
                Videotex::SIZE_DOUBLE_HEIGHT,
            ],
            $narrowed,
        );
    }
}
