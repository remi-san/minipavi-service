<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Io\FileFinder;
use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\FileWriter;

/**
 * Files in memory, by path, instead of on disk, in folders by the "/" of their paths; records
 * the paths read, in $read, and the files written, in $written
 */
final class MemoryFiles implements FileReader, FileWriter, FileFinder
{
    /** @var list<string> paths read, in order */
    public array $read = [];

    /** @var array<string, string> content written, by path */
    public array $written = [];

    /**
     * @param array<string, ?string> $files content by path, null for a file that can't be read
     */
    public function __construct(
        private readonly array $files,
    ) {}

    public function read(string $file): ?string
    {
        $this->read[] = $file;

        return $this->files[$file] ?? null;
    }

    public function write(string $file, string $content): bool
    {
        $this->written[$file] = $content;

        return true;
    }

    /**
     * @return list<string>|null
     */
    public function find(string $path, string $extension): ?array
    {
        if (\array_key_exists($path, $this->files)) {
            return [$path];
        }
        $folder = rtrim($path, characters: '/') . '/';
        $inFolder = array_values(array_filter(
            array_keys($this->files),
            static fn(string $file): bool => str_starts_with($file, $folder),
        ));
        if ($inFolder === []) {
            return null;
        }
        $found = array_values(array_filter(
            $inFolder,
            static fn(string $file): bool => strcasecmp(pathinfo($file, PATHINFO_EXTENSION), $extension) === 0,
        ));
        sort($found);

        return $found;
    }
}
