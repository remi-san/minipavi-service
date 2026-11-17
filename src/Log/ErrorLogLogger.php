<?php

declare(strict_types=1);

namespace MiniPaviService\Log;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger writing to PHP's error log (error_log()): the web server's or PHP-FPM's log,
 * e.g. docker compose logs services. A line per record: "[level] message {context}", then
 * the exception of the context, with its trace.
 */
final class ErrorLogLogger extends AbstractLogger
{
    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $exception = $context['exception'] ?? null;
        unset($context['exception']);
        $line = '[' . (\is_string($level) ? $level : get_debug_type($level)) . '] ' . (string) $message;
        if ($context !== []) {
            $line .=
                ' '
                . (string) json_encode(
                    $context,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
                );
        }
        if ($exception instanceof \Throwable) {
            $line .= "\n" . (string) $exception;
        }
        error_log($line);
    }
}
