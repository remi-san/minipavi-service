<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt\Screen;

use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Charset;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\ShownScreen;
use MiniPaviService\Vdt\Screen\Zone;
use PHPUnit\Framework\TestCase;

/**
 * The top half of a tall text character still shows on the row above once the screen is drawn
 * while that row shows the same character there: drawn at the same row and column, a space or
 * not as it is (ShownScreen::showsOnTop())
 */
final class ShownScreenTest extends TestCase
{
    public function testTheTopHalfOfATallSpaceShows(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall());

        self::assertTrue($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testTheTopHalfShowsUnderAnotherSpaceDrawnOverItsBottomHalf(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall());
        $screen->glyph(new Position(5, 3), ' ', Attributes::defaults());

        self::assertTrue($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testTheTopHalfOfALetterIsntTheSpaceDrawnOverItsBottomHalf(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), 'A', self::tall());
        $screen->glyph(new Position(5, 3), ' ', Attributes::defaults());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testTheTopHalfOfACharacterDrawnInTheColumnBeforeIsntIt(): void
    {
        $screen = new ShownScreen();
        $wide = self::tall()->withSize(CharacterSize::Double);
        $screen->glyph(new Position(5, 2), ' ', $wide);
        $screen->glyph(new Position(5, 3), ' ', Attributes::defaults());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testASpaceDrawnOnTheRowAboveIsntTheTopHalf(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall());
        $screen->glyph(new Position(4, 3), ' ', Attributes::defaults());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testAMosaicCharacterOnTheRowAboveIsntTheTopHalf(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall());
        $screen->glyph(new Position(4, 3), ' ', self::mosaic());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    public function testATopHalfOverAMosaicCharacterIsntTheCharacterDrawnBelow(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall());
        $screen->glyph(new Position(5, 3), ' ', self::mosaic());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    /**
     * Two mosaic cells, one above the other, show no tall text character: unlike the arrays
     * ShownScreen kept before #185, which compared them as both null
     */
    public function testTwoMosaicCellsArentATopHalf(): void
    {
        $screen = new ShownScreen();
        $screen->glyph(new Position(5, 3), ' ', self::tall()->withSize(CharacterSize::Double));
        $screen->glyph(new Position(5, 3), ' ', self::mosaic());
        $screen->glyph(new Position(4, 3), ' ', self::mosaic());

        self::assertFalse($screen->showsOnTop(new Position(5, 3), Zone::none()));
    }

    private static function tall(): Attributes
    {
        return Attributes::defaults()->withSize(CharacterSize::DoubleHeight);
    }

    private static function mosaic(): Attributes
    {
        return Attributes::defaults()->withCharset(Charset::Mosaic);
    }
}
