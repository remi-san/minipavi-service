<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Xml;

use MiniPaviService\Xml\XmlDocument;
use MiniPaviService\Xml\XmlException;
use PHPUnit\Framework\TestCase;

final class XmlDocumentTest extends TestCase
{
    public function testReadsTheRootWithCdataAsText(): void
    {
        $root = XmlDocument::withRoot('<page><text><![CDATA[a < b]]></text></page>', name: 'home.xml', root: 'page');

        $text = $root->children()[0] ?? self::fail('No <text>');
        self::assertSame('a < b', (string) $text);
        // A text node, not a CDATA section, which the page parser wouldn't read as text
        self::assertSame(XML_TEXT_NODE, dom_import_simplexml($text)->firstChild?->nodeType);
    }

    public function testMalformedXmlGivesTheFileAndLine(): void
    {
        $this->expectException(XmlException::class);
        $this->expectExceptionMessageMatches('/^home\.xml line 2: /');

        XmlDocument::load("<page>\n<text></page>", name: 'home.xml');
    }

    public function testAnotherRootIsAnError(): void
    {
        $this->expectException(XmlException::class);
        $this->expectExceptionMessage('home.xml: root element must be <page>, not <messages>');

        XmlDocument::withRoot('<messages/>', name: 'home.xml', root: 'page');
    }

    public function testLeavesLibxmlAsItWas(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            $thrown = false;
            try {
                XmlDocument::load('<page>', name: 'home.xml');
            } catch (XmlException) {
                $thrown = true;
            }

            self::assertTrue($thrown);
            self::assertFalse(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
