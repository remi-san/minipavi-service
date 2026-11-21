<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\PreviewCommand;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Vdt\Screen\Screen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class PreviewCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/pages';

    private CommandTester $tester;

    public function testPrintsTheScreenOfAPage(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults()))->parseFile(self::FIXTURES . '/logo.xml');

        self::assertSame(Command::SUCCESS, $this->preview([
            'pages/logo.xml' => (string) file_get_contents(self::FIXTURES . '/logo.xml'),
            'pages/images/logo.vdt' => (string) file_get_contents(self::FIXTURES . '/images/logo.vdt'),
        ], ['pages/logo.xml']));
        self::assertSame(Screen::of($page->display(new Terminal()))->preview(), $this->tester->getDisplay());
    }

    public function testPrintsATemplateWithItsData(): void
    {
        self::assertSame(Command::SUCCESS, $this->preview([
            'list.xml' =>
                '<page type="template"><text row="1">{{title}}</text>'
                    . '<foreach items="games" as="game" row="3"><text>{{game.name}}</text></foreach></page>',
            'data.json' => '{"title": "Games", "games": [{"name": "Rocket Rescue"}, {"name": "Harbour Pirates"}]}',
        ], ['list.xml', 'data.json']));
        $rows = explode("\n", $this->tester->getDisplay());
        self::assertSame([
            ' 1|Games                                   |',
            ' 3|Rocket Rescue                           |',
            ' 4|Harbour Pirates                         |',
        ], [$rows[1], $rows[3], $rows[4]]);
    }

    public function testReadsThePageWithTheMessagesOfTheService(): void
    {
        self::assertSame(Command::SUCCESS, $this->preview([
            'messages.xml' => '<messages><message name="greeting">Hello</message></messages>',
            'page.xml' => '<page type="static"><message name="greeting">Hi</message><text>x</text></page>',
        ], ['--messages', 'messages.xml', 'page.xml']));
    }

    public function testRefusesAMessageThatIsNotOneOfTheService(): void
    {
        self::assertSame(Command::FAILURE, $this->preview([
            'page.xml' => '<page type="static"><message name="greeting">Hi</message><text>x</text></page>',
        ], ['page.xml']));
        self::assertStringContainsString(
            'name="greeting" is not a message of the service',
            $this->tester->getErrorOutput(),
        );
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
        self::assertSame(Command::FAILURE, $this->preview(['page.xml' => $page, 'data.json' => $data], [
            'page.xml',
            'data.json',
        ]));
        self::assertStringContainsString($error, $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testFailsOnAPageNotFound(): void
    {
        self::assertSame(Command::FAILURE, $this->preview([], ['nope.xml']));
        self::assertSame("nope.xml: file not found\n", $this->tester->getErrorOutput());
    }

    public function testFailsOnADataFileNotFound(): void
    {
        self::assertSame(Command::FAILURE, $this->preview(['page.xml' => '<page type="static"/>'], [
            'page.xml',
            'nope.json',
        ]));
        self::assertSame("nope.json: file not found\n", $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testFailsOnAMessagesFileItCannotRead(): void
    {
        self::assertSame(Command::FAILURE, $this->preview(['page.xml' => '<page type="static"/>'], [
            '--messages',
            'nope.xml',
            'page.xml',
        ]));
        self::assertSame("nope.xml: file not found\n", $this->tester->getErrorOutput());
        self::assertSame('', $this->tester->getDisplay());
    }

    public function testNeedsAPage(): void
    {
        $this->expectException(RuntimeException::class);

        $this->preview([], []);
    }

    /**
     * @param array<string, string> $files the files the command reads, by path
     * @param list<string> $arguments the command line: [--messages file] page [data]
     */
    private function preview(array $files, array $arguments): int
    {
        $this->tester = new CommandTester(new PreviewCommand(new MemoryFiles($files)));
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
}
