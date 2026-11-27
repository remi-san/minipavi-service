<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Tests\Support\ParsedPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Invalid pages, refused with their location
 */
final class InvalidPagesTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
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
            'f.xml line 1: <message>: name="page.last" is already in the page',
        ];
        yield 'message with a malformed placeholder' => [
            "<page type=\"static\">\n<message name=\"page.last\">Oops {{ not-a-name }}</message></page>",
            'f.xml line 2: <message>: {{ not-a-name }} is not a placeholder',
        ];
        // The first error in the file: the second "page.last", before the unknown message
        yield 'first message error in the file' => [
            "<page type=\"static\">\n<message name=\"page.last\">x</message>\n<message name=\"page.last\">y</message>\n<message name=\"zzz\">z</message></page>",
            'f.xml line 3: <message>: name="page.last" is already in the page',
        ];
        yield 'message in a loop' => [
            '<page type="template"><foreach items="a" as="b"><message name="page.last">x</message></foreach></page>',
            'f.xml line 1: <message>: only at the top level of the page',
        ];
        yield 'message in an if' => [
            '<page type="template"><if test="a"><message name="page.last">x</message></if></page>',
            'f.xml line 1: <message>: only at the top level of the page',
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
            'f.xml line 1: <text>: unknown color "pink" (black, red, green, yellow, blue, magenta, cyan, white)',
        ];
        yield 'line too long' => [
            '<page type="static"><line row="1" col="30" length="20"/></page>',
            'f.xml line 1: <line>: Length 20 from column 30 goes past column 40',
        ];
        yield 'unknown alignment' => [
            '<page type="static"><text align="middle">x</text></page>',
            'f.xml line 1: <text>: align="middle" must be left or center',
        ];
        yield 'unknown fill' => [
            '<page type="static"><text fill="true">x</text></page>',
            'f.xml line 1: <text>: fill="true" must be yes or no',
        ];
        yield 'line of two characters' => [
            '<page type="static"><line char="=-"/></page>',
            "f.xml line 1: <line>: A line is made of a single character, got '=-'",
        ];
        yield 'line of no character' => [
            '<page type="static"><line char=""/></page>',
            "f.xml line 1: <line>: A line is made of a single character, got ''",
        ];
        yield 'image on line 0' => [
            '<page type="static"><image row="0">images/logo.vdt</image></page>',
            'f.xml line 1: <image>: An image starts at row 1 at least',
        ];
        yield 'image without file' => [
            '<page type="static"><image> </image></page>',
            'f.xml line 1: <image>: the file is missing',
        ];
        yield 'placeholder in a static page' => [
            '<page type="static"><text>{{x}}</text></page>',
            'f.xml line 1: <text>: {{placeholders}} only in a page of type="template"',
        ];
        yield 'foreach in a static page' => [
            '<page type="static"><foreach items="a" as="b"/></page>',
            'f.xml line 1: <foreach>: only in a page of type="template"',
        ];
        yield 'foreach as loop' => [
            '<page type="template"><foreach items="a" as="loop"/></page>',
            'f.xml line 1: <foreach>: as="loop" must be a variable name, other than loop',
        ];
        yield 'foreach as starting with a digit' => [
            '<page type="template"><foreach items="a" as="1a"/></page>',
            'f.xml line 1: <foreach>: as="1a" must be a variable name, other than loop',
        ];
        yield 'foreach as with a dash' => [
            '<page type="template"><foreach items="a" as="a-b"/></page>',
            'f.xml line 1: <foreach>: as="a-b" must be a variable name, other than loop',
        ];
        yield 'if in a static page' => [
            '<page type="static"><if test="x"/></page>',
            'f.xml line 1: <if>: only in a page of type="template"',
        ];
        yield 'if without test' => [
            '<page type="template"><if test="!"/></page>',
            'f.xml line 1: <if>: test is missing',
        ];
        // Found while parsing, with the file and line, not when displayed
        yield 'if test with a space' => [
            '<page type="template"><if test="! x"/></page>',
            'f.xml line 1: <if>: test="! x" must be a variable, or !variable',
        ];
        yield 'if test negated twice' => [
            '<page type="template"><if test="!!x"/></page>',
            'f.xml line 1: <if>: test="!!x" must be a variable, or !variable',
        ];
        yield 'if test of two variables' => [
            '<page type="template"><if test="x y"/></page>',
            'f.xml line 1: <if>: test="x y" must be a variable, or !variable',
        ];
        yield 'two inputs' => [
            '<page type="static"><input/><input/></page>',
            'f.xml line 1: <input>: only one <input> per page',
        ];
        yield 'input in a loop' => [
            '<page type="template"><foreach items="a" as="b"><input/></foreach></page>',
            'f.xml line 1: <input>: only at the top level of the page',
        ];
        yield 'input in an if' => [
            '<page type="template"><if test="a"><input/></if></page>',
            'f.xml line 1: <input>: only at the top level of the page',
        ];
        yield 'placeholder in an input' => [
            '<page type="template"><input row="{{r}}"/></page>',
            'f.xml line 1: <input>: no {{placeholders}} in <input>',
        ];
        yield 'input past the last row' => [
            '<page type="static"><input row="23" lines="3"/></page>',
            'f.xml line 1: <input>: Input of 3 lines from row 23 goes past row 24',
        ];
        yield 'unknown validwith key' => [
            '<page type="static"><input validwith="enter"/></page>',
            'f.xml line 1: <input>: unknown validwith key "enter" '
                . '(sommaire, annulation, retour, repetition, guide, correction, suite, envoi)',
        ];
        yield 'validwith keys separated by a comma' => [
            '<page type="static"><input validwith="envoi,suite"/></page>',
            'f.xml line 1: <input>: unknown validwith key "envoi,suite" '
                . '(sommaire, annulation, retour, repetition, guide, correction, suite, envoi)',
        ];
        yield 'endkey in a loop' => [
            '<page type="template"><foreach items="a" as="b"><endkey/></foreach></page>',
            'f.xml line 1: <endkey>: not inside <foreach>',
        ];
        yield 'file outside the folder' => [
            '<page type="static"><image>../secret.vdt</image></page>',
            "f.xml line 1: <image>: ../secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'absolute file' => [
            '<page type="static"><background>/etc/passwd</background></page>',
            "f.xml line 1: <background>: /etc/passwd: only files from the page's folder or its subfolders",
        ];
        yield 'file outside the folder with backslashes' => [
            '<page type="static"><image>images\\..\\..\\secret.vdt</image></page>',
            "f.xml line 1: <image>: images\\..\\..\\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'absolute file with a backslash' => [
            '<page type="static"><background>\\secret.vdt</background></page>',
            "f.xml line 1: <background>: \\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file on a Windows drive' => [
            '<page type="static"><image>C:/secret.vdt</image></page>',
            "f.xml line 1: <image>: C:/secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file on a Windows drive with a backslash' => [
            '<page type="static"><image>c:\\secret.vdt</image></page>',
            "f.xml line 1: <image>: c:\\secret.vdt: only files from the page's folder or its subfolders",
        ];
        yield 'file relative to a Windows drive' => [
            '<page type="static"><image>C:secret.vdt</image></page>',
            "f.xml line 1: <image>: C:secret.vdt: only files from the page's folder or its subfolders",
        ];
    }

    #[DataProvider('invalidPages')]
    public function testInvalidPageThrowsWithItsLocation(string $xml, string $message): void
    {
        self::assertSame($message, $this->pages->error($xml)->getMessage());
    }
}
