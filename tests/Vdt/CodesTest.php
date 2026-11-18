<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\TestCase;

/**
 * The framework's codes are the ones MiniPaviCli defines as global constants
 */
final class CodesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Its constants are defined in its class file
        self::assertTrue(class_exists(MiniPaviCli::class));
    }

    public function testVideotexCodes(): void
    {
        self::assertSame([
            VDT_CLRLN,
            VDT_CUROFF,
            VDT_FDINV,
            VDT_FDNORM,
            VDT_STARTUNDERLINE,
            VDT_STOPUNDERLINE,
            VDT_SZNORM,
            VDT_SZDBLH,
            VDT_SZDBLW,
            VDT_SZDBLHW,
            VDT_RESET_DRCS,
        ], [
            Videotex::CLEAR_LINE,
            Videotex::CURSOR_OFF,
            Videotex::INVERSE_ON,
            Videotex::INVERSE_OFF,
            Videotex::UNDERLINE_ON,
            Videotex::UNDERLINE_OFF,
            Videotex::SIZE_NORMAL,
            Videotex::SIZE_DOUBLE_HEIGHT,
            Videotex::SIZE_DOUBLE_WIDTH,
            Videotex::SIZE_DOUBLE,
            Videotex::RESET_CHARSETS,
        ]);
    }

    public function testControlCodes(): void
    {
        self::assertSame([
            VDT_LEFT,
            VDT_RIGHT,
            VDT_DOWN,
            VDT_UP,
            VDT_CR,
            VDT_CLR,
            VDT_POS,
            VDT_REP,
            VDT_G0,
            VDT_G1,
            VDT_BLINK,
            VDT_FIXED,
        ], [
            Videotex::CURSOR_LEFT,
            Videotex::CURSOR_RIGHT,
            Videotex::CURSOR_DOWN,
            Videotex::CURSOR_UP,
            Videotex::CARRIAGE_RETURN,
            Videotex::CLEAR_SCREEN,
            Videotex::POSITION,
            Videotex::REPEAT,
            Videotex::TEXT_SET,
            Videotex::MOSAIC_SET,
            Videotex::BLINK_ON,
            Videotex::BLINK_OFF,
        ]);
    }

    /**
     * Attributes names the bytes after ESC itself: they are those of Videotex's codes
     */
    public function testAttributesReadVideotexCodes(): void
    {
        $set = static fn(string $code) => Attributes::defaults()->with(substr($code, offset: 1));

        self::assertTrue($set(Videotex::SIZE_DOUBLE_HEIGHT)->isDoubleHeight());
        self::assertTrue($set(Videotex::SIZE_DOUBLE_WIDTH)->isDoubleWidth());
        self::assertTrue($set(Videotex::SIZE_DOUBLE)->isDoubleHeight() && $set(Videotex::SIZE_DOUBLE)->isDoubleWidth());
        self::assertTrue($set(Videotex::BLINK_ON)->isBlinking());
        self::assertTrue($set(Videotex::MASK_ON)->isMasked());
        self::assertTrue($set(Videotex::UNDERLINE_ON)->isUnderlined());
        self::assertTrue($set(Videotex::INVERSE_ON)->isInverse());
        // The codes turning them off give the defaults back
        self::assertEquals(
            Attributes::defaults(),
            Attributes::defaults()
                ->with(substr(Videotex::SIZE_DOUBLE, offset: 1))
                ->with(substr(Videotex::SIZE_NORMAL, offset: 1))
                ->with(substr(Videotex::BLINK_ON, offset: 1))
                ->with(substr(Videotex::BLINK_OFF, offset: 1))
                ->with(substr(Videotex::MASK_ON, offset: 1))
                ->with(substr(Videotex::MASK_OFF, offset: 1))
                ->with(substr(Videotex::UNDERLINE_ON, offset: 1))
                ->with(substr(Videotex::UNDERLINE_OFF, offset: 1))
                ->with(substr(Videotex::INVERSE_ON, offset: 1))
                ->with(substr(Videotex::INVERSE_OFF, offset: 1)),
        );
        self::assertSame(Videotex::ESC, Videotex::INVERSE_ON[0]);
    }

    public function testProtocolSequences(): void
    {
        self::assertSame([PRO_MIN, PRO_MAJ, PRO_LOCALECHO_OFF, PRO_LOCALECHO_ON, PRO_ROULEAU_OFF, PRO_ROULEAU_ON], [
            Keyboard::Lowercase->sequence(),
            Keyboard::Uppercase->sequence(),
            LocalEcho::Off->sequence(),
            LocalEcho::On->sequence(),
            Scroll::Off->sequence(),
            Scroll::On->sequence(),
        ]);
    }

    public function testFunctionKeyMasks(): void
    {
        self::assertSame([
            MSK_SOMMAIRE,
            MSK_ANNULATION,
            MSK_RETOUR,
            MSK_REPETITION,
            MSK_GUIDE,
            MSK_CORRECTION,
            MSK_SUITE,
            MSK_ENVOI,
        ], [
            FunctionKey::Summary->mask(),
            FunctionKey::Cancel->mask(),
            FunctionKey::Previous->mask(),
            FunctionKey::Refresh->mask(),
            FunctionKey::Guide->mask(),
            FunctionKey::Correction->mask(),
            FunctionKey::Next->mask(),
            FunctionKey::Send->mask(),
        ]);
    }

    public function testPublicGateway(): void
    {
        self::assertSame(DEFAULT_MINIPAVI_GW, EmulatorConfig::PUBLIC_GATEWAY);
    }
}
