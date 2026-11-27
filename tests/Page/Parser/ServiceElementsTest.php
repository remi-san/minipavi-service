<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\ScreenRows;
use MiniPaviService\Tests\Support\StarsElements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The elements a service adds to the page format, and their errors
 */
final class ServiceElementsTest extends TestCase
{
    public function testServiceAddsItsElements(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());
        $static = $parser->parse(<<<'XML'
            <page type="static">
              <picture>images/logo.vdt</picture>
              <text row="4">Note</text>
              <stars row="5" count="4"/>
            </page>
            XML, ParsedPages::FOLDER);
        $template = $parser->parse(<<<'XML'
            <page type="template">
              <foreach items="notes" as="note" row="5">
                <stars row="0" count="{{note}}"/>
              </foreach>
            </page>
            XML, ParsedPages::FOLDER);

        self::assertSame(
            [
                ' 1|##                                      |',
                ' 2|ok                                      |',
                ' 4|Note                                    |',
                ' 5|****                                    |',
            ],
            ScreenRows::drawn($static->display(new Terminal())),
        );
        self::assertSame(
            [' 5|**                                      |', ' 6|*****                                   |'],
            ScreenRows::drawn($template->display(new Terminal(), ['notes' => [2, 5]])),
        );
    }

    public function testServiceElementDecidesWhereItIsDrawnInAForeach(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());
        $page = $parser->parse(<<<'XML'
            <page type="template">
              <foreach items="notes" as="note" row="5">
                <picture>images/logo.vdt</picture>
                <stars row="0" count="{{note}}"/>
              </foreach>
            </page>
            XML, ParsedPages::FOLDER);

        // <stars> at the item's row, <picture> at its own place
        self::assertSame(
            [
                ' 1|##                                      |',
                ' 2|ok                                      |',
                ' 5|***                                     |',
            ],
            ScreenRows::drawn($page->display(new Terminal(), ['notes' => [3]])),
        );
    }

    public function testServiceElementWithoutPlaceholdersInAForeachIsBuiltWhenThePageIsRead(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());
        // Built when read, before its items' rows are known: what it places by row can't fail
        $page = $parser->parse(
            '<page type="template"><foreach items="notes" as="note" row="3"><stars row="1" count="2"/></foreach></page>',
            ParsedPages::FOLDER,
        );

        self::assertSame(
            [' 4|**                                      |', ' 5|**                                      |'],
            ScreenRows::drawn($page->display(new Terminal(), ['notes' => [1, 2]])),
        );
    }

    public function testServiceElementCanBeOnThePageOnly(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());
        $page = $parser->parse('<page type="template"><if test="signed"><signature/></if></page>', ParsedPages::FOLDER);

        self::assertSame(
            ['24|Signed                                  |'],
            ScreenRows::drawn($page->display(new Terminal(), ['signed' => true])),
        );
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('f.xml line 3: <signature>: not inside <foreach>');

        // Refused when the page is read, even deep in the <foreach>
        $parser->parse(
            "<page type=\"template\">\n<foreach items=\"a\" as=\"b\"><if test=\"b\">\n<signature/></if></foreach></page>",
            ParsedPages::FOLDER,
            name: 'f.xml',
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function bracesSplitAcrossValues(): iterable
    {
        yield 'between an attribute and the text' => ['<stars count="2" note="{{a">}}</stars>'];
        yield 'between two attributes' => ['<stars count="2" note="{{a" end="}}"/>'];
        yield 'with a name split' => ['<stars count="2" note="{{a">b}}</stars>'];
    }

    /**
     * A placeholder is in one value: {{ in a value and }} in another make none
     */
    #[DataProvider('bracesSplitAcrossValues')]
    public function testPlaceholderIsNotSplitAcrossValues(string $stars): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());

        $page = $parser->parse("<page type=\"static\">{$stars}</page>", ParsedPages::FOLDER);

        self::assertSame(
            [' 1|**                                      |'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
    }

    public function testInvalidValueOfAServiceElementGivesTheLine(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());

        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('stars.xml line 2: <stars>: Row 30 is off the screen (0 to 24)');

        $parser->parse("<page type=\"static\">\n<stars row=\"30\"/></page>", ParsedPages::FOLDER, name: 'stars.xml');
    }

    public function testBugOfAServiceElementIsntAnErrorOfThePage(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements());

        // Not a PageXmlException: nothing to fix in the page
        $this->expectExceptionObject(new \InvalidArgumentException('a bug building <broken>'));

        $parser->parse('<page type="static"><broken/></page>', ParsedPages::FOLDER);
    }

    public function testBugOfAServiceElementIsntAnErrorOfTheTemplate(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults(), new StarsElements()))->parse(
            '<page type="template"><broken row="{{row}}"/></page>',
            ParsedPages::FOLDER,
        );

        // Not a TemplateException: nothing to fix in the page
        $this->expectExceptionObject(new \InvalidArgumentException('a bug building <broken>'));

        $page->display(new Terminal(), ['row' => 3]);
    }
}
