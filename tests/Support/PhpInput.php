<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

/**
 * The body of the HTTP request PHP received (php://input), which the CLI doesn't have: read()
 * runs a function with php:// streams opening the body given instead, as the body MiniPavi
 * posts to the service. It stands for every php:// stream while it runs, then gives them back.
 */
final class PhpInput
{
    /** Set by PHP on the streams it opens */
    public mixed $context = null;

    private static string $body = '';

    private int $position = 0;

    /**
     * What the function returns, with php://input giving the body
     *
     * @template T
     *
     * @param \Closure(): T $read
     *
     * @return T
     */
    public static function read(string $body, \Closure $read): mixed
    {
        self::$body = $body;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
        try {
            return $read();
        } finally {
            stream_wrapper_restore('php');
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $read = substr(self::$body, $this->position, $count);
        $this->position += \strlen($read);

        return $read;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$body);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => \strlen(self::$body)];
    }
}
