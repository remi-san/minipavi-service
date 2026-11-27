<?php

declare(strict_types=1);

namespace MiniPaviService\Io;

/**
 * Reads, writes and finds files on disk without PHP's warning when it can't (a missing or
 * unreadable file, a folder that doesn't exist): the caller handles the failure, with its own
 * error. The FileReader, FileWriter and FileFinder the framework uses by default.
 */
final readonly class Files implements FileReader, FileWriter, FileFinder
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
     * The file at the path, whatever its extension, or the files with the extension (in any
     * case) of the folder at the path and its subfolders, sorted, a link to a folder being
     * skipped; null if there is nothing at the path
     *
     * @return list<string>|null
     */
    public function find(string $path, string $extension): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        if (!is_dir($path)) {
            return [$path];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $path,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (
                !(
                    $file instanceof \SplFileInfo
                    && $file->isFile()
                    && strcasecmp($file->getExtension(), $extension) === 0
                )
            ) {
                continue;
            }

            $files[] = $file->getPathname();
        }
        sort($files);

        return $files;
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
