<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * Exit status of a command
 */
enum ExitCode: int
{
    case Success = 0;
    // The command ran, and found errors (e.g. in pages), or couldn't read or write a file
    case Failure = 1;
    // The command line is wrong: unknown command, option or argument
    case Usage = 2;
}
