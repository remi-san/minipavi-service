<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\Application;
use MiniPaviService\Cli\Command;
use MiniPaviService\Cli\Console;
use MiniPaviService\Cli\ExitCode;
use MiniPaviService\Cli\UsageException;
use MiniPaviService\Tests\Support\MemoryConsole;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private Application $application;

    private MemoryConsole $console;

    protected function setUp(): void
    {
        $this->console = new MemoryConsole();
        // echo: writes its arguments; nothing given, a usage error
        $this->application = new Application(new class implements Command {
            public function name(): string
            {
                return 'echo';
            }

            public function usage(): string
            {
                return '<text>...';
            }

            public function description(): string
            {
                return 'Writes its arguments';
            }

            public function run(array $arguments, Console $console): ExitCode
            {
                if ($arguments === []) {
                    throw new UsageException('nothing to write');
                }
                $console->write(implode(' ', $arguments));

                return ExitCode::Success;
            }
        });
    }

    public function testRunsTheCommandWithTheOtherArguments(): void
    {
        self::assertSame(ExitCode::Success, $this->application->run(['echo', 'a', 'b'], $this->console->console));
        self::assertSame('a b', $this->console->output());
    }

    public function testShowsTheUsageOfACommandRunWrong(): void
    {
        self::assertSame(ExitCode::Usage, $this->application->run(['echo'], $this->console->console));
        self::assertSame("minipavi echo: nothing to write\nUsage: minipavi echo <text>...\n", $this->console->errors());
    }

    public function testListsTheCommandsForHelp(): void
    {
        self::assertSame(ExitCode::Success, $this->application->run(['help'], $this->console->console));
        self::assertSame("Usage: minipavi <command> [<arguments>]\n\nCommands:\n  echo <text>...\n      Writes its arguments\n", $this->console->output());
    }

    public function testListsTheCommandsWithoutCommand(): void
    {
        self::assertSame(ExitCode::Usage, $this->application->run([], $this->console->console));
        self::assertStringStartsWith('Usage: minipavi', $this->console->errors());
    }

    public function testRefusesAnUnknownCommand(): void
    {
        self::assertSame(ExitCode::Usage, $this->application->run(['lnit'], $this->console->console));
        self::assertStringStartsWith("minipavi: unknown command lnit\n\nUsage: minipavi", $this->console->errors());
    }
}
