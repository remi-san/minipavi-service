<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\PreviewCommand;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Vdt\Screen\Screen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class PreviewCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/pages';

    private string $dir;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minipavi-preview-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->tester = new CommandTester(new PreviewCommand());
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testPrintsTheScreenOfAPage(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults()))->parseFile(self::FIXTURES . '/logo.xml');

        self::assertSame(Command::SUCCESS, $this->preview([self::FIXTURES . '/logo.xml']));
        self::assertSame(Screen::of($page->display(new Terminal()))->preview(), $this->tester->getDisplay());
    }

    public function testPrintsATemplateWithItsData(): void
    {
        $this->write(
            'list.xml',
            content: '<page type="template"><foreach items="games" as="game" row="3"><text>{{game.name}}</text></foreach></page>',
        );
        $this->write('data.json', content: '{"games": [{"name": "Rocket Rescue"}, {"name": "Harbour Pirates"}]}');

        self::assertSame(Command::SUCCESS, $this->preview([$this->dir . '/list.xml', $this->dir . '/data.json']));
        $rows = explode("\n", $this->tester->getDisplay());
        self::assertSame([
            ' 3|Rocket Rescue                           |',
            ' 4|Harbour Pirates                         |',
        ], [$rows[3], $rows[4]]);
    }

    public function testReadsThePageWithTheMessagesOfTheService(): void
    {
        $this->write('messages.xml', content: '<messages><message name="greeting">Hello</message></messages>');
        $this->write(
            'page.xml',
            content: '<page type="static"><message name="greeting">Hi</message><text>x</text></page>',
        );

        self::assertSame(Command::SUCCESS, $this->preview([
            '--messages',
            $this->dir . '/messages.xml',
            $this->dir . '/page.xml',
        ]));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function failures(): iterable
    {
        yield 'page error' => [
            "<page type=\"static\">\n<text color=\"purple\">x</text></page>",
            '{}',
            'page.xml line 2: <text>: ',
        ];
        yield 'missing variable' => [
            '<page type="template"><text>{{title}}</text></page>',
            '{}',
            'page.xml line 1: <text>: unknown variable {{title}}',
        ];
        yield 'data not JSON' => ['<page type="static"/>', '{', 'data.json: Syntax error'];
        yield 'data not an object' => ['<page type="static"/>', '[1, 2]', 'data.json: not a JSON object'];
    }

    #[DataProvider('failures')]
    public function testFailsWithTheError(string $page, string $data, string $error): void
    {
        $this->write('page.xml', $page);
        $this->write('data.json', $data);

        self::assertSame(Command::FAILURE, $this->preview([$this->dir . '/page.xml', $this->dir . '/data.json']));
        self::assertStringContainsString($error, $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testFailsOnAPageNotFound(): void
    {
        self::assertSame(Command::FAILURE, $this->preview([$this->dir . '/nope.xml']));
        self::assertSame("{$this->dir}/nope.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testFailsOnAMessagesFileItCannotRead(): void
    {
        $this->write('page.xml', content: '<page type="static"/>');

        self::assertSame(Command::FAILURE, $this->preview([
            '--messages',
            $this->dir . '/nope.xml',
            $this->dir . '/page.xml',
        ]));
        self::assertSame("{$this->dir}/nope.xml: file not found\n", $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testNeedsAPage(): void
    {
        $this->expectException(RuntimeException::class);

        $this->preview([]);
    }

    /**
     * @param list<string> $arguments the command line: [--messages file] page [data]
     */
    private function preview(array $arguments): int
    {
        $input = [];
        if (($arguments[0] ?? '') === '--messages') {
            $input['--messages'] = $arguments[1] ?? '';
            $arguments = \array_slice($arguments, offset: 2);
        }
        foreach (['page', 'data'] as $i => $name) {
            if (!array_key_exists($i, $arguments)) {
                continue;
            }

            $input[$name] = $arguments[$i];
        }

        return $this->tester->execute($input, ['capture_stderr_separately' => true]);
    }

    private function write(string $file, string $content): void
    {
        file_put_contents("{$this->dir}/{$file}", $content);
    }
}
