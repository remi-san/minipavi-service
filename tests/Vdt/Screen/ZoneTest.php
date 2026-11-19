<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Color;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Zone;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\TestCase;

final class ZoneTest extends TestCase
{
    public function testNoneIsNotUnderlinedNorWaiting(): void
    {
        self::assertFalse(Zone::none()->isUnderlined());
        self::assertFalse(Zone::none()->isWaiting());
    }

    public function testUnderlineWaitsForTheDelimiter(): void
    {
        $zone = Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults());

        self::assertTrue($zone->isWaiting());
        self::assertFalse($zone->isUnderlined());
        self::assertTrue($zone->delimited()->isUnderlined());
        self::assertFalse($zone->delimited()->isWaiting());
        self::assertFalse(
            $zone->delimited()->with(Attributes::NO_UNDERLINE, Attributes::defaults())->delimited()->isUnderlined(),
        );
    }

    public function testBackgroundAndMaskingDelimitTheSameZone(): void
    {
        $underlined = Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults())->delimited();

        foreach ([Attributes::backgroundColor(Color::Blue), Attributes::MASK, Attributes::UNMASK] as $byte) {
            $zone = $underlined->with($byte, Attributes::defaults());

            self::assertTrue($zone->isWaiting());
            self::assertTrue($zone->delimited()->isUnderlined());
        }
    }

    public function testOnlyMaskingWaitsInMosaic(): void
    {
        $mosaic = Attributes::defaults()->withCharset(Videotex::MOSAIC_SET);

        self::assertFalse(Zone::none()->with(Attributes::UNDERLINE, $mosaic)->isWaiting());
        self::assertFalse(Zone::none()->with(Attributes::backgroundColor(Color::Blue), $mosaic)->isWaiting());
        self::assertTrue(Zone::none()->with(Attributes::MASK, $mosaic)->isWaiting());
        // Nor anything else
        self::assertFalse(Zone::none()->with(Attributes::INVERSE, Attributes::defaults())->isWaiting());
    }

    public function testSoEndsTheZoneAndAppliesTheWaitingBackground(): void
    {
        $underlined = Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults())->delimited();
        $background = $underlined->with(Attributes::backgroundColor(Color::Blue), Attributes::defaults());

        self::assertFalse($underlined->withCharset(Videotex::MOSAIC_SET)->isUnderlined());
        self::assertFalse($background->withCharset(Videotex::MOSAIC_SET)->isWaiting());
        // SI changes nothing
        self::assertEquals($background, $background->withCharset(Videotex::TEXT_SET));
    }

    public function testSoKeepsTheUnderlineWaiting(): void
    {
        $zone = Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults())->withCharset(Videotex::MOSAIC_SET);

        self::assertTrue($zone->isWaiting());
        self::assertTrue($zone->delimited()->isUnderlined());
    }

    public function testDelimiterCodes(): void
    {
        $underlined = Zone::none()->with(Attributes::UNDERLINE, Attributes::defaults())->delimited();

        self::assertSame(Videotex::UNDERLINE_ON, $underlined->delimiterCodes());
        self::assertSame(Videotex::UNDERLINE_OFF, Zone::none()->delimiterCodes());
    }
}
