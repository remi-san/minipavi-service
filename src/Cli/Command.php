<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * Command of bin/minipavi (Application), e.g. `minipavi lint config/pages`
 */
interface Command
{
    /**
     * Name typed after minipavi, e.g. lint
     */
    public function name(): string;

    /**
     * Its options and arguments, e.g. `[--messages <messages.xml>] <path>...`
     */
    public function usage(): string;

    /**
     * What it does, in one line
     */
    public function description(): string;

    /**
     * @param list<string> $arguments the command line after its name
     *
     * @throws UsageException for a wrong command line
     */
    public function run(array $arguments, Console $console): ExitCode;
}
