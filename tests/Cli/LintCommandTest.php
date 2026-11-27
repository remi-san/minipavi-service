<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\LintCommand;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Tests\Support\MemoryFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * With the files in memory: how the files of a folder are found on disk is FilesTest's
 */
final class LintCommandTest extends TestCase
{
    private CommandTester $tester;

    public function testChecksThePagesOfAFolderAndItsSubfolders(): void
    {
        self::assertSame(Command::SUCCESS, $this->lint(
            [
                'pages/home.xml' => '<page type="static"><text>Home</text></page>',
                'pages/sub/list.xml' => '<page type="template"><text>{{title}}</text></page>',
                'pages/sub/MENU.XML' => '<page type="static"><text>Menu</text></page>',
                // Not pages: skipped
                'pages/messages.xml' => '<messages><message name="x">X</message></messages>',
                'pages/notes.txt' => 'not XML',
            ],
            ['pages'],
        ));
        self::assertSame("3 pages checked, 0 errors\n", $this->tester->getDisplay());
        self::assertSame('', $this->tester->getErrorOutput());
    }

    public function testChecksAFileGivenWhateverItsExtension(): void
    {
        self::assertSame(Command::SUCCESS, $this->lint([
            'pages/home.page' => '<page type="static"><text>Home</text></page>',
        ], ['pages/home.page']));
        self::assertSame("1 page checked, 0 errors\n", $this->tester->getDisplay());
    }

    public function testGivesTheErrorsInTheOrderOfTheFiles(): void
    {
        $files = [];
        // Out of order: the finder gives them in order
        foreach (['d', 'b', 'f', 'a', 'e', 'c'] as $name) {
            $files["pages/{$name}.xml"] = "<page type=\"static\">\n<text>";
        }

        self::assertSame(Command::FAILURE, $this->lint($files, ['pages']));
        self::assertSame("6 pages checked, 6 errors\n", $this->tester->getDisplay());
        self::assertSame(
            ['a.xml', 'b.xml', 'c.xml', 'd.xml', 'e.xml', 'f.xml'],
            array_map(
                static fn(string $error): string => explode(' ', $error)[0],
                explode("\n", trim($this->tester->getErrorOutput())),
            ),
        );
    }

    public function testFailsWithTheErrorsOfThePages(): void
    {
        self::assertSame(Command::FAILURE, $this->lint([
            'pages/home.xml' => '<page type="static"><text>Home</text></page>',
            'pages/image.xml' => "<page type=\"template\">\n<image>missing.vdt</image></page>",
            'pages/broken.xml' => "<page type=\"static\">\n<text>",
        ], ['pages']));
        self::assertSame("3 pages checked, 2 errors\n", $this->tester->getDisplay());
        $errors = explode("\n", trim($this->tester->getErrorOutput()));
        self::assertCount(2, $errors);
        self::assertStringStartsWith('broken.xml line 2: ', $errors[0]);
        self::assertSame('image.xml line 2: <image>: Videotex file not found: pages/missing.vdt', $errors[1]);
    }

    public function testReadsTheVideotexFilesOfThePagesWithItsReader(): void
    {
        self::assertSame(Command::SUCCESS, $this->lint([
            'pages/image.xml' => '<page type="template"><image>logo.vdt</image></page>',
            'pages/logo.vdt' => "\x0C",
        ], ['pages']));
        self::assertSame("1 page checked, 0 errors\n", $this->tester->getDisplay());
    }

    public function testReadsThePagesWithTheMessagesOfTheService(): void
    {
        $files = [
            'messages.xml' => '<messages><message name="greeting">Hello</message></messages>',
            'pages/home.xml' => '<page type="static"><message name="greeting">Hi</message></page>',
        ];

        self::assertSame(Command::FAILURE, $this->lint($files, ['pages']));
        self::assertStringContainsString(
            'name="greeting" is not a message of the service',
            $this->tester->getErrorOutput(),
        );

        self::assertSame(Command::SUCCESS, $this->lint($files, ['--messages', 'messages.xml', 'pages/home.xml']));
        self::assertSame("1 page checked, 0 errors\n", $this->tester->getDisplay());
    }

