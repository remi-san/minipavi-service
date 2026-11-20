<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Color;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Page\Display\Size;
use MiniPaviService\Page\Parser\ElementFactory;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\Parser\StandardElements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * schema/page.xsd agrees with PageXmlParser: pages the parser reads are valid, pages it
 * refuses are not (but for the rules the schema can't express), and the values listed
 * by the schema are those of the parser.
 */
final class PageXsdTest extends TestCase
{
    private const SCHEMA = __DIR__ . '/../../../schema/page.xsd';

    private const FIXTURES = __DIR__ . '/../../fixtures/pages';

    // Invalid pages of PageXmlParserTest that XML Schema 1.0 can't tell from valid ones
    // (listed in the comment of page.xsd): only the parser refuses them
    private const PARSER_ONLY = [
        'line too long',
        'placeholder in a static page',
        'foreach in a static page',
        'foreach as loop',
        'two inputs',
        'input past the last row',
        'message of no service',
    ];

    private const SHOULD_BE_REFUSED = 'the schema should refuse it';

    private const REFUSED_BY_THE_SCHEMA_TOO = 'the schema refuses it: remove it from PARSER_ONLY';

    /**
     * @return iterable<string, array{string}>
     */
    public static function validPages(): iterable
    {
        yield 'empty static page' => ['<page type="static"/>'];
        yield 'every element and attribute' => [<<<'XML'
            <page type="static">
              <!-- a comment -->
              <background>images/logo.vdt</background>
              <image row="2" col="3">images/logo.vdt</image>
              <text row="0" col="1" align="center" color="yellow" bg="blue" size="double-height" fill="yes">Title</text>
              <text size="double-width" fill="no" align="left">x</text>
              <text size="double">x</text>
              <text size="normal">x</text>
              <line row="5" col="2" length="10" char="top" color="cyan"/>
              <line char="middle"/>
              <line char="bottom"/>
              <line char="*"/>
              <key row="24" col="30" color="white" bg="blue">Envoi</key>
              <endkey row="23" color="red"/>
              <input row="20" col="5" length="10" lines="1" validwith="envoi suite retour sommaire repetition guide annulation correction"/>
            </page>
            XML];
        yield 'template' => [<<<'XML'
            <page type="template">
              <text row="1">{{title}} ({{ games }})</text>
              <if test="subtitle"><text row="2">{{subtitle}}</text><endkey/></if>
              <foreach items="items" as="game" row="3" step="2">
                <text col="3" color="{{game.color}}">{{loop.index}}/{{loop.count}} {{game.name}}</text>
                <image row="{{game.row}}" col="{{game.col}}">{{game.image}}</image>
                <if test="!loop.last"><line row="1" col="3" length="4"/></if>
                <foreach items="game.cells" as="cell" step="1"><text>{{cell}}</text></foreach>
              </foreach>
              <input/>
            </page>
            XML];
        // Spaces collapsed by the schema, trimmed by the parser
        yield 'values with spaces around them' => [<<<'XML'
            <page type="static">
              <text row=" 4 " col=" 3 ">Left</text>
              <image row="9" col="5">
                images/logo.vdt
              </image>
              <input row=" 20 " col=" 5 " length=" 10 " lines=" 1 " validwith="  envoi   suite  "/>
            </page>
            XML];
        yield 'test with spaces around it' => [
            '<page type="template"><if test=" !hidden "><text row="2">x</text></if></page>',
        ];
        yield 'messages' => [
            '<page type="static"><message name="page.last">Fin de l\'article</message><message name="endkey">Fin</message></page>',
        ];
        yield 'Minitel setup' => ['<page type="static" keyboard="lowercase" echo="off" scroll="on"/>'];
        yield 'schema location' => [<<<'XML'
            <page type="static" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:noNamespaceSchemaLocation="../../vendor/remi-san/minipavi-service/schema/page.xsd"/>
            XML];
    }

    #[DataProvider('validPages')]
    public function testPageReadByTheParserIsValid(string $xml): void
    {
        (new PageXmlParser((new MessagesXmlParser())->defaults()))->parse($xml, self::FIXTURES);

        self::assertSame([], self::errors($xml));
    }

    public function testFixturePagesAreValid(): void
    {
        $files = glob(self::FIXTURES . '/*.xml');
        $files = $files === false ? [] : $files;
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            self::assertSame([], self::errors((string) file_get_contents($file)), $file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filesInThePageFolder(): iterable
    {
        yield 'in a subfolder' => ['images/logo.vdt'];
        yield 'with Windows separators' => ['images\logo.vdt'];
        yield 'one letter' => ['a'];
        yield 'colon after the first letter' => ['ab:c.vdt'];
        yield 'colon in a subfolder' => ['images/C:logo.vdt'];
        yield 'from the current folder' => ['./logo.vdt'];
        yield 'hidden' => ['.logo.vdt'];
        yield 'starting with two dots' => ['..logo.vdt'];
    }

    /**
     * A file the parser takes from the page's folder: the schema takes it too
     */
    #[DataProvider('filesInThePageFolder')]
    public function testFileInThePageFolderIsValid(string $file): void
    {
        self::assertSame('dir/' . $file, (new PageFiles('dir'))->path($file));
        self::assertSame([], self::errors("<page type=\"static\"><image>{$file}</image></page>"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesRefusedBySchema(): iterable
    {
        foreach (PageXmlParserTest::invalidPages() as $name => [$xml]) {
            if ($name === 'invalid XML' || \in_array($name, self::PARSER_ONLY, strict: true)) {
                continue;
            }

            yield $name => [$xml];
        }
        yield 'unknown attribute' => ['<page type="static"><text bold="yes">x</text></page>'];
        yield 'col 0' => ['<page type="static"><text col="0">x</text></page>'];
        yield 'signed number' => ['<page type="static"><text row="+1">x</text></page>'];
        yield 'input in an if' => ['<page type="template"><if test="a"><input/></if></page>'];
        yield 'endkey in an if in a loop' => [
            '<page type="template"><foreach items="a" as="b"><if test="c"><endkey/></if></foreach></page>',
        ];
        yield 'foreach without items' => ['<page type="template"><foreach as="b"/></page>'];
        yield 'line of two characters' => ['<page type="static"><line char="=="/></page>'];
        yield 'empty validwith' => ['<page type="static"><input validwith=""/></page>'];
    }

    #[DataProvider('pagesRefusedBySchema')]
    public function testPageRefusedBySchemaIsInvalid(string $xml): void
    {
        self::assertNotSame([], self::errors($xml), self::SHOULD_BE_REFUSED);
    }

    // One test over the list (not a data provider), so that it still passes once the schema
    // refuses every page and PARSER_ONLY is empty
    public function testPagesOnlyTheParserRefusesAreValid(): void
    {
        $refused = [];
        foreach (PageXmlParserTest::invalidPages() as $name => [$xml]) {
            if (!\in_array($name, self::PARSER_ONLY, strict: true) || self::errors($xml) === []) {
                continue;
            }

            $refused[] = $name;
        }

        self::assertSame([], $refused, self::REFUSED_BY_THE_SCHEMA_TOO);
    }

    public function testParserOnlyPagesAreInvalidPagesOfTheParser(): void
    {
        $names = array_keys(iterator_to_array(PageXmlParserTest::invalidPages()));

        self::assertSame([], array_values(array_diff(self::PARSER_ONLY, $names)));
        // Not well-formed: the schema can't be checked against it
        self::assertNotContains('invalid XML', self::PARSER_ONLY);
    }

    public function testValuesAreThoseOfTheParser(): void
    {
        self::assertSame(array_column(Color::cases(), 'value'), self::enumeration('color'));
        self::assertSame(array_column(Size::cases(), 'value'), self::enumeration('size'));
        self::assertSame(array_column(Keyboard::cases(), 'value'), self::enumeration('keyboard'));
        self::assertSame(array_column(LocalEcho::cases(), 'value'), self::enumeration('onOff'));
        self::assertSame(array_column(Scroll::cases(), 'value'), self::enumeration('onOff'));
        $keys = array_map(static fn(FunctionKey $key) => $key->xmlName(), FunctionKey::cases());
        sort($keys);
        $schemaKeys = self::enumeration('functionKey');
        sort($schemaKeys);
        self::assertSame($keys, $schemaKeys);
    }

    public function testElementsAreThoseOfTheParser(): void
    {
        $xpath = self::schema();
        $names = [];
        $nodes = $xpath->query(
            '//xs:group[@name="displayElement"]//xs:element/@name | //xs:element[@name="page"]//xs:element/@name',
        );
        foreach ($nodes === false ? [] : $nodes as $name) {
            $names[] = $name->nodeValue;
        }
        $expected = [
            ...ElementFactory::of(
                new PageFiles(__DIR__),
                (new MessagesXmlParser())->defaults(),
                new StandardElements(),
            )->tags(),
            'input',
            'foreach',
            'if',
            'message',
        ];
        sort($expected);
        $names = array_values(array_unique($names));
        sort($names);
        self::assertSame($expected, $names);
    }

    /**
     * Validation errors of a page, none if it is valid
     *
     * @return list<string>
     */
    private static function errors(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument();
        if ($document->loadXML($xml)) {
            $document->schemaValidate(self::SCHEMA);
        }
        $errors = array_map(static fn(\LibXMLError $error) => trim($error->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $errors;
    }

    private static function schema(): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::SCHEMA));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace(prefix: 'xs', namespace: 'http://www.w3.org/2001/XMLSchema');

        return $xpath;
    }

    /**
     * Values of an enumeration of the schema, in order (for a union, those of its
     * enumerated member)
     *
     * @return list<string>
     */
    private static function enumeration(string $type): array
    {
        $values = [];
        $nodes = self::schema()->query("//xs:simpleType[@name=\"{$type}\"]//xs:enumeration/@value");
        foreach ($nodes === false ? [] : $nodes as $value) {
            $values[] = (string) $value->nodeValue;
        }

        return $values;
    }
}
