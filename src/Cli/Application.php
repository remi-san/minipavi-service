<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * The bin/minipavi command line: runs the command named by the first argument with the
 * others. Without a command, or with help, lists the commands.
 */
final readonly class Application
{
    private const NAME = 'minipavi';

    /** @var array<string, Command> by name */
    private array $commands;

    public function __construct(Command ...$commands)
    {
        $byName = [];
        foreach ($commands as $command) {
            $byName[$command->name()] = $command;
        }
        $this->commands = $byName;
    }

    /**
     * @param list<string> $arguments the command line, without the script
     */
    public function run(array $arguments, Console $console): ExitCode
    {
        $name = $arguments[0] ?? '';
        if (\in_array($name, ['help', '--help', '-h'], true)) {
            $console->write($this->help());

            return ExitCode::Success;
        }
        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            $console->error(($name === '' ? '' : self::NAME . ": unknown command $name\n\n") . $this->help());

            return ExitCode::Usage;
        }

        try {
            return $command->run(\array_slice($arguments, 1), $console);
        } catch (UsageException $e) {
            $console->error(self::NAME . " {$command->name()}: {$e->getMessage()}\nUsage: " . self::NAME . " {$command->name()} {$command->usage()}\n");

            return ExitCode::Usage;
        }
    }

    private function help(): string
    {
        $help = 'Usage: ' . self::NAME . " <command> [<arguments>]\n\nCommands:\n";
        foreach ($this->commands as $name => $command) {
            $help .= "  $name {$command->usage()}\n      {$command->description()}\n";
        }

        return $help;
    }
}
