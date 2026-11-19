<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\Videotex;
use PHPUnit\Framework\Attributes\DataProvider;
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
            VDT_G2,
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
            Videotex::G2,
        ]);
    }

    /**
     * The Minitel starts with every attribute off, and the codes turning them off are
     * attributes (Videotex's codes are made of Attributes' bytes)
     */
    public function testAttributesStartOffAndTheirOffCodesAreAttributes(): void
    {
        $defaults = Attributes::defaults();

        self::assertFalse($defaults->isDoubleHeight() || $defaults->isDoubleWidth());
        self::assertFalse($defaults->isBlinking());
        self::assertFalse($defaults->isMasked());
        self::assertFalse($defaults->isUnderlined());
        self::assertFalse($defaults->isInverse());
        foreach ([
            Videotex::SIZE_NORMAL,
            Videotex::BLINK_OFF,
            Videotex::MASK_OFF,
            Videotex::UNDERLINE_OFF,
            Videotex::INVERSE_OFF,
        ] as $off) {
            self::assertSame(Videotex::ESC, $off[0]);
            self::assertTrue(Attributes::isAttribute($off[1]), bin2hex($off));
        }
        // Each turns its "on" code off again
        self::assertEquals($defaults, $defaults->with(Attributes::INVERSE)->with(Attributes::NO_INVERSE));
        self::assertEquals($defaults, $defaults->with(Attributes::DOUBLE)->with(Attributes::SIZE_NORMAL));
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function texts(): iterable
    {
        yield 'every character it encodes' => [
            'éèàçêÉÈÀÇÊβßœŒüûúùöôóòïîíìëäâá£°±←↑→↓¼½¾ÂÎōá’ ňćřýšíąā',
        ];
        yield 'control characters' => ["a\x00b\x0Ac\x1Fd\x7Fe"];
        yield 'plain text' => ['Hello, world! 0123456789 {}~'];
        yield 'bytes MiniPaviCli takes as undefined (in UTF-8 letters, e.g. Á, Í, Ý)' => ['ÁÍÏÐÝ'];
    }

    /**
     * Vdt encodes as MiniPaviCli's helpers do, byte for byte
     */
    #[DataProvider('texts')]
    public function testVdtEncodesTextAsMiniPaviCli(string $text): void
    {
        self::assertSame(MiniPaviCli::toG2($text), Vdt::text($text)->toString());
        self::assertSame(MiniPaviCli::writeLine0($text), Vdt::line0($text)->toString());
    }

    public function testVdtEncodesSequencesAsMiniPaviCli(): void
    {
        self::assertSame(MiniPaviCli::clearScreen(), Vdt::clearScreen()->toString());
        foreach ([[0, 1], [1, 1], [24, 40], [12, 7]] as [$row, $col]) {
            self::assertSame(MiniPaviCli::setPos($col, $row), Vdt::position(new Position($row, $col))->toString());
        }
        foreach ([1, 2, 40, 64] as $count) {
            self::assertSame(MiniPaviCli::repeatChar('-', $count), Vdt::repeat('-', $count)->toString());
        }
    }
}
