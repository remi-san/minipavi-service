<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

/**
 * PCRE settings under which a regular expression that has to backtrack fails ("Backtrack limit
 * exhausted"): no JIT, and a backtrack limit of 0. restore() puts the previous settings back, in
 * a finally block so that a failing test doesn't leave them on.
 */
final readonly class ExhaustedBacktrackLimit
{
    private function __construct(
        private string $jit,
        private string $backtrackLimit,
    ) {}

    /**
     * Turns the settings on, remembering the previous ones
     */
    public static function exhaust(): self
    {
        $limit = new self((string) ini_get('pcre.jit'), (string) ini_get('pcre.backtrack_limit'));
        ini_set('pcre.jit', value: '0');
        ini_set('pcre.backtrack_limit', value: '0');

        return $limit;
    }

    /**
     * Puts the previous settings back
     */
    public function restore(): void
    {
        ini_set('pcre.jit', $this->jit);
        ini_set('pcre.backtrack_limit', $this->backtrackLimit);
    }
}
