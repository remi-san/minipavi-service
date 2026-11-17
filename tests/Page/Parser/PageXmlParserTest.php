<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page;
use MiniPaviService\Page\Parser\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\StaticPage;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Page\TemplatePage;
use MiniPaviService\Tests\Support\TestMessage;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageXmlParserTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/pages';

    private PageXmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PageXmlParser(Messages::defaults());
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

        self::assertInstanceOf(StaticPage::class, $page);
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

        self::assertSame([' 1|x' . str_repeat(' ', 39) . '|'], $this->drawnRows((new Vdt($before))->append($vdt)));
    }

    public function testDisplaySetsUpTheMinitelFirst(): void
    {
        $page = $this->parse('<page type="static" keyboard="lowercase" echo="off" scroll="off"><text row="1">x</text></page>');

        self::assertStringStartsWith(
            Keyboard::Lowercase->sequence() . LocalEcho::Off->sequence() . Scroll::Off->sequence() . VdtHelper::clearScreen()->toString(),
            $page->display(new Terminal())->toString(),
        );
        $template = $this->parse('<page type="template" keyboard="uppercase" echo="on" scroll="on"><text row="1">{{x}}</text></page>');
        self::assertStringStartsWith(
            Keyboard::Uppercase->sequence() . LocalEcho::On->sequence() . Scroll::On->sequence() . VdtHelper::clearScreen()->toString(),
            $template->display(new Terminal(), ['x' => 'y'])->toString(),
        );
    }

    public function testWithoutSetupTheMinitelIsLeftAsItIs(): void
    {
        $clear = VdtHelper::clearScreen()->toString();
        self::assertSame($clear, substr($this->parse('<page type="static"/>')->display(new Terminal())->toString(), 0, \strlen($clear)));
        // Only what is given
        self::assertStringStartsWith(Scroll::Off->sequence() . VdtHelper::clearScreen()->toString(), $this->parse('<page type="static" scroll="off"/>')->display(new Terminal())->toString());
    }

    public function testPageRedefinesMessagesOfTheService(): void
    {
        $service = Messages::defaults()->with(['welcome' => 'Bonjour {{name}}']);
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
        $page = (new PageXmlParser(Messages::defaults()->with(['welcome' => 'Bonjour'])))->parse('<page type="template"/>', self::FIXTURES);

        self::assertSame('Bonjour', $page->message(TestMessage::Welcome));
    }

    public function testCenteredTextOfEvenWidthHasOneMoreColumnOnTheRight(): void
    {
        // 4 columns: 18 on the left, 18 on the right
        $page = $this->parse('<page type="static"><text row="1" align="center">Jeux</text></page>');

        self::assertSame([' 1|' . str_repeat(' ', 18) . 'Jeux' . str_repeat(' ', 18) . '|'], $this->drawnRows($page->display(new Terminal())));
    }

    public function testEndKeyDependsOnTheTerminal(): void
    {
        $page = $this->parse('<page type="static"><endkey/></page>');

        self::assertSame(['24|             Connexion/fin              |'], $this->drawnRows($page->display(new Terminal())));

        // ESP32 / Minimit adapters: a websocket with a known Minitel, "[Maj] +" on row 23
        $adapter = new Terminal('192.0.2.1', 'websocket', 'Cv5');
        self::assertSame(['23|        [Maj] +  Connexion/fin          |'], $this->drawnRows($page->display($adapter)));
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

        self::assertInstanceOf(TemplatePage::class, $page);
        self::assertSame(
            [
                ' 1|Games (2)                               |',
                ' 3|  1/2 Twin Pine Falls                   |',
                ' 4|  ````                                  |',
                ' 5|  2/2 Alien Wipeout  new                |',
            ],
            $this->drawnRows($page->display(new Terminal(), [
                'title' => 'Games',
                'games' => 2,
                'items' => [['name' => 'Twin Pine Falls', 'new' => false], (object) ['name' => 'Alien Wipeout', 'new' => true]],
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
            $this->drawnRows($page->display(new Terminal(), ['rows' => [['name' => 'A', 'cells' => ['a1', 'a2']], ['name' => 'B', 'cells' => ['b1']]]])),
        );
    }

    public function testTemplateImageCanComeFromTheData(): void
    {
        $page = $this->parse('<page type="template"><image row="3">{{logo}}</image></page>');

        self::assertSame([' 3|##                                      |', ' 4|ok                                      |'], $this->drawnRows($page->display(new Terminal(), ['logo' => 'images/logo.vdt'])));
    }

    public function testTemplateErrorsGiveTheLine(): void
    {
        $page = $this->parse("<page type=\"template\">\n<text row=\"{{row}}\">x</text></page>", 'list.xml');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('list.xml line 2: <text>: Row 30 is off the screen (0 to 24)');

        $page->display(new Terminal(), ['row' => 30]);
    }

    public function testInputCommandReadsTheInputField(): void
    {
        $oneLine = $this->parse('<page type="static"><input row="20" col="5" length="10" lines="1" validwith="envoi suite"/></page>');
        $twoLines = $this->parse('<page type="static"><input/></page>');

        self::assertSame(
            ['name' => 'InputTxt', 'param' => ['x' => 5, 'y' => 20, 'l' => 10, 'char' => '', 'spacechar' => '.', 'prefill' => 'abc', 'cursor' => 'on', 'validwith' => FunctionKey::masks(FunctionKey::Send, FunctionKey::Next)]],
            $oneLine->inputCommand(['abc'])?->toArray()['COMMAND'] ?? null,
        );
        self::assertSame(
            ['name' => 'InputMsg', 'param' => ['x' => 1, 'y' => 13, 'w' => 40, 'h' => 2, 'spacechar' => '.', 'prefill' => [], 'cursor' => 'on', 'validwith' => FunctionKey::Send->mask()]],
            $twoLines->inputCommand()?->toArray()['COMMAND'] ?? null,
        );
        self::assertNull($this->parse('<page type="static"/>')->inputCommand());
    }

    public function testParseFileReadsFilesFromTheFolderOfThePage(): void
    {
        $page = $this->parser->parseFile(self::FIXTURES . '/logo.xml');

        self::assertSame([' 1|##                                      |', ' 2|ok                                      |'], $this->drawnRows($page->display(new Terminal())));
    }

    public function testMissingImageGivesThePageAndLine(): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('f.xml line 2: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt');

        $this->parser->parse("<page type=\"static\">\n<image>images/missing.vdt</image></page>", self::FIXTURES, 'f.xml');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateErrorsFoundWhenParsing(): iterable
    {
        yield 'missing image' => ["<page type=\"template\">\n<image>images/missing.vdt</image></page>", 'f.xml line 2: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt'];
        yield 'missing background, in an <if>' => ["<page type=\"template\">\n<if test=\"x\">\n<background>missing.vdt</background></if></page>", 'f.xml line 3: <background>: Videotex file not found: ' . self::FIXTURES . '/missing.vdt'];
        yield 'missing image, in a <foreach>' => ["<page type=\"template\">\n<foreach items=\"xs\" as=\"x\">\n<image row=\"0\">images/missing.vdt</image></foreach></page>", 'f.xml line 3: <image>: Videotex file not found: ' . self::FIXTURES . '/images/missing.vdt'];
        yield 'unknown color' => ["<page type=\"template\">\n<text color=\"purple\">x</text></page>", 'f.xml line 2: <text>: '];
    }

    /**
     * Elements without placeholders are checked as in a static page
     */
    #[DataProvider('templateErrorsFoundWhenParsing')]
    public function testTemplateElementWithoutPlaceholderIsCheckedWhenParsing(string $xml, string $error): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage($error);

        $this->parser->parse($xml, self::FIXTURES, 'f.xml');
    }

    public function testTemplateRowInAForeachIsCheckedWhenDisplayed(): void
    {
        // Relative to the item: row 0 is the item's first row
        $page = $this->parser->parse('<page type="template"><foreach items="xs" as="x" row="5"><image row="0">images/logo.vdt</image></foreach></page>', self::FIXTURES, 'f.xml');

        self::assertSame([' 5|##                                      |', ' 6|ok                                      |'], $this->drawnRows($page->display(new Terminal(), ['xs' => [1]])));
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
        yield 'no type' => ['<page/>', 'f.xml: <page>: type is missing (type="static" or type="template")'];
        yield 'unknown type' => ['<page type="dynamic"/>', 'f.xml: <page>: type="dynamic" is unknown'];
        yield 'message of no service' => ["<page type=\"static\">\n<message name=\"welcom\">x</message></page>", 'f.xml line 2: <message>: name="welcom" is not a message of the service'];
        yield 'message without name' => ['<page type="static"><message>x</message></page>', 'f.xml line 1: <message>: name is missing'];
        yield 'message twice' => ['<page type="static"><message name="page.last">x</message><message name="page.last">y</message></page>', 'name="page.last" is already in the page'];
        yield 'message in a loop' => ['<page type="template"><foreach items="a" as="b"><message name="page.last">x</message></foreach></page>', '<message>: only at the top level of the page'];
        yield 'unknown keyboard' => ["\n<page type=\"static\" keyboard=\"caps\"/>", 'f.xml line 2: <page>: keyboard="caps" is unknown (keyboard="uppercase" or keyboard="lowercase")'];
        yield 'unknown echo' => ['<page type="static" echo="yes"/>', 'f.xml line 1: <page>: echo="yes" is unknown (echo="on" or echo="off")'];
        yield 'unknown scroll' => ['<page type="static" scroll=""/>', 'f.xml line 1: <page>: scroll="" is unknown (scroll="on" or scroll="off")'];
        yield 'unknown element' => ["<page type=\"static\">\n\n<bogus/></page>", 'f.xml line 3: <bogus>: unknown element'];
        yield 'off the screen' => ['<page type="static"><text row="25">x</text></page>', 'f.xml line 1: <text>: Row 25 is off the screen (0 to 24)'];
        yield 'not a number' => ['<page type="static"><text row="a">x</text></page>', 'f.xml line 1: <text>: row="a" is not a number'];
        yield 'unknown color' => ['<page type="static"><text color="pink">x</text></page>', 'f.xml line 1: <text>: unknown color "pink"'];
        yield 'line too long' => ['<page type="static"><line row="1" col="30" length="20"/></page>', 'goes past column 40'];
        yield 'placeholder in a static page' => ['<page type="static"><text>{{x}}</text></page>', '{{placeholders}} only in a page of type="template"'];
        yield 'foreach in a static page' => ['<page type="static"><foreach items="a" as="b"/></page>', '<foreach>: only in a page of type="template"'];
        yield 'foreach as loop' => ['<page type="template"><foreach items="a" as="loop"/></page>', 'as="loop" must be a variable name, other than loop'];
        yield 'if without test' => ['<page type="template"><if test="!"/></page>', '<if>: test is missing'];
        yield 'two inputs' => ['<page type="static"><input/><input/></page>', '<input>: only one <input> per page'];
        yield 'input in a loop' => ['<page type="template"><foreach items="a" as="b"><input/></foreach></page>', '<input>: only at the top level of the page'];
        yield 'placeholder in an input' => ['<page type="template"><input row="{{r}}"/></page>', '<input>: no {{placeholders}} in <input>'];
        yield 'unknown validwith key' => ['<page type="static"><input validwith="enter"/></page>', 'unknown validwith key "enter"'];
        yield 'endkey in a loop' => ['<page type="template"><foreach items="a" as="b"><endkey/></foreach></page>', '<endkey>: not inside <foreach>'];
        yield 'file outside the folder' => ['<page type="static"><image>../secret.vdt</image></page>', "../secret.vdt: only files from the page's folder or its subfolders"];
        yield 'absolute file' => ['<page type="static"><background>/etc/passwd</background></page>', "/etc/passwd: only files from the page's folder or its subfolders"];
    }

    #[DataProvider('invalidPages')]
    public function testInvalidPageThrowsWithItsLocation(string $xml, string $message): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage($message);

        $this->parser->parse($xml, self::FIXTURES, 'f.xml');
    }

    public function testTemplateFileOutsideTheFolderIsRefusedAtDisplay(): void
    {
        $page = $this->parse('<page type="template"><image>{{file}}</image></page>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage("only files from the page's folder or its subfolders");

        $page->display(new Terminal(), ['file' => 'images/../../secret.vdt']);
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
        $rows = explode("\n", rtrim($vdt->screen()->preview(), "\n"));

        return array_values(array_filter($rows, fn(string $row) => trim(substr($row, 3), ' |') !== ''));
    }
}
