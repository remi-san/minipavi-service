<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Message;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Tests\Support\TestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    public function testTheFrameworkDefinesItsMessages(): void
    {
        $defaults = Messages::defaults()->check(FrameworkMessage::class);

        self::assertSame('Dernière page', $defaults->get(FrameworkMessage::LastPage));
        self::assertSame($defaults, Messages::defaults());
    }

    public function testReplacesThePlaceholders(): void
    {
        $messages = new Messages(['welcome' => 'Bonjour {{name}}, {{ count }} messages']);

        self::assertSame('Bonjour Léa, 3 messages', $messages->get(TestMessage::Welcome, ['name' => 'Léa', 'count' => 3]));
    }

    public function testPlaceholderWithoutVariableIsAnError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Message "welcome" needs the variable "name"');

        (new Messages(['welcome' => 'Bonjour {{name}}']))->get(TestMessage::Welcome);
    }

    public function testMessageWithoutTextIsAnError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No text for the message "code.unknown"');

        (new Messages())->get(TestMessage::UnknownCode);
    }

    public function testOthersRedefineOrAdd(): void
    {
        $messages = Messages::defaults()->with(['page.last' => "Fin de l'article", 'welcome' => 'Bonjour']);

        self::assertSame("Fin de l'article", $messages->get(FrameworkMessage::LastPage));
        self::assertSame('Bonjour', $messages->get(TestMessage::Welcome));
        self::assertSame('Première page', $messages->get(FrameworkMessage::FirstPage));
        // Immutable
        self::assertSame('Dernière page', Messages::defaults()->get(FrameworkMessage::LastPage));
    }

    public function testCheckNamesTheMessagesWithoutText(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('No text for the messages: welcome, code.unknown');

        Messages::defaults()->check(FrameworkMessage::class, TestMessage::class);
    }

    public function testFileRedefinesTheBaseMessages(): void
    {
        $messages = (new MessagesXmlParser())->parse(<<<'XML'
            <messages>
              <message name="welcome">Bonjour</message>
              <message name="page.last">  Fin  </message>
            </messages>
            XML, Messages::defaults());

        self::assertSame('Bonjour', $messages->get(TestMessage::Welcome));
        self::assertSame('Fin', $messages->get(FrameworkMessage::LastPage));
        self::assertSame('Première page', $messages->get(FrameworkMessage::FirstPage));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'invalid XML' => ['<messages><message>', 'm.xml line 1: '];
        yield 'other root' => ['<texts/>', 'm.xml: root element must be <messages>, not <texts>'];
        yield 'other element' => ["<messages>\n<text/></messages>", 'm.xml line 2: <text>: unknown element'];
        yield 'no name' => ['<messages><message>x</message></messages>', 'm.xml line 1: <message>: name is missing'];
        yield 'twice' => ["<messages><message name=\"a\">x</message>\n<message name=\"a\">y</message></messages>", 'm.xml line 2: <message>: name="a" is already in the file'];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFileThrowsWithItsLocation(string $xml, string $message): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage($message);

        (new MessagesXmlParser())->parse($xml, name: 'm.xml');
    }

    public function testMissingFileIsNamed(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('/missing.xml: file not found');

        (new MessagesXmlParser())->parseFile('/missing.xml');
    }

    public function testFilesMatchTheirSchema(): void
    {
        $document = new \DOMDocument();
        $document->load(__DIR__ . '/../../config/messages.xml');

        self::assertTrue($document->schemaValidate(__DIR__ . '/../../schema/messages.xsd'));
    }
}
