<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\TestCase;

final class AttributesTest extends TestCase
{
    public function testUnderlinedMosaicIsSeparated(): void
    {
        $mosaic = Attributes::defaults()->withCharset(Videotex::MOSAIC_SET);

        self::assertTrue($mosaic->with(Attributes::UNDERLINE)->isSeparated());
        self::assertFalse($mosaic->isSeparated());
    }

    public function testUnderlinedTextIsntSeparated(): void
    {
        self::assertFalse(Attributes::defaults()->with(Attributes::UNDERLINE)->isSeparated());
    }
}
