<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\ScreenRows;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\Screen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where and how a page draws its elements: their position, colour, alignment, and the keys
 */
final class ElementLayoutTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testStaticPageDrawsItsElements(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="static">
              <text row="2" align="center" size="double-width">Hello</text>
              <text row="4" col="3" color="red">Left</text>
              <line row="5" col="3" length="6" char="bottom"/>
              <key row="7" col="10">Envoi</key>
              <image row="9" col="5">images/logo.vdt</image>
            </page>
            XML);

        self::assertSame(
            [
                ' 2|               HHeelllloo               |',
                ' 4|  Left                                  |',
                ' 5|  ▔▔▔▔▔▔                                |',
                ' 7|           Envoi                        |',
                ' 9|    ##                                  |',
                '10|    ok                                  |',
            ],
            ScreenRows::drawn($page->display(new Terminal())),
        );
    }

    public function testValuesMayHaveSpacesAroundThem(): void
    {
        // As the schema reads them (it collapses the spaces of numbers, files, tests and keys)
        $page = $this->pages->parse(<<<'XML'
            <page type="static">
              <text row=" 4 " col=" 3 ">Left</text>
              <image row="9" col="5">
                images/logo.vdt
              </image>
              <input row=" 20 " col=" 5 " length=" 10 " lines=" 1 " validwith="  envoi   suite  "/>
            </page>
            XML);
        $template = $this->pages->parse(<<<'XML'
            <page type="template">
              <if test=" shown "><text row="1">shown</text></if>
              <if test=" !hidden "><text row="2">not hidden</text></if>
            </page>
            XML);

        self::assertSame(
            [
                ' 4|  Left                                  |',
                ' 9|    ##                                  |',
                '10|    ok                                  |',
            ],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        self::assertEquals(
            new InputCommand(
                new InputField(new Span(new Position(20, 5), 10), validWith: [FunctionKey::Send, FunctionKey::Next]),
            ),
            $page->inputCommand(),
        );
        self::assertSame(
            [' 1|shown                                   |', ' 2|not hidden                              |'],
            ScreenRows::drawn($template->display(new Terminal(), ['shown' => true, 'hidden' => false])),
        );
    }

    public function testCenteredTextOfEvenWidthHasOneMoreColumnOnTheRight(): void
    {
        // 4 columns: 18 on the left, 18 on the right
        $page = $this->pages->parse('<page type="static"><text row="1" align="center">Jeux</text></page>');

        self::assertSame(
            [' 1|' . str_repeat(' ', times: 18) . 'Jeux' . str_repeat(' ', times: 18) . '|'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
    }

    public function testEndKeyDependsOnTheTerminal(): void
    {
        $page = $this->pages->parse('<page type="static"><endkey/></page>');

        self::assertSame(
            ['24|             Connexion/fin              |'],
            ScreenRows::drawn($page->display(new Terminal())),
        );

        // ESP32 / Minimit adapters: a websocket with a known Minitel, "[Maj] +" on row 23
        $adapter = new Terminal('192.0.2.1', 'websocket', 'Cv5');
        self::assertSame(['23|        [Maj] +  Connexion/fin          |'], ScreenRows::drawn($page->display($adapter)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function elementsWithoutPosition(): iterable
    {
        yield 'text' => ['<text>Top</text>', [' 1|Top' . str_repeat(' ', times: 37) . '|']];
        yield 'line' => ['<line length="3"/>', [' 1|───' . str_repeat(' ', times: 37) . '|']];
        // An underlined space, " Envoi " in reverse video, and a space
        yield 'key' => ['<key>Envoi</key>', [' 1|  Envoi' . str_repeat(' ', times: 33) . '|']];
        yield 'image' => [
            '<image>images/logo.vdt</image>',
            [' 1|##' . str_repeat(' ', times: 38) . '|', ' 2|ok' . str_repeat(' ', times: 38) . '|'],
        ];
    }

    /**
     * @param list<string> $rows
     */
    #[DataProvider('elementsWithoutPosition')]
    public function testElementWithoutRowOrColStartsAtTheTopLeft(string $element, array $rows): void
    {
        $page = $this->pages->parse("<page type=\"static\">{$element}</page>");

        self::assertSame($rows, ScreenRows::drawn($page->display(new Terminal())));
    }

    public function testElementsWithoutColorHaveTheirOwn(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="static">
              <text row="1">Text</text>
              <line row="2" length="3"/>
              <key row="3">Envoi</key>
              <endkey/>
            </page>
            XML);
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(Color::White, self::cell($screen, row: 1, col: 1)->attributes->foreground());
        self::assertSame(Color::White, self::cell($screen, row: 2, col: 1)->attributes->foreground());
        self::assertSame(Color::Green, self::cell($screen, row: 3, col: 3)->attributes->foreground());
        self::assertSame(Color::Green, self::cell($screen, row: 24, col: 16)->attributes->foreground());
    }

    public function testElementsAreInTheColorGiven(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="static">
              <text row="1" color="red">Text</text>
              <line row="2" length="3" color="red"/>
              <key row="3" color="red">Envoi</key>
              <endkey color="red"/>
            </page>
            XML);
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(Color::Red, self::cell($screen, row: 1, col: 1)->attributes->foreground());
        self::assertSame(Color::Red, self::cell($screen, row: 2, col: 1)->attributes->foreground());
        self::assertSame(Color::Red, self::cell($screen, row: 3, col: 3)->attributes->foreground());
        self::assertSame(Color::Red, self::cell($screen, row: 24, col: 16)->attributes->foreground());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leftAlignedTexts(): iterable
    {
        yield 'by default' => [''];
        yield 'align="left"' => ['align="left"'];
        yield 'fill="no"' => ['fill="no"'];
    }

    #[DataProvider('leftAlignedTexts')]
    public function testTextIsAtItsColumnAndDoesntFillTheRow(string $attributes): void
    {
        $page = $this->pages->parse(
            "<page type=\"static\"><text row=\"2\" col=\"5\" bg=\"blue\" {$attributes}>ab</text></page>",
        );
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(
            [' 2|    ab' . str_repeat(' ', times: 34) . '|'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        self::assertNull($screen->cell(row: 2, col: 40));
    }

    public function testFilledTextPaintsTheRestOfTheRow(): void
    {
        $page = $this->pages->parse('<page type="static"><text row="2" col="5" bg="blue" fill="yes">ab</text></page>');
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(
            [' 2|    ab' . str_repeat(' ', times: 34) . '|'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        // From the space before it, which starts its background colour
        self::assertSame(Color::Blue, self::cell($screen, row: 2, col: 4)->attributes->background());
        self::assertSame(Color::Blue, self::cell($screen, row: 2, col: 5)->attributes->background());
        self::assertSame(Color::Blue, self::cell($screen, row: 2, col: 40)->attributes->background());
        self::assertNull($screen->cell(row: 2, col: 3));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function centeredTexts(): iterable
    {
        // A column per character, not per byte: 3 columns, 18 on the left, 19 on the right
        yield 'accented' => [
            '<text row="1" align="center">Été</text>',
            ' 1|' . str_repeat(' ', times: 18) . 'Été' . str_repeat(' ', times: 19) . '|',
        ];
        // Two columns per character: 8 columns, 16 on each side
        yield 'double width' => [
            '<text row="1" align="center" size="double-width">Jeux</text>',
            ' 1|' . str_repeat(' ', times: 16) . 'JJeeuuxx' . str_repeat(' ', times: 16) . '|',
        ];
        yield 'as wide as the screen' => [
            '<text row="1" align="center" size="double-width">ABCDEFGHIJKLMNOPQRST</text>',
            ' 1|AABBCCDDEEFFGGHHIIJJKKLLMMNNOOPPQQRRSSTT|',
        ];
        // From the first column, the rest going on the next row
        yield 'wider than the screen' => [
            '<text row="1" align="center" size="double-width">ABCDEFGHIJKLMNOPQRSTUV</text>',
            ' 1|AABBCCDDEEFFGGHHIIJJKKLLMMNNOOPPQQRRSSTT|',
        ];
        // The spaces before it paint the row from its first column
        yield 'filled' => [
            '<text row="1" align="center" bg="blue" fill="yes">Jeux</text>',
            ' 1|' . str_repeat(' ', times: 18) . 'Jeux' . str_repeat(' ', times: 18) . '|',
        ];
        // No room for a space before it: the space, which starts its background colour, is
        // in the first column, and the text after it, the rest going on the next row
        yield 'filled, as wide as the screen' => [
            '<text row="1" align="center" size="double-width" bg="blue" fill="yes">ABCDEFGHIJKLMNOPQRST</text>',
            ' 1|  AABBCCDDEEFFGGHHIIJJKKLLMMNNOOPPQQRRSS|',
        ];
        yield 'filled, wider than the screen' => [
            '<text row="1" align="center" size="double-width" bg="blue" fill="yes">ABCDEFGHIJKLMNOPQRSTUV</text>',
            ' 1|  AABBCCDDEEFFGGHHIIJJKKLLMMNNOOPPQQRRSS|',
        ];
        yield 'filled, double width' => [
            '<text row="1" align="center" size="double-width" bg="blue" fill="yes">Jeux</text>',
            ' 1|' . str_repeat(' ', times: 16) . 'JJeeuuxx' . str_repeat(' ', times: 16) . '|',
        ];
    }

    #[DataProvider('centeredTexts')]
    public function testCenteredTextHasAsManyColumnsOnEachSide(string $text, string $row): void
    {
        $page = $this->pages->parse("<page type=\"static\">{$text}</page>");

        self::assertSame($row, ScreenRows::drawn($page->display(new Terminal()))[0]);
    }

    public function testFilledCenteredTextPaintsTheWholeRow(): void
    {
        $page = $this->pages->parse(
            '<page type="static"><text row="1" align="center" bg="blue" fill="yes">Jeux</text></page>',
        );
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(Color::Blue, self::cell($screen, row: 1, col: 1)->attributes->background());
        self::assertSame(Color::Blue, self::cell($screen, row: 1, col: 40)->attributes->background());
    }

    public function testEndKeyIsCenteredByCharacters(): void
    {
        $page = $this->pages->parse(<<<'XML'
            <page type="static">
              <message name="endkey">Élève</message>
              <message name="endkey.shift">[Maj] é</message>
              <endkey/>
            </page>
            XML);

        // The badge " Élève " and a cell on each side: 9 columns, from column 16
        self::assertSame(
            ['24|' . str_repeat(' ', times: 17) . 'Élève' . str_repeat(' ', times: 18) . '|'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        // "[Maj] é" before it: 16 columns, from column 13
        self::assertSame(
            ['23|' . str_repeat(' ', times: 12) . '[Maj] é  Élève' . str_repeat(' ', times: 14) . '|'],
            ScreenRows::drawn($page->display(new Terminal('192.0.2.1', 'websocket', 'Cv5'))),
        );
    }

    public function testEndKeyIsOnTheRowGiven(): void
    {
        $page = $this->pages->parse('<page type="static"><endkey row="20"/></page>');

        self::assertSame(
            ['20|             Connexion/fin              |'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        self::assertSame(
            ['20|        [Maj] +  Connexion/fin          |'],
            ScreenRows::drawn($page->display(new Terminal('192.0.2.1', 'websocket', 'Cv5'))),
        );
    }

    public function testKeyEndsItsReverseVideoAndUnderline(): void
    {
        $screen = Screen::of(
            $this->pages->parse('<page type="static"><key row="1">A</key></page>')->display(new Terminal()),
        );

        // " A " in reverse video, underlined, then a space in neither
        self::assertTrue(self::cell($screen, row: 1, col: 4)->attributes->isInverse());
        self::assertTrue(self::cell($screen, row: 1, col: 4)->attributes->isUnderlined());
        self::assertFalse(self::cell($screen, row: 1, col: 5)->attributes->isInverse());
        self::assertFalse(self::cell($screen, row: 1, col: 5)->attributes->isUnderlined());
    }

    private static function cell(Screen $screen, int $row, int $col): Cell
    {
        return $screen->cell($row, $col) ?? self::fail("Nothing drawn at row {$row}, column {$col}");
    }
}
