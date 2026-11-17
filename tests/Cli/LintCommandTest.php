<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\ExitCode;
use MiniPaviService\Cli\LintCommand;
use MiniPaviService\Cli\UsageException;
use MiniPaviService\Tests\Support\MemoryConsole;
use PHPUnit\Framework\TestCase;

final class LintCommandTest extends TestCase
{
    private string $dir;

    private MemoryConsole $console;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minipavi-lint-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/pages/sub', 0o777, true);
        $this->console = new MemoryConsole();
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }
        rmdir($this->dir);
    }

    public function testChecksThePagesOfAFolderAndItsSubfolders(): void
    {
        $this->write('pages/home.xml', '<page type="static"><text>Home</text></page>');
        $this->write('pages/sub/list.xml', '<page type="template"><text>{{title}}</text></page>');
        // Not pages: skipped
        $this->write('pages/messages.xml', '<messages><message name="x">X</message></messages>');
        $this->write('pages/notes.txt', 'not XML');

        self::assertSame(ExitCode::Success, $this->lint([$this->dir . '/pages']));
        self::assertSame("2 pages checked, 0 errors\n", $this->console->output());
        self::assertSame('', $this->console->errors());
    }

    public function testFailsWithTheErrorsOfThePages(): void
    {
        $this->write('pages/home.xml', '<page type="static"><text>Home</text></page>');
        $this->write('pages/image.xml', "<page type=\"template\">\n<image>missing.vdt</image></page>");
        $this->write('pages/broken.xml', "<page type=\"static\">\n<text>");

        self::assertSame(ExitCode::Failure, $this->lint([$this->dir . '/pages']));
        self::assertSame("3 pages checked, 2 errors\n", $this->console->output());
        $errors = explode("\n", trim($this->console->errors()));
        self::assertCount(2, $errors);
        self::assertStringStartsWith('broken.xml line 2: ', $errors[0]);
        self::assertSame("image.xml line 2: <image>: Videotex file not found: {$this->dir}/pages/missing.vdt", $errors[1]);
    }

    public function testReadsThePagesWithTheMessagesOfTheService(): void
    {
        $this->write('messages.xml', '<messages><message name="greeting">Hello</message></messages>');
        $this->write('pages/home.xml', '<page type="static"><message name="greeting">Hi</message></page>');

        self::assertSame(ExitCode::Failure, $this->lint([$this->dir . '/pages']));
        self::assertStringContainsString('name="greeting" is not a message of the service', $this->console->errors());

        $this->console = new MemoryConsole();
        self::assertSame(ExitCode::Success, $this->lint(['--messages', $this->dir . '/messages.xml', $this->dir . '/pages/home.xml']));
        self::assertSame("1 page checked, 0 errors\n", $this->console->output());
    }

    public function testFailsWithoutPage(): void
    {
        self::assertSame(ExitCode::Failure, $this->lint([$this->dir . '/pages']));
        self::assertSame("No page found\n", $this->console->errors());
    }

    public function testFailsOnAPathNotFound(): void
    {
        $this->write('pages/home.xml', '<page type="static"/>');

        self::assertSame(ExitCode::Failure, $this->lint([$this->dir . '/pages', $this->dir . '/nope']));
        self::assertSame("{$this->dir}/nope: not found\n", $this->console->errors());
    }

    public function testFailsOnAMessagesFileItCannotRead(): void
    {
        self::assertSame(ExitCode::Failure, $this->lint(['--messages', $this->dir . '/nope.xml', $this->dir]));
        self::assertSame("{$this->dir}/nope.xml: file not found\n", $this->console->errors());
    }

    public function testNeedsAPath(): void
    {
        $this->expectException(UsageException::class);

        $this->lint([]);
    }

    /**
     * @param list<string> $arguments
     */
    private function lint(array $arguments): ExitCode
    {
        return (new LintCommand())->run($arguments, $this->console->console);
    }

    private function write(string $file, string $content): void
    {
        file_put_contents("{$this->dir}/$file", $content);
    }
}
