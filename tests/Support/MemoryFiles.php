<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\FileWriter;

/**
 * Files in memory, by path, instead of on disk; records the paths read, in $read, and the
 * files written, in $written
 */
final class MemoryFiles implements FileReader, FileWriter
{
    /** @var list<string> paths read, in order */
    public array $read = [];

    /** @var array<string, string> content written, by path */
    public array $written = [];

    /**
     * @param array<string, string> $files content by path
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
}
