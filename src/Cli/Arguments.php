<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * Command line of a command, read with the options it accepts: flags (`--preview`), options
 * with a value (`--messages <file>`), and the other arguments, in their order. Options start
 * with --: "-" (the standard input) and negative numbers are arguments.
 */
final readonly class Arguments
{
    /**
     * @param list<string> $flags names of the flags set
     * @param array<string, string> $values value of each option given
     * @param list<string> $positional the other arguments
     */
    private function __construct(
        private array $flags,
        private array $values,
        public array $positional,
    ) {}

    /**
     * @param list<string> $arguments
     * @param list<string> $flags names of the flags accepted, without --
     * @param list<string> $options names of the options with a value accepted, without --
     *
     * @throws UsageException for an unknown option, or an option without its value
     */
    public static function parse(array $arguments, array $flags = [], array $options = []): self
    {
        $set = [];
        $values = [];
        $positional = [];
        for ($i = 0; $i < \count($arguments); $i++) {
            $argument = $arguments[$i];
            if (!str_starts_with($argument, '--')) {
                $positional[] = $argument;
                continue;
            }
            $name = substr($argument, 2);
            if (\in_array($name, $flags, true)) {
                $set[] = $name;
            } elseif (\in_array($name, $options, true)) {
                $values[$name] = $arguments[++$i] ?? throw new UsageException("$argument needs a value");
            } else {
                throw new UsageException("unknown option $argument");
            }
        }

        return new self($set, $values, $positional);
    }

    public function flag(string $name): bool
    {
        return \in_array($name, $this->flags, true);
    }

    /**
     * Value of an option, or default when it isn't given
     */
    public function value(string $name, string $default): string
    {
        return $this->values[$name] ?? $default;
    }
}
