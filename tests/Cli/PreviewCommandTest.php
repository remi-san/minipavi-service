<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\ExitCode;
use MiniPaviService\Cli\PreviewCommand;
use MiniPaviService\Cli\UsageException;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Tests\Support\MemoryConsole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreviewCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/pages';

    private string $dir;

    private MemoryConsole $console;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minipavi-preview-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->console = new MemoryConsole();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testPrintsTheScreenOfAPage(): void
    {
        $page = (new PageXmlParser(Messages::defaults()))->parseFile(self::FIXTURES . '/logo.xml');

        self::assertSame(ExitCode::Success, $this->preview([self::FIXTURES . '/logo.xml']));
        self::assertSame($page->display(new Terminal())->screen()->preview(), $this->console->output());
    }

    public function testPrintsATemplateWithItsData(): void
    {
        $this->write('list.xml', '<page type="template"><foreach items="games" as="game" row="3"><text>{{game.name}}</text></foreach></page>');
        $this->write('data.json', '{"games": [{"name": "Alien Wipeout"}, {"name": "Twin Pine Falls"}]}');

        self::assertSame(ExitCode::Success, $this->preview([$this->dir . '/list.xml', $this->dir . '/data.json']));
        $rows = explode("\n", $this->console->output());
        self::assertSame([' 3|Alien Wipeout                           |', ' 4|Twin Pine Falls                         |'], [$rows[3], $rows[4]]);
    }

    public function testReadsThePageWithTheMessagesOfTheService(): void
    {
        $this->write('messages.xml', '<messages><message name="greeting">Hello</message></messages>');
        $this->write('page.xml', '<page type="static"><message name="greeting">Hi</message><text>x</text></page>');

        self::assertSame(ExitCode::Success, $this->preview(['--messages', $this->dir . '/messages.xml', $this->dir . '/page.xml']));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function failures(): iterable
    {
        yield 'page error' => ["<page type=\"static\">\n<text color=\"purple\">x</text></page>", '{}', 'page.xml line 2: <text>: '];
        yield 'missing variable' => ['<page type="template"><text>{{title}}</text></page>', '{}', 'page.xml line 1: <text>: unknown variable {{title}}'];
        yield 'data not JSON' => ['<page type="static"/>', '{', 'data.json: Syntax error'];
        yield 'data not an object' => ['<page type="static"/>', '[1, 2]', 'data.json: not a JSON object'];
    }

    #[DataProvider('failures')]
    public function testFailsWithTheError(string $page, string $data, string $error): void
    {
        $this->write('page.xml', $page);
        $this->write('data.json', $data);

        self::assertSame(ExitCode::Failure, $this->preview([$this->dir . '/page.xml', $this->dir . '/data.json']));
        self::assertStringContainsString($error, $this->console->errors());
        self::assertSame('', $this->console->output());
    }

    public function testFailsOnAPageNotFound(): void
    {
        self::assertSame(ExitCode::Failure, $this->preview([$this->dir . '/nope.xml']));
        self::assertSame("{$this->dir}/nope.xml: file not found\n", $this->console->errors());
    }

    public function testNeedsAPage(): void
    {
        $this->expectException(UsageException::class);

        $this->preview([]);
    }

    /**
     * @param list<string> $arguments
     */
    private function preview(array $arguments): ExitCode
    {
        return (new PreviewCommand())->run($arguments, $this->console->console);
    }

    private function write(string $file, string $content): void
    {
        file_put_contents("{$this->dir}/$file", $content);
    }
}
