<?php

declare(strict_types=1);

namespace MiniPaviService\Log;

/**
 * PHP's error log (error_log()): the web server's or PHP-FPM's log, e.g. docker compose logs
 * services
 */
final readonly class PhpErrorLog implements LogLines
{
    public function write(string $line): void
    {
        error_log($line);
    }
}
