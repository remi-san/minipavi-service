<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\LintCommand;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class LintCommandTest extends TestCase
{
    private string $dir;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minipavi-lint-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/pages/sub', permissions: 0o777, recursive: true);
        $this->tester = new CommandTester(new LintCommand());
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testChecksThePagesOfAFolderAndItsSubfolders(): void
    {
        $this->write('pages/home.xml', content: '<page type="static"><text>Home</text></page>');
        $this->write('pages/sub/list.xml', content: '<page type="template"><text>{{title}}</text></page>');
        $this->write('pages/sub/MENU.XML', content: '<page type="static"><text>Menu</text></page>');
        // Not pages: skipped
        $this->write('pages/messages.xml', content: '<messages><message name="x">X</message></messages>');
        $this->write('pages/notes.txt', content: 'not XML');

        self::assertSame(Command::SUCCESS, $this->lint([$this->dir . '/pages']));
        self::assertSame("3 pages checked, 0 errors\n", $this->tester->getDisplay());
        self::assertSame('', $this->tester->getErrorOutput());
    }

    public function testSkipsALinkToAFolder(): void
    {
        $this->write('pages/home.xml', content: '<page type="static"><text>Home</text></page>');
        symlink($this->dir . '/pages/sub', $this->dir . '/pages/archive.xml');

        self::assertSame(Command::SUCCESS, $this->lint([$this->dir . '/pages']));
        self::assertSame("1 page checked, 0 errors\n", $this->tester->getDisplay());
        self::assertSame('', $this->tester->getErrorOutput());
    }

    public function testGivesTheErrorsInTheOrderOfTheFiles(): void
    {
        // Written out of order: a folder lists its files in no particular order
        foreach (['d', 'b', 'f', 'a', 'e', 'c'] as $name) {
            $this->write("pages/{$name}.xml", content: "<page type=\"static\">\n<text>");
        }

        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
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
        $this->write('pages/home.xml', content: '<page type="static"><text>Home</text></page>');
        $this->write('pages/image.xml', content: "<page type=\"template\">\n<image>missing.vdt</image></page>");
        $this->write('pages/broken.xml', content: "<page type=\"static\">\n<text>");

        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
        self::assertSame("3 pages checked, 2 errors\n", $this->tester->getDisplay());
        $errors = explode("\n", trim($this->tester->getErrorOutput()));
        self::assertCount(2, $errors);
        self::assertStringStartsWith('broken.xml line 2: ', $errors[0]);
        self::assertSame(
            "image.xml line 2: <image>: Videotex file not found: {$this->dir}/pages/missing.vdt",
            $errors[1],
        );
    }

    public function testReadsThePagesWithTheMessagesOfTheService(): void
    {
        $this->write('messages.xml', content: '<messages><message name="greeting">Hello</message></messages>');
        $this->write('pages/home.xml', content: '<page type="static"><message name="greeting">Hi</message></page>');

        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
        self::assertStringContainsString(
            'name="greeting" is not a message of the service',
            $this->tester->getErrorOutput(),
        );

        $this->tester = new CommandTester(new LintCommand());
        self::assertSame(Command::SUCCESS, $this->lint([
            '--messages',
            $this->dir . '/messages.xml',
            $this->dir . '/pages/home.xml',
        ]));
        self::assertSame("1 page checked, 0 errors\n", $this->tester->getDisplay());
    }

    public function testChecksAnXmlFileWithoutElementAsAPage(): void
    {
        $this->write('pages/empty.xml', content: '');

        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        // The parser's error, whose words depend on libxml's version
        try {
            (new PageXmlParser((new MessagesXmlParser())->defaults()))->parseFile($this->dir . '/pages/empty.xml');
            self::fail('An empty file was parsed');
        } catch (PageXmlException $e) {
            self::assertStringStartsWith('empty.xml', $e->getMessage());
            self::assertSame($e->getMessage() . "\n", $this->tester->getErrorOutput());
        }
    }

    public function testFailsOnAPageItCannotRead(): void
    {
        $this->write('pages/home.xml', content: '<page type="static"/>');
        chmod($this->dir . '/pages/home.xml', permissions: 0o000);
        if (is_readable($this->dir . '/pages/home.xml')) {
            self::markTestSkipped('Run as a user who can read any file (root)');
        }

        // The parser's error, without PHP's warnings
        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertSame("{$this->dir}/pages/home.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testFailsWithoutPage(): void
    {
        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/pages']));
        self::assertSame("No page found\n", $this->tester->getErrorOutput());
    }

    public function testFailsOnAPathNotFound(): void
    {
        $this->write('pages/home.xml', content: '<page type="static"/>');

        self::assertSame(Command::FAILURE, $this->lint([$this->dir . '/nope', $this->dir . '/pages']));
        self::assertSame("1 page checked, 1 error\n", $this->tester->getDisplay());
        self::assertSame("{$this->dir}/nope: not found\n", $this->tester->getErrorOutput());
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
        $this->write('pages/home.xml', content: '<page type="static"><text>Home</text></page>');
        // Not a page, with an error libxml finds reading its root element (an unknown prefix),
        // read last
        $this->write('pages/sub/feed.xml', content: '<atom:feed/>');

        $previous = libxml_use_internal_errors($internalErrors);
        try {
            self::assertSame(Command::SUCCESS, $this->lint([$this->dir . '/pages']));
            self::assertSame($internalErrors, libxml_use_internal_errors($internalErrors));
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testFailsOnAMessagesFileItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->lint(['--messages', $this->dir . '/nope.xml', $this->dir]));
        self::assertSame("{$this->dir}/nope.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testNeedsAPath(): void
    {
        $this->expectException(RuntimeException::class);

        $this->lint([]);
    }

    /**
     * @param list<string> $arguments the command line
     */
    private function lint(array $arguments): int
    {
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

    private function write(string $file, string $content): void
    {
        file_put_contents("{$this->dir}/{$file}", $content);
    }
}
