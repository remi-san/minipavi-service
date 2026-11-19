<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Io\FileReader;

/**
 * Files in memory, by path, instead of on disk; records the paths read, in $read
 */
final class MemoryFiles implements FileReader
{
    /** @var list<string> paths read, in order */
    public array $read = [];

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
}
