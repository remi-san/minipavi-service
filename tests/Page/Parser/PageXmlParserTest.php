<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Tests\Support\FailingProperties;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Tests\Support\PrivateProperties;
use MiniPaviService\Tests\Support\StarsElements;
use MiniPaviService\Tests\Support\TestMessage;
use MiniPaviService\Vdt\Screen\Cell;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageXmlParserTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/pages';

    private PageXmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PageXmlParser((new MessagesXmlParser())->defaults());
    }

    public function testStaticPageDrawsItsElements(): void
    {
        $page = $this->parse(<<<'XML'
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
                ' 5|  ~~~~~~                                |',
                ' 7|           Envoi                        |',
                ' 9|    ##                                  |',
                '10|    ok                                  |',
            ],
            $this->drawnRows($page->display(new Terminal())),
        );
    }

    public function testDisplayClearsTheScreenFirst(): void
    {
        $before = "\x1F\x45\x41previous screen";
        $vdt = $this->parse('<page type="static"><text row="1">x</text></page>')->display(new Terminal());

        self::assertSame(
            [' 1|x' . str_repeat(' ', times: 39) . '|'],
            $this->drawnRows((new Vdt($before))->append($vdt)),
        );
    }

    public function testDisplaySetsUpTheMinitelFirst(): void
    {
        $page = $this->parse(
            '<page type="static" keyboard="lowercase" echo="off" scroll="off"><text row="1">x</text></page>',
        );

        self::assertStringStartsWith(
            Keyboard::Lowercase->sequence() . LocalEcho::Off->sequence() . Scroll::Off->sequence()
                . Vdt::clearScreen()->toString(),
            $page->display(new Terminal())->toString(),
        );
        $template = $this->parse(
            '<page type="template" keyboard="uppercase" echo="on" scroll="on"><text row="1">{{x}}</text></page>',
        );
        self::assertStringStartsWith(
            Keyboard::Uppercase->sequence() . LocalEcho::On->sequence() . Scroll::On->sequence()
                . Vdt::clearScreen()->toString(),
            $template->display(new Terminal(), ['x' => 'y'])->toString(),
        );
    }

    public function testWithoutSetupTheMinitelIsLeftAsItIs(): void
    {
        $clear = Vdt::clearScreen()->toString();
        self::assertSame($clear, substr(
            $this->parse('<page type="static"/>')->display(new Terminal())->toString(),
            offset: 0,
            length: \strlen($clear),
        ));
        // Only what is given
        self::assertStringStartsWith(
            Scroll::Off->sequence() . Vdt::clearScreen()->toString(),
            $this->parse('<page type="static" scroll="off"/>')->display(new Terminal())->toString(),
        );
    }

    public function testPageRedefinesMessagesOfTheService(): void
    {
        $service = (new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bonjour {{name}}']);
        $page = (new PageXmlParser($service))->parse(<<<'XML'
            <page type="static">
              <message name="welcome">Salut {{name}}</message>
              <message name="endkey">Fin</message>
              <endkey/>
            </page>
            XML, self::FIXTURES);

        self::assertSame('Salut Léa', $page->message(TestMessage::Welcome, ['name' => 'Léa']));
        self::assertSame('Dernière page', $page->message(FrameworkMessage::LastPage));
        // Its elements too
        self::assertStringContainsString(' Fin ', $page->display(new Terminal())->toString());
        self::assertStringNotContainsString('Connexion/fin', $page->display(new Terminal())->toString());
    }

    public function testPageWithoutMessagesHasTheServices(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bonjour'])))->parse('<page type="template"/>', self::FIXTURES);

        self::assertSame('Bonjour', $page->message(TestMessage::Welcome));
    }

    public function testServiceAddsItsElements(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());
        $static = $parser->parse(<<<'XML'
            <page type="static">
              <picture>images/logo.vdt</picture>
              <text row="4">Note</text>
              <stars row="5" count="4"/>
            </page>
            XML, self::FIXTURES);
        $template = $parser->parse(<<<'XML'
            <page type="template">
              <foreach items="notes" as="note" row="5">
                <stars row="0" count="{{note}}"/>
              </foreach>
            </page>
            XML, self::FIXTURES);

        self::assertSame(
            [
                ' 1|##                                      |',
                ' 2|ok                                      |',
                ' 4|Note                                    |',
                ' 5|****                                    |',
            ],
            $this->drawnRows($static->display(new Terminal())),
        );
        self::assertSame(
            [' 5|**                                      |', ' 6|*****                                   |'],
            $this->drawnRows($template->display(new Terminal(), ['notes' => [2, 5]])),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function takenTags(): iterable
    {
        yield 'a framework element' => ['text'];
        yield 'an instruction' => ['foreach'];
        yield 'the input' => ['input'];
        yield 'a condition' => ['if'];
        // Also read by the parser for the page's messages
        yield 'a message' => ['message'];
    }

    #[DataProvider('takenTags')]
    public function testServiceElementTakesNoTagOfTheFormat(string $tag): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("<{$tag}>");

        // When the parser is built (at boot), not when a page is first parsed
        new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements($tag));
    }

    public function testCenteredTextOfEvenWidthHasOneMoreColumnOnTheRight(): void
    {
        // 4 columns: 18 on the left, 18 on the right
        $page = $this->parse('<page type="static"><text row="1" align="center">Jeux</text></page>');

        self::assertSame(
            [' 1|' . str_repeat(' ', times: 18) . 'Jeux' . str_repeat(' ', times: 18) . '|'],
            $this->drawnRows($page->display(new Terminal())),
        );
    }

    public function testEndKeyDependsOnTheTerminal(): void
    {
        $page = $this->parse('<page type="static"><endkey/></page>');

        self::assertSame(
            ['24|             Connexion/fin              |'],
            $this->drawnRows($page->display(new Terminal())),
        );

        // ESP32 / Minimit adapters: a websocket with a known Minitel, "[Maj] +" on row 23
        $adapter = new Terminal('192.0.2.1', 'websocket', 'Cv5');
        self::assertSame(['23|        [Maj] +  Connexion/fin          |'], $this->drawnRows($page->display($adapter)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function elementsWithoutPosition(): iterable
    {
        yield 'text' => ['<text>Top</text>', [' 1|Top' . str_repeat(' ', times: 37) . '|']];
        yield 'line' => ['<line length="3"/>', [' 1|```' . str_repeat(' ', times: 37) . '|']];
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
        $page = $this->parse("<page type=\"static\">{$element}</page>");

        self::assertSame($rows, $this->drawnRows($page->display(new Terminal())));
    }

    public function testElementsWithoutColorHaveTheirOwn(): void
    {
        $page = $this->parse(<<<'XML'
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
        $page = $this->parse(<<<'XML'
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
        $page = $this->parse(
            "<page type=\"static\"><text row=\"2\" col=\"5\" bg=\"blue\" {$attributes}>ab</text></page>",
        );
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(
            [' 2|    ab' . str_repeat(' ', times: 34) . '|'],
            $this->drawnRows($page->display(new Terminal())),
        );
        self::assertNull($screen->cell(row: 2, col: 40));
    }

    public function testFilledTextPaintsTheRestOfTheRow(): void
    {
        $page = $this->parse('<page type="static"><text row="2" col="5" bg="blue" fill="yes">ab</text></page>');
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(
            [' 2|    ab' . str_repeat(' ', times: 34) . '|'],
            $this->drawnRows($page->display(new Terminal())),
        );
        self::assertSame(Color::Blue, self::cell($screen, row: 2, col: 7)->attributes->background());
        self::assertSame(Color::Blue, self::cell($screen, row: 2, col: 40)->attributes->background());
        self::assertNull($screen->cell(row: 2, col: 4));
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
        yield 'filled, double width' => [
            '<text row="1" align="center" size="double-width" bg="blue" fill="yes">Jeux</text>',
            ' 1|' . str_repeat(' ', times: 16) . 'JJeeuuxx' . str_repeat(' ', times: 16) . '|',
        ];
    }

    #[DataProvider('centeredTexts')]
    public function testCenteredTextHasAsManyColumnsOnEachSide(string $text, string $row): void
    {
        $page = $this->parse("<page type=\"static\">{$text}</page>");

        self::assertSame($row, $this->drawnRows($page->display(new Terminal()))[0]);
    }

    public function testFilledCenteredTextPaintsTheWholeRow(): void
    {
        $page = $this->parse(
            '<page type="static"><text row="1" align="center" bg="blue" fill="yes">Jeux</text></page>',
        );
        $screen = Screen::of($page->display(new Terminal()));

        self::assertSame(Color::Blue, self::cell($screen, row: 1, col: 1)->attributes->background());
        self::assertSame(Color::Blue, self::cell($screen, row: 1, col: 40)->attributes->background());
    }

    public function testEndKeyIsCenteredByCharacters(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="static">
              <message name="endkey">Élève</message>
              <message name="endkey.shift">[Maj] é</message>
              <endkey/>
            </page>
            XML);

        // The badge " Élève " and a cell on each side: 9 columns, from column 16
        self::assertSame(
            ['24|' . str_repeat(' ', times: 17) . 'Élève' . str_repeat(' ', times: 18) . '|'],
            $this->drawnRows($page->display(new Terminal())),
        );
        // "[Maj] é" before it: 16 columns, from column 13
        self::assertSame(
            ['23|' . str_repeat(' ', times: 12) . '[Maj] é  Élève' . str_repeat(' ', times: 14) . '|'],
            $this->drawnRows($page->display(new Terminal('192.0.2.1', 'websocket', 'Cv5'))),
        );
    }

    public function testEndKeyIsOnTheRowGiven(): void
    {
        $page = $this->parse('<page type="static"><endkey row="20"/></page>');

        self::assertSame(
            ['20|             Connexion/fin              |'],
            $this->drawnRows($page->display(new Terminal())),
        );
        self::assertSame(
            ['20|        [Maj] +  Connexion/fin          |'],
            $this->drawnRows($page->display(new Terminal('192.0.2.1', 'websocket', 'Cv5'))),
        );
    }

    public function testKeyEndsItsReverseVideoAndUnderline(): void
    {
        $screen = Screen::of($this->parse('<page type="static"><key row="1">A</key></page>')->display(new Terminal()));

        // " A " in reverse video, underlined, then a space in neither
        self::assertTrue(self::cell($screen, row: 1, col: 4)->attributes->isInverse());
        self::assertTrue(self::cell($screen, row: 1, col: 4)->attributes->isUnderlined());
        self::assertFalse(self::cell($screen, row: 1, col: 5)->attributes->isInverse());
        self::assertFalse(self::cell($screen, row: 1, col: 5)->attributes->isUnderlined());
    }

    public function testKeyCanBeDrawnForEachItem(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="template">
              <foreach items="keys" as="key" row="3"><key col="2">{{key}}</key></foreach>
            </page>
            XML);

        self::assertSame(
            [' 3|   Envoi' . str_repeat(' ', times: 32) . '|', ' 4|   Suite' . str_repeat(' ', times: 32) . '|'],
            $this->drawnRows($page->display(new Terminal(), ['keys' => ['Envoi', 'Suite']])),
        );
    }

    public function testForeachStartsAtTheFirstRowOrItsItemsAndGoesDownARowPerItem(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="template">
              <foreach items="rows" as="row" step="2">
                <text>{{row.name}}</text>
                <foreach items="row.cells" as="cell"><text col="5">{{cell}}</text></foreach>
              </foreach>
            </page>
            XML);

        // Without row: row 1 of the page, the first row of the item inside a <foreach>
        self::assertSame(
            [
                ' 1|A   a1                                  |',
                ' 2|    a2                                  |',
                ' 3|B   b1                                  |',
            ],
            $this->drawnRows($page->display(new Terminal(), [
                'rows' => [['name' => 'A', 'cells' => ['a1', 'a2']], ['name' => 'B', 'cells' => ['b1']]],
            ])),
        );
    }

    public function testTemplateReadsEachImageOnce(): void
    {
        $files = new MemoryFiles([
            '/pages/home.xml' => '<page type="template"><image row="{{row}}">images/logo.vdt</image></page>',
            '/pages/images/logo.vdt' => 'ok',
        ]);
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults(), files: $files))->parseFile('/pages/home.xml');

        $page->display(new Terminal(), ['row' => 3]);
        $vdt = $page->display(new Terminal(), ['row' => 5]);

        self::assertSame([' 5|ok                                      |'], $this->drawnRows($vdt));
        self::assertSame(['/pages/home.xml', '/pages/images/logo.vdt'], $files->read);
    }

    public function testForeachItemNameIsTrimmed(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="template">
              <foreach items="games" as=" game " row="2">
                <text>{{game}}</text>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [' 2|Chess                                   |', ' 3|Tetris                                  |'],
            $this->drawnRows($page->display(new Terminal(), ['games' => ['Chess', 'Tetris']])),
        );
    }

    public function testTemplateRepeatsAndTestsItsContent(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="template">
              <text row="1">{{title}} ({{games}})</text>
              <foreach items="items" as="game" row="3" step="2">
                <text col="3">{{loop.index}}/{{loop.count}} {{game.name}}</text>
                <if test="!loop.last"><line row="1" col="3" length="4"/></if>
                <if test="game.new"><text col="22">new</text></if>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [
                ' 1|Games (2)                               |',
                ' 3|  1/2 Harbour Pirates                   |',
                ' 4|  ````                                  |',
                ' 5|  2/2 Rocket Rescue  new                |',
            ],
            $this->drawnRows($page->display(new Terminal(), [
                'title' => 'Games',
                'games' => 2,
                'items' => [
                    ['name' => 'Harbour Pirates', 'new' => false],
                    (object) ['name' => 'Rocket Rescue', 'new' => true],
                ],
            ])),
        );
    }

    public function testNestedForeachRowsAreRelativeToTheEnclosingItem(): void
    {
        $page = $this->parse(<<<'XML'
            <page type="template">
              <foreach items="rows" as="row" row="2" step="3">
                <text>{{row.name}}</text>
                <foreach items="row.cells" as="cell" row="1">
                  <text col="3">{{cell}}</text>
                </foreach>
              </foreach>
            </page>
            XML);

        self::assertSame(
            [
                ' 2|A                                       |',
                ' 3|  a1                                    |',
                ' 4|  a2                                    |',
                ' 5|B                                       |',
                ' 6|  b1                                    |',
            ],
            $this->drawnRows($page->display(new Terminal(), [
                'rows' => [['name' => 'A', 'cells' => ['a1', 'a2']], ['name' => 'B', 'cells' => ['b1']]],
            ])),
        );
    }

    public function testReadsThePageAndItsVideotexFilesThroughItsFileReader(): void
    {
        $files = new MemoryFiles([
            '/pages/home.xml' => '<page type="static"><image row="3" col="2">images/logo.vdt</image></page>',
            '/pages/images/logo.vdt' => 'ok',
        ]);
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), files: $files);

        $page = $parser->parseFile('/pages/home.xml');

        self::assertSame(
            [' 3| ok                                     |'],
            $this->drawnRows($page->display(new Terminal())),
        );
        self::assertSame(['/pages/home.xml', '/pages/images/logo.vdt'], $files->read);
    }

    public function testPageItsFileReaderDoesntHaveIsNotFound(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), files: new MemoryFiles([]));

        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('/pages/home.xml: file not found');

        $parser->parseFile('/pages/home.xml');
    }

    public function testTemplateImageCanComeFromTheData(): void
    {
        $page = $this->parse('<page type="template"><image row="3">{{logo}}</image></page>');

        self::assertSame(
            [' 3|##                                      |', ' 4|ok                                      |'],
            $this->drawnRows($page->display(new Terminal(), ['logo' => 'images/logo.vdt'])),
        );
    }

    public function testTemplateErrorsGiveTheLine(): void
    {
        $page = $this->parse("<page type=\"template\">\n<text row=\"{{row}}\">x</text></page>", name: 'list.xml');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('list.xml line 2: <text>: Row 30 is off the screen (0 to 24)');

        $page->display(new Terminal(), ['row' => 30]);
    }

    public function testPrivatePropertyIsAnUnknownVariableOfTheTemplate(): void
    {
        $page = $this->parse("<page type=\"template\">\n<text row=\"1\">{{user.city}}</text></page>", name: 'user.xml');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('user.xml line 2: <text>: unknown variable {{user.city}}: no "city" in '
        . PrivateProperties::class);

        $page->display(new Terminal(), ['user' => new PrivateProperties()]);
    }

    public function testInvalidValueOfAServiceElementGivesTheLine(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());

        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('stars.xml line 2: <stars>: Row 30 is off the screen (0 to 24)');

        $parser->parse("<page type=\"static\">\n<stars row=\"30\"/></page>", self::FIXTURES, name: 'stars.xml');
    }

    public function testBugOfAServiceElementIsntAnErrorOfThePage(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());

        // Not a PageXmlException: nothing to fix in the page
        $this->expectExceptionObject(new \InvalidArgumentException('a bug building <broken>'));

        $parser->parse('<page type="static"><broken/></page>', self::FIXTURES);
    }

    public function testBugOfAServiceElementIsntAnErrorOfTheTemplate(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements()))->parse(
            '<page type="template"><broken row="{{row}}"/></page>',
            self::FIXTURES,
        );

        // Not a TemplateException: nothing to fix in the page
        $this->expectExceptionObject(new \InvalidArgumentException('a bug building <broken>'));

        $page->display(new Terminal(), ['row' => 3]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templatesReadingAVariable(): iterable
    {
        yield 'a placeholder' => ['<text row="1">{{user.name}}</text>'];
        yield 'a condition' => ['<if test="user.name"><text row="1">x</text></if>'];
        yield 'a loop' => ['<foreach items="user.name" as="n" row="1"><text row="0">x</text></foreach>'];
    }

    #[DataProvider('templatesReadingAVariable')]
    public function testBugOfAVariableIsntAnErrorOfTheTemplate(string $xml): void
    {
        $page = $this->parse("<page type=\"template\">{$xml}</page>");

        // Not a TemplateException: the variable's own code failed
        $this->expectExceptionObject(new \InvalidArgumentException('a bug reading name'));

        $page->display(new Terminal(), ['user' => new FailingProperties()]);
    }

    public function testInputCommandReadsTheInputField(): void
    {
        $oneLine = $this->parse(
            '<page type="static"><input row="20" col="5" length="10" lines="1" validwith="envoi suite"/></page>',
        );
        $twoLines = $this->parse('<page type="static"><input/></page>');

        self::assertEquals(
            new InputCommand(
                new InputField(new Span(new Position(20, 5), 10), validWith: [FunctionKey::Send, FunctionKey::Next]),
                ['abc'],
            ),
            $oneLine->inputCommand(['abc']),
        );
        // By default: two lines from row 13, the whole width, validated with Envoi
        self::assertEquals(
            new InputCommand(
                new InputField(new Span(new Position(13, 1), 40), lines: 2, validWith: [FunctionKey::Send]),
            ),
            $twoLines->inputCommand(),
        );
        self::assertNull($this->parse('<page type="static"/>')->inputCommand());
    }

    public function testParseFileReadsFilesFromTheFolderOfThePage(): void
    {
        $page = $this->parser->parseFile(self::FIXTURES . '/logo.xml');

        self::assertSame(
            [' 1|##                                      |', ' 2|ok                                      |'],
            $this->drawnRows($page->display(new Terminal())),
        );
    }

    public function testMissingImageGivesThePageAndLine(): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage(
            'f.xml line 2: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt',
        );

        $this->parser->parse(
            "<page type=\"static\">\n<image>images/missing.vdt</image></page>",
            self::FIXTURES,
            name: 'f.xml',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templateErrorsFoundWhenParsing(): iterable
    {
        yield 'missing image' => [
            "<page type=\"template\">\n<image>images/missing.vdt</image></page>",
            'f.xml line 2: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt',
        ];
        yield 'missing background, in an <if>' => [
            "<page type=\"template\">\n<if test=\"x\">\n<background>missing.vdt</background></if></page>",
            'f.xml line 3: <background>: Videotex file not found: ' . self::FIXTURES . '/missing.vdt',
        ];
        yield 'missing image, in a <foreach>' => [
            "<page type=\"template\">\n<foreach items=\"xs\" as=\"x\">\n<image row=\"0\">images/missing.vdt</image></foreach></page>",
            'f.xml line 3: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt',
        ];
        yield 'unknown color' => [
            "<page type=\"template\">\n<text color=\"purple\">x</text></page>",
            'f.xml line 2: <text>: ',
        ];
    }

    /**
     * Elements without placeholders are checked as in a static page
     */
    #[DataProvider('templateErrorsFoundWhenParsing')]
    public function testTemplateElementWithoutPlaceholderIsCheckedWhenParsing(string $xml, string $error): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage($error);

        $this->parser->parse($xml, self::FIXTURES, name: 'f.xml');
    }

    public function testTemplateRowInAForeachIsCheckedWhenDisplayed(): void
    {
        // Relative to the item: row 0 is the item's first row
        $page = $this->parser->parse(
            '<page type="template"><foreach items="xs" as="x" row="5"><image row="0">images/logo.vdt</image></foreach></page>',
            self::FIXTURES,
            name: 'f.xml',
        );

        self::assertSame(
            [' 5|##                                      |', ' 6|ok                                      |'],
            $this->drawnRows($page->display(new Terminal(), ['xs' => [1]])),
        );
    }

    public function testParseFileThrowsWhenTheFileIsMissing(): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('missing.xml: file not found');

        $this->parser->parseFile(self::FIXTURES . '/missing.xml');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'invalid XML' => ['<page type="static"><text>', 'f.xml line 1: Premature end of data in tag text line 1'];
        yield 'other root' => ['<pages/>', 'f.xml: root element must be <page>, not <pages>'];
        // With the line of <page>, as its other attributes
        yield 'no type' => [
            "<!-- a page -->\n<page/>",
            'f.xml line 2: <page>: type is missing (type="static" or type="template")',
        ];
        yield 'unknown type' => [
            '<page type="dynamic"/>',
            'f.xml line 1: <page>: type="dynamic" is unknown (type="static" or type="template")',
        ];
        yield 'message of no service' => [
            "<page type=\"static\">\n<message name=\"welcom\">x</message></page>",
            'f.xml line 2: <message>: name="welcom" is not a message of the service',
        ];
        yield 'message without name' => [
            '<page type="static"><message>x</message></page>',
            'f.xml line 1: <message>: name is missing',
        ];
        yield 'message twice' => [
            '<page type="static"><message name="page.last">x</message><message name="page.last">y</message></page>',
            'name="page.last" is already in the page',
        ];
        // The first error in the file: the second "page.last", before the unknown message
        yield 'first message error in the file' => [
            "<page type=\"static\">\n<message name=\"page.last\">x</message>\n<message name=\"page.last\">y</message>\n<message name=\"zzz\">z</message></page>",
            'f.xml line 3: <message>: name="page.last" is already in the page',
        ];
        yield 'message in a loop' => [
            '<page type="template"><foreach items="a" as="b"><message name="page.last">x</message></foreach></page>',
            '<message>: only at the top level of the page',
        ];
        yield 'unknown keyboard' => [
            "\n<page type=\"static\" keyboard=\"caps\"/>",
            'f.xml line 2: <page>: keyboard="caps" is unknown (keyboard="uppercase" or keyboard="lowercase")',
        ];
        yield 'unknown echo' => [
            '<page type="static" echo="yes"/>',
            'f.xml line 1: <page>: echo="yes" is unknown (echo="on" or echo="off")',
        ];
        yield 'unknown scroll' => [
            '<page type="static" scroll=""/>',
            'f.xml line 1: <page>: scroll="" is unknown (scroll="on" or scroll="off")',
        ];
        yield 'unknown element' => [
            "<page type=\"static\">\n\n<bogus/></page>",
            'f.xml line 3: <bogus>: unknown element',
        ];
        yield 'off the screen' => [
            '<page type="static"><text row="25">x</text></page>',
            'f.xml line 1: <text>: Row 25 is off the screen (0 to 24)',
        ];
        yield 'not a number' => [
            '<page type="static"><text row="a">x</text></page>',
            'f.xml line 1: <text>: row="a" is not a number',
        ];
        yield 'unknown color' => [
            '<page type="static"><text color="pink">x</text></page>',
            'f.xml line 1: <text>: unknown color "pink"',
        ];
        yield 'line too long' => [
            '<page type="static"><line row="1" col="30" length="20"/></page>',
            'goes past column 40',
        ];
        yield 'placeholder in a static page' => [
            '<page type="static"><text>{{x}}</text></page>',
            '{{placeholders}} only in a page of type="template"',
        ];
        yield 'foreach in a static page' => [
            '<page type="static"><foreach items="a" as="b"/></page>',
            '<foreach>: only in a page of type="template"',
        ];
        yield 'foreach as loop' => [
            '<page type="template"><foreach items="a" as="loop"/></page>',
            'as="loop" must be a variable name, other than loop',
        ];
        yield 'foreach as starting with a digit' => [
            '<page type="template"><foreach items="a" as="1a"/></page>',
            'as="1a" must be a variable name, other than loop',
        ];
        yield 'foreach as with a dash' => [
            '<page type="template"><foreach items="a" as="a-b"/></page>',
            'as="a-b" must be a variable name, other than loop',
        ];
        yield 'if without test' => ['<page type="template"><if test="!"/></page>', '<if>: test is missing'];
        // Found while parsing, with the file and line, not when displayed
        yield 'if test with a space' => [
            '<page type="template"><if test="! x"/></page>',
            'f.xml line 1: <if>: test="! x" must be a variable, or !variable',
        ];
        yield 'if test negated twice' => [
            '<page type="template"><if test="!!x"/></page>',
            'f.xml line 1: <if>: test="!!x" must be a variable, or !variable',
        ];
        yield 'two inputs' => ['<page type="static"><input/><input/></page>', '<input>: only one <input> per page'];
        yield 'input in a loop' => [
            '<page type="template"><foreach items="a" as="b"><input/></foreach></page>',
            '<input>: only at the top level of the page',
        ];
        yield 'placeholder in an input' => [
            '<page type="template"><input row="{{r}}"/></page>',
            '<input>: no {{placeholders}} in <input>',
        ];
        yield 'unknown validwith key' => [
            '<page type="static"><input validwith="enter"/></page>',
            'unknown validwith key "enter"',
        ];
        yield 'endkey in a loop' => [
            '<page type="template"><foreach items="a" as="b"><endkey/></foreach></page>',
            '<endkey>: not inside <foreach>',
        ];
        yield 'file outside the folder' => [
            '<page type="static"><image>../secret.vdt</image></page>',
            "../secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'absolute file' => [
            '<page type="static"><background>/etc/passwd</background></page>',
            "/etc/passwd: only files from the page's folder or its subfolders",
        ];
        yield 'file outside the folder with backslashes' => [
            '<page type="static"><image>images\\..\\..\\secret.vdt</image></page>',
            "images\\..\\..\\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'absolute file with a backslash' => [
            '<page type="static"><background>\\secret.vdt</background></page>',
            "\\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file on a Windows drive' => [
            '<page type="static"><image>C:/secret.vdt</image></page>',
            "C:/secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file on a Windows drive with a backslash' => [
            '<page type="static"><image>c:\\secret.vdt</image></page>',
            "c:\\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file relative to a Windows drive' => [
            '<page type="static"><image>C:secret.vdt</image></page>',
            "C:secret.vdt: only files from the page's folder or its subfolders",
        ];
    }

    #[DataProvider('invalidPages')]
    public function testInvalidPageThrowsWithItsLocation(string $xml, string $message): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage($message);

        $this->parser->parse($xml, self::FIXTURES, name: 'f.xml');
    }

    public function testTemplateFileOutsideTheFolderIsRefusedAtDisplay(): void
    {
        $page = $this->parse('<page type="template"><image>{{file}}</image></page>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage("only files from the page's folder or its subfolders");

        $page->display(new Terminal(), ['file' => 'images/../../secret.vdt']);
    }

    private static function cell(Screen $screen, int $row, int $col): Cell
    {
        return $screen->cell($row, $col) ?? self::fail("Nothing drawn at row {$row}, column {$col}");
    }

    private function parse(string $xml, string $name = 'page'): Page
    {
        return $this->parser->parse($xml, self::FIXTURES, $name);
    }

    /**
     * Rows of the screen drawn by Videotex content that are not empty, as in Screen::preview()
     *
     * @return list<string>
     */
    private function drawnRows(Vdt $vdt): array
    {
        $rows = explode("\n", rtrim(Screen::of($vdt)->preview(), characters: "\n"));

        return array_values(array_filter(
            $rows,
            static fn(string $row) => trim(substr($row, offset: 3), characters: ' |') !== '',
        ));
    }
}
