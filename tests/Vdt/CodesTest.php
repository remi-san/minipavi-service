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
use MiniPaviService\Vdt\Screen\Attribute\AttributeReader;
use MiniPaviService\Vdt\Screen\Attribute\CharacterSize;
use MiniPaviService\Vdt\Screen\Attribute\Inversion;
use MiniPaviService\Vdt\Screen\Attributes;
use MiniPaviService\Vdt\Screen\Screen;
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
     * attributes (Videotex's codes are made of Attribute's bytes)
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
            self::assertNotNull(AttributeReader::read($off[1]), bin2hex($off));
        }
        // Each turns its "on" code off again
        self::assertEquals($defaults, $defaults->with(Inversion::Inverse)->with(Inversion::NoInverse));
        self::assertEquals($defaults, $defaults->with(CharacterSize::Double)->with(CharacterSize::Normal));
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
        // Except the deviations (below)
        yield 'every character it encodes' => [
            'éèàçêÉÈÀÊœŒûùôïîëâ£°±↑→↓¼½¾ÂÎ’ ňćřýšą',
        ];
        yield 'control characters' => ["a\x00b\x0Ac\x1Fd\x7Fe"];
        yield 'plain text' => ['Hello, world! 0123456789 {}~'];
        // 0x8D, 0x81, 0x9D, 0x8F and 0x90, each in a letter
        yield 'bytes MiniPaviCli takes as undefined (in UTF-8 letters it doesn\'t encode)' => ['čŁŝďĐ'];
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

    /**
     * The text, what the Minitel shows with MiniPaviCli, and what it shows with Vdt
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function deviations(): iterable
    {
        // MiniPaviCli sends the cedilla with a lowercase c (#58)
        yield 'Ç' => ['Ç', 'ç', 'Ç'];
        // MiniPaviCli replaces the bytes it takes as undefined before encoding the characters
        // holding them (#137): ← (e2 86 90) is sent as e2 86 20, shown as ? then a space
        yield '←' => ['←', '? ', '←'];
        yield 'ō' => ['ō', '? ', 'o'];
        yield 'ā' => ['ā', '? ', 'a'];
        // MiniPaviCli sends G2 codes the Minitel doesn't have, shown as _ (#137): the letter
        // without its accent, ß as ss, β as b
        yield 'ü' => ['ü', '_', 'u'];
        yield 'ä' => ['ä', '_', 'a'];
        yield 'ö' => ['ö', '_', 'o'];
        yield 'ú' => ['ú', '_', 'u'];
        yield 'ó' => ['ó', '_', 'o'];
        yield 'í' => ['í', '_', 'i'];
        yield 'á' => ['á', '_', 'a'];
        yield 'ò' => ['ò', '_', 'o'];
        yield 'ì' => ['ì', '_', 'i'];
        yield 'ß' => ['ß', '_', 'ss'];
        yield 'β' => ['β', '_', 'b'];
        // MiniPaviCli doesn't encode characters the Minitel has, sent as ? (#137)
        yield '÷' => ['÷', '?', '÷'];
        yield 'Ë' => ['Ë', '?', 'Ë'];
        // MiniPaviCli doesn't encode the Latin-1 letters the Minitel doesn't have, sent as ?, or
        // as ? and a space for those holding a byte it takes as undefined (#137): the letter
        // without its accent, in the same case, Æ æ as AE ae, Ð ð as D d, Þ þ as TH th
        foreach (['Á' => 'A', 'Í' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ý' => 'Y'] as $letter => $shown) {
            yield $letter => [$letter, '? ', $shown];
        }
        foreach ([
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ì' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Þ' => 'TH',
            'ã' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ð' => 'd',
            'ñ' => 'n',
            'õ' => 'o',
            'ø' => 'o',
            'þ' => 'th',
            'ÿ' => 'y',
        ] as $letter => $shown) {
            yield $letter => [$letter, '?', $shown];
        }
        // The Minitel has no §, nor anything like it: Vdt sends the ? the Minitel shows (#137).
        // Sent in 7 bits, MiniPaviCli's § is ? too: VdtTest checks the screen shows ? for it
        yield '§' => ['§', '?', '?'];
    }

    /**
     * Where MiniPavi's emulator shows MiniPaviCli's text is wrong, Vdt deviates from it on
     * purpose: the Minitel shows one thing with what MiniPaviCli sends, another with what Vdt
     * sends. Both are sent as MiniPaviCli::send() sends them, in 7 bits: each character left
     * out of 7 bits is sent as ?
     */
    #[DataProvider('deviations')]
    public function testVdtDeviatesFromMiniPaviCliWhereTheMinitelShowsItIsWrong(
        string $text,
        string $shownWithMiniPaviCli,
        string $shownWithVdt,
    ): void {
        self::assertStringStartsWith($shownWithMiniPaviCli . ' ', self::shown(MiniPaviCli::toG2($text)));
        self::assertStringStartsWith($shownWithVdt . ' ', self::shown(Vdt::text($text)->toString()));
    }

    /**
     * Row 1 of the screen, once bytes are sent as MiniPaviCli::send() sends them
     */
    private static function shown(string $bytes): string
    {
        $sent = mb_convert_encoding($bytes, to_encoding: '7bit');
        self::assertIsString($sent);

        return Screen::of(new Vdt($sent))->line(1);
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

    /**
     * The sequences Vdt and the Writer write, POSITION and REPEAT with their bytes, are
     * MiniPaviCli's
     */
    public function testVideotexEncodesPositionAndRepeatAsMiniPaviCli(): void
    {
        foreach ([[0, 1], [1, 1], [24, 40], [12, 7]] as [$row, $col]) {
            self::assertSame(MiniPaviCli::setPos($col, $row), Videotex::position($row, $col));
        }
        foreach ([1, 2, 39, Videotex::MAX_REPEAT] as $count) {
            self::assertSame(MiniPaviCli::repeatChar('-', $count + 1), '-' . Videotex::repeat($count));
        }
    }
}