    public function testFailsOnAMalformedPlaceholderInAMessageOfAPage(): void
    {
        self::assertSame(Command::FAILURE, $this->lint([
            'pages/home.xml' => "<page type=\"static\">\n<message name=\"error\">Oops {{ not-a-name }}</message>\n<text>Hello</text></page>",
        ], ['pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertSame(
            "home.xml line 2: <message>: {{ not-a-name }} is not a placeholder\n",
            $this->tester->getErrorOutput(),
        );
    }

    public function testFailsOnAMalformedPlaceholderInTheMessagesFile(): void
    {
        self::assertSame(Command::FAILURE, $this->lint([
            'messages.xml' => "<messages>\n<message name=\"error\">Broken {{ 1bad }}</message></messages>",
            'pages/home.xml' => '<page type="static"><text>Hello</text></page>',
        ], ['--messages', 'messages.xml', 'pages/home.xml']));
        self::assertSame(
            "messages.xml line 2: <message>: {{ 1bad }} is not a placeholder\n",
            $this->tester->getErrorOutput(),
        );
    }

    public function testChecksAnXmlFileWithoutElementAsAPage(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['pages/empty.xml' => ''], ['pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        // The parser's error, whose words depend on libxml's version
        try {
            (new PageXmlParser((new MessagesXmlParser())->defaults()))->parse('', baseDir: 'pages', name: 'empty.xml');
            self::fail('An empty file was parsed');
        } catch (PageXmlException $e) {
            self::assertStringStartsWith('empty.xml', $e->getMessage());
            self::assertSame($e->getMessage() . "\n", $this->tester->getErrorOutput());
        }
    }

    public function testChecksAFileWhoseRootElementIsNotClosedAsAPage(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['pages/broken.xml' => '<?xml version="1.0"?><'], ['pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertStringStartsWith('broken.xml line 1: ', $this->tester->getErrorOutput());
    }

    public function testFailsOnAPageItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['pages/home.xml' => null], ['pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertSame("pages/home.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testFailsWithoutPage(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['pages/notes.txt' => 'not XML'], ['pages']));
        self::assertSame("No page found\n", $this->tester->getErrorOutput());
    }

    public function testFailsOnAPathNotFound(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['pages/home.xml' => '<page type="static"/>'], [
            'nope',
            'pages',
        ]));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertSame("nope: not found\n", $this->tester->getErrorOutput());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function libxmlErrorModes(): iterable
    {
        yield 'PHP warnings' => [false];
        yield 'internal errors' => [true];
    }

    #[DataProvider('libxmlErrorModes')]
    public function testLeavesLibxmlAsItFoundIt(bool $internalErrors): void
    {
        $previous = libxml_use_internal_errors($internalErrors);
        try {
            self::assertSame(Command::SUCCESS, $this->lint(
                [
                    'pages/home.xml' => '<page type="static"><text>Home</text></page>',
                    // Not a page, with an error libxml finds reading its root element (an unknown
                    // prefix), read last
                    'pages/sub/feed.xml' => '<atom:feed/>',
                ],
                ['pages'],
            ));
            self::assertSame($internalErrors, libxml_use_internal_errors($internalErrors));
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testFailsOnAMessagesFileItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->lint([], ['--messages', 'nope.xml', 'pages']));
        self::assertSame("nope.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testNeedsAPath(): void
    {
        $this->expectException(RuntimeException::class);

        $this->lint([], []);
    }

    /**
     * @param array<string, ?string> $files    content by path, null for a file that can't be read
     * @param list<string>           $arguments the command line
     */
    private function lint(array $files, array $arguments): int
    {
        $memory = new MemoryFiles($files);
        $this->tester = new CommandTester(new LintCommand($memory, $memory));
        $input = [];
        $paths = [];
        for ($i = 0; $i < \count($arguments); $i++) {
            if ($arguments[$i] === '--messages') {
                $input['--messages'] = $arguments[++$i] ?? '';
                continue;
            }
            $paths[] = $arguments[$i];
        }

        if ($paths !== []) {
            $input['paths'] = $paths;
        }

        return $this->tester->execute($input, ['capture_stderr_separately' => true]);
    }
}
