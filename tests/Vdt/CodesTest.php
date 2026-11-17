<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Vdt;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Http\EmulatorConfig;
use MiniPaviService\Vdt\Keyboard;
use MiniPaviService\Vdt\LocalEcho;
use MiniPaviService\Vdt\Scroll;
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
        class_exists(MiniPaviCli::class);
    }

    public function testVideotexCodes(): void
    {
        self::assertSame(
            [VDT_CLRLN, VDT_CUROFF, VDT_FDINV, VDT_FDNORM, VDT_STARTUNDERLINE, VDT_STOPUNDERLINE,
                VDT_SZNORM, VDT_SZDBLH, VDT_SZDBLW, VDT_SZDBLHW, VDT_RESET_DRCS],
            [Videotex::CLEAR_LINE, Videotex::CURSOR_OFF, Videotex::INVERSE_ON, Videotex::INVERSE_OFF, Videotex::UNDERLINE_ON,
                Videotex::UNDERLINE_OFF, Videotex::SIZE_NORMAL, Videotex::SIZE_DOUBLE_HEIGHT, Videotex::SIZE_DOUBLE_WIDTH,
                Videotex::SIZE_DOUBLE, Videotex::RESET_CHARSETS],
        );
    }

    public function testProtocolSequences(): void
    {
        self::assertSame(
            [PRO_MIN, PRO_MAJ, PRO_LOCALECHO_OFF, PRO_LOCALECHO_ON, PRO_ROULEAU_OFF, PRO_ROULEAU_ON],
            [Keyboard::Lowercase->sequence(), Keyboard::Uppercase->sequence(), LocalEcho::Off->sequence(),
                LocalEcho::On->sequence(), Scroll::Off->sequence(), Scroll::On->sequence()],
        );
    }

    public function testFunctionKeyMasks(): void
    {
        self::assertSame(
            [MSK_SOMMAIRE, MSK_ANNULATION, MSK_RETOUR, MSK_REPETITION, MSK_GUIDE, MSK_CORRECTION, MSK_SUITE, MSK_ENVOI],
            [FunctionKey::Summary->mask(), FunctionKey::Cancel->mask(), FunctionKey::Previous->mask(), FunctionKey::Refresh->mask(),
                FunctionKey::Guide->mask(), FunctionKey::Correction->mask(), FunctionKey::Next->mask(), FunctionKey::Send->mask()],
        );
    }

    public function testPublicGateway(): void
    {
        self::assertSame(DEFAULT_MINIPAVI_GW, EmulatorConfig::PUBLIC_GATEWAY);
    }
}
