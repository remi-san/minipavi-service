<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Message;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Tests\Support\FailingProperties;
use MiniPaviService\Tests\Support\PrivateProperties;
use MiniPaviService\Tests\Support\TestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    public function testTheFrameworkDefinesItsMessages(): void
    {
        $defaults = (new MessagesXmlParser())
            ->defaults()
            ->check(FrameworkMessage::class);

        self::assertSame('Dernière page', $defaults->get(FrameworkMessage::LastPage));
    }

    public function testReplacesThePlaceholders(): void
    {
        $messages = new Messages(['welcome' => 'Bonjour {{name}}, {{ count }} messages']);

        self::assertSame('Bonjour Léa, 3 messages', $messages->get(TestMessage::Welcome, [
            'name' => 'Léa',
            'count' => 3,
        ]));
    }

    public function testPlaceholdersGoThroughArraysAndObjectsAsInTemplates(): void
    {
        $messages = new Messages(['welcome' => 'Bonjour {{user.name}}, page {{ page.number }}']);

        self::assertSame('Bonjour Léa, page 2', $messages->get(TestMessage::Welcome, [
            'user' => ['name' => 'Léa'],
            'page' => (object) ['number' => 2],
        ]));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function placeholderErrors(): iterable
    {
        yield 'no variable' => ['Bonjour {{name}}', [], 'Message "welcome": unknown variable {{name}}'];
        yield 'a value that isn\'t text' => [
            'Bonjour {{user}}',
            ['user' => ['name' => 'Léa']],
            'Message "welcome": {{user}} is not text (array)',
        ];
        // Not placeholders, but not left in the text either: a mistake in the message
        yield 'a number' => ['Code {{1}}', [], 'Message "welcome": {{1}} is not a placeholder'];
        yield 'a dash' => [
            'Bonjour {{user-name}}',
            ['user' => 'x'],
            'Message "welcome": {{user-name}} is not a placeholder',
        ];
        yield 'a dot at the end' => [
            'Bonjour {{user.}}',
            ['user' => 'x'],
            'Message "welcome": {{user.}} is not a placeholder',
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[DataProvider('placeholderErrors')]
    public function testPlaceholderErrorsNameTheMessage(string $text, array $variables, string $error): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($error);

        (new Messages(['welcome' => $text]))->get(TestMessage::Welcome, $variables);
    }

    public function testPrivatePropertyIsAnUnknownVariableOfTheMessage(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Message "welcome": unknown variable {{user.city}}: no "city" in '
        . PrivateProperties::class);

        (new Messages(['welcome' => 'Bonjour {{user.city}}']))->get(TestMessage::Welcome, [
            'user' => new PrivateProperties(),
        ]);
    }

    public function testBugOfAVariableGoesThroughAsItIs(): void
    {
        // Not a LogicException naming the message: the variable's own code failed
        $this->expectExceptionObject(new \InvalidArgumentException('a bug reading name'));

        (new Messages(['welcome' => 'Bonjour {{user.name}}']))->get(TestMessage::Welcome, [
            'user' => new FailingProperties(),
        ]);
    }

    public function testMessageWithoutTextIsAnError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No text for the message "code.unknown"');

        (new Messages())->get(TestMessage::UnknownCode);
    }

    public function testOthersRedefineOrAdd(): void
    {
        $messages = (new MessagesXmlParser())
            ->defaults()
            ->with(['page.last' => "Fin de l'article", 'welcome' => 'Bonjour']);

        self::assertSame("Fin de l'article", $messages->get(FrameworkMessage::LastPage));
        self::assertSame('Bonjour', $messages->get(TestMessage::Welcome));
        self::assertSame('Première page', $messages->get(FrameworkMessage::FirstPage));
        // Immutable
        self::assertSame('Dernière page', (new MessagesXmlParser())->defaults()->get(FrameworkMessage::LastPage));
    }

    public function testCheckNamesTheMessagesWithoutText(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('No text for the messages: welcome, code.unknown');

        (new MessagesXmlParser())
            ->defaults()
            ->check(FrameworkMessage::class, TestMessage::class);
    }

    public function testFileRedefinesTheBaseMessages(): void
    {
        $messages = (new MessagesXmlParser())->parse(<<<'XML'
            <messages>
              <message name="welcome">Bonjour</message>
              <message name="page.last">  Fin  </message>
            </messages>
            XML, (new MessagesXmlParser())->defaults());

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
        yield 'twice' => [
            "<messages><message name=\"a\">x</message>\n<message name=\"a\">y</message></messages>",
            'm.xml line 2: <message>: name="a" is already in the file',
        ];
        // The first error in the file: the name missing, before the unknown element
        yield 'first error in the file' => [
            "<messages>\n<message>x</message>\n<text/></messages>",
            'm.xml line 2: <message>: name is missing',
        ];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFileThrowsWithItsLocation(string $xml, string $message): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage($message);

        (new MessagesXmlParser())->parse($xml, new Messages(), name: 'm.xml');
    }

    public function testMissingFileIsNamed(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('/missing.xml: file not found');

        (new MessagesXmlParser())->parseFile('/missing.xml', new Messages());
    }

    public function testFilesMatchTheirSchema(): void
    {
        $document = new \DOMDocument();
        $document->load(__DIR__ . '/../../config/messages.xml');

        self::assertTrue($document->schemaValidate(__DIR__ . '/../../schema/messages.xsd'));
    }
}
