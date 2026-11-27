<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Message;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Tests\Support\FailingProperties;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Tests\Support\PrivateProperties;
use MiniPaviService\Tests\Support\SharingMessage;
use MiniPaviService\Tests\Support\TestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    public function testTheFrameworkDefinesItsMessages(): void
    {
        $defaults = (new MessagesXmlParser())
            ->defaults()
            ->check(...FrameworkMessage::cases());

        self::assertSame('Dernière page', $defaults->get(FrameworkMessage::LastPage));
    }

    public function testReadsItsFilesThroughItsFileReader(): void
    {
        $files = new MemoryFiles([
            '/config/messages.xml' => '<messages><message name="welcome">Bienvenue</message></messages>',
        ]);

        $messages = (new MessagesXmlParser($files))->parseFile('/config/messages.xml', new Messages());

        self::assertSame('Bienvenue', $messages->get(TestMessage::Welcome));
        self::assertSame(['/config/messages.xml'], $files->read);
    }

    public function testReadsTheFrameworkMessagesThroughItsFileReaderToo(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('messages.xml: file not found');

        (new MessagesXmlParser(new MemoryFiles([])))->defaults();
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
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedTexts(): iterable
    {
        yield 'a number' => ['Code {{1}}', 'Message "welcome": {{1}} is not a placeholder'];
        yield 'a dash' => ['Bonjour {{user-name}}', 'Message "welcome": {{user-name}} is not a placeholder'];
        yield 'a dot at the end' => ['Bonjour {{user.}}', 'Message "welcome": {{user.}} is not a placeholder'];
    }

    /**
     * Not placeholders, but not left in the text either: a mistake in the message, found when
     * the messages are built (at startup), not when it is shown
     */
    #[DataProvider('malformedTexts')]
    public function testMessagesWithAMalformedPlaceholderAreNotBuilt(string $text, string $error): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage($error);

        new Messages(['bienvenue' => 'Bienvenue', 'welcome' => $text]);
    }

    #[DataProvider('malformedTexts')]
    public function testAMalformedPlaceholderIsNotAdded(string $text, string $error): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage($error);

        (new Messages(['bienvenue' => 'Bienvenue']))->with(['welcome' => $text]);
    }

    public function testMalformedPlaceholderErrorKeepsTheErrorOfThePlaceholders(): void
    {
        try {
            new Messages(['welcome' => 'Code {{1}}']);
        } catch (MessagesException $e) {
            self::assertInstanceOf(InvalidValueException::class, $e->getPrevious());

            return;
        }
        self::fail('Messages with a malformed placeholder were built');
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[DataProvider('placeholderErrors')]
    public function testPlaceholderErrorsNameTheMessage(string $text, array $variables, string $error): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage($error);

        (new Messages(['welcome' => $text]))->get(TestMessage::Welcome, $variables);
    }

    public function testPlaceholderErrorKeepsTheErrorOfTheVariable(): void
    {
        try {
            (new Messages(['welcome' => 'Bonjour {{name}}']))->get(TestMessage::Welcome);
        } catch (MessagesException $e) {
            self::assertSame('Message "welcome": unknown variable {{name}}', $e->getMessage());
            // For the log: where the variable was read
            self::assertInstanceOf(InvalidValueException::class, $e->getPrevious());

            return;
        }
        self::fail('A message with an unknown variable was given');
    }

    public function testPrivatePropertyIsAnUnknownVariableOfTheMessage(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('Message "welcome": unknown variable {{user.city}}: no "city" in '
        . PrivateProperties::class);

        (new Messages(['welcome' => 'Bonjour {{user.city}}']))->get(TestMessage::Welcome, [
            'user' => new PrivateProperties(),
        ]);
    }

    public function testBugOfAVariableGoesThroughAsItIs(): void
    {
        // Not a MessagesException naming the message: the variable's own code failed
        $this->expectExceptionObject(new \InvalidArgumentException('a bug reading name'));

        (new Messages(['welcome' => 'Bonjour {{user.name}}']))->get(TestMessage::Welcome, [
            'user' => new FailingProperties(),
        ]);
    }

    public function testMessageWithoutTextIsAnError(): void
    {
        $this->expectException(MessagesException::class);
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
            ->check(...FrameworkMessage::cases(), ...TestMessage::cases());
    }

    public function testCheckNamesTheMessagesWithoutTextAfterOneWithText(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage('No text for the messages: code.unknown');

        (new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bienvenue'])
            ->check(...TestMessage::cases());
    }

    public function testCheckChecksOnlyTheMessagesGiven(): void
    {
        $messages = (new Messages(['welcome' => 'Bienvenue']))->check(TestMessage::Welcome);

        self::assertSame('Bienvenue', $messages->get(TestMessage::Welcome));
    }

    public function testCheckNamesTheMessagesOfDifferentEnumsWithTheSameName(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage(
            'Messages of different enums with the same name: '
            . 'MiniPaviService\Message\FrameworkMessage::Error and '
            . 'MiniPaviService\Tests\Support\SharingMessage::Oops are both "error", '
            . 'MiniPaviService\Tests\Support\TestMessage::Welcome and '
            . 'MiniPaviService\Tests\Support\SharingMessage::Hello are both "welcome"',
        );

        (new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bienvenue', 'code.unknown' => 'Code inconnu'])
            ->check(...FrameworkMessage::cases(), ...TestMessage::cases(), ...SharingMessage::cases());
    }

    public function testCheckNamesTheMessagesWithTheSameNameBeforeThoseWithoutText(): void
    {
        $this->expectException(MessagesException::class);
        $this->expectExceptionMessage(
            'Messages of different enums with the same name: '
            . 'MiniPaviService\Tests\Support\SharingMessage::Oops and '
            . 'MiniPaviService\Message\FrameworkMessage::Error are both "error"',
        );

        (new MessagesXmlParser())
            ->defaults()
            ->check(SharingMessage::Oops, ...TestMessage::cases(), ...FrameworkMessage::cases());
    }

    public function testCheckAcceptsAMessageGivenTwice(): void
    {
        $messages = (new Messages(['welcome' => 'Bienvenue']))->check(TestMessage::Welcome, TestMessage::Welcome);

        self::assertSame('Bienvenue', $messages->get(TestMessage::Welcome));
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
        // Shown as it is otherwise: a mistake in the file
        yield 'malformed placeholder' => [
            "<messages>\n<message name=\"error\">Broken {{ 1bad }}</message></messages>",
            'm.xml line 2: <message>: {{ 1bad }} is not a placeholder',
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
