<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Message;

use MiniPaviService\Message\MessageElement;
use MiniPaviService\Message\MessageTexts;
use MiniPaviService\Xml\XmlDocument;
use MiniPaviService\Xml\XmlException;
use PHPUnit\Framework\TestCase;

final class MessageElementTest extends TestCase
{
    public function testReadsTheNameAndTextTrimmed(): void
    {
        $message = MessageElement::of(self::element('<message name=" welcome "> Bonjour </message>'), file: 'm.xml');

        self::assertSame(['welcome', 'Bonjour'], [$message->name, $message->text]);
    }

    public function testErrorsGiveTheFileAndLine(): void
    {
        $element = self::element("<messages>\n\n<message name=\"a\">x</message></messages>")
            ->children()[0] ?? self::fail('No message');

        self::assertSame(
            'm.xml line 3: <message>: wrong',
            MessageElement::of($element, file: 'm.xml')->error('wrong')->getMessage(),
        );
    }

    public function testAMessageWithoutNameIsAnError(): void
    {
        $this->expectException(XmlException::class);
        $this->expectExceptionMessage('m.xml line 1: <message>: name is missing');

        MessageElement::of(self::element('<message> </message>'), file: 'm.xml');
    }

    public function testTextsAreByNameEachOnce(): void
    {
        $texts = new MessageTexts(in: 'the page');
        $texts->add(MessageElement::of(self::element('<message name="a">x</message>'), file: 'p.xml'));
        $texts->add(MessageElement::of(self::element('<message name="b">y</message>'), file: 'p.xml'));

        self::assertSame(['a' => 'x', 'b' => 'y'], $texts->texts());

        $this->expectException(XmlException::class);
        $this->expectExceptionMessage('p.xml line 1: <message>: name="a" is already in the page');

        $texts->add(MessageElement::of(self::element('<message name="a">z</message>'), file: 'p.xml'));
    }

    private static function element(string $xml): \SimpleXMLElement
    {
        return XmlDocument::load($xml, name: 'test');
    }
}
