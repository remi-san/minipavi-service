<?php

declare(strict_types=1);

namespace MiniPaviService\Log;

/**
 * Where LineLogger writes its lines: PHP's error log (PhpErrorLog), or another destination
 */
interface LogLines
{
    /**
     * A line of the log, without its end of line (it may hold several, e.g. an exception's
     * trace)
     */
    public function write(string $line): void;
}
