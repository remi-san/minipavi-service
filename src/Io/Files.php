<?php

declare(strict_types=1);

namespace MiniPaviService\Io;

/**
 * Reads and writes files on disk without PHP's warning when it can't (a missing or
 * unreadable file, a folder that doesn't exist): the caller handles the failure, with its own
 * error. The FileReader the framework uses by default.
 */
final readonly class Files implements FileReader
{
    /**
     * Content of the file (or of a stream, e.g. php://stdin); null if it can't be read
     */
    public function read(string $file): ?string
    {
        $content = self::silently(static fn() => file_get_contents($file));

        return $content === false ? null : $content;
    }

    /**
     * Writes the content to the file; false if it can't
     */
    public function write(string $file, string $content): bool
    {
        return self::silently(static fn() => file_put_contents($file, $content)) !== false;
    }

    /**
     * Result of the call, PHP's warnings while it runs being ignored
     *
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     */
    private static function silently(\Closure $call): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}
