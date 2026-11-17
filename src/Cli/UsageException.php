<?php

declare(strict_types=1);

namespace MiniPaviService\Cli;

/**
 * Wrong command line for a command (missing argument, unknown option...): Application shows
 * the message and the command's usage, and exits with ExitCode::Usage
 */
final class UsageException extends \InvalidArgumentException {}
