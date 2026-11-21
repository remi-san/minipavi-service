<?php

declare(strict_types=1);

namespace MiniPaviService\Io;

/**
 * Writes the files the framework produces: the moved Videotex page of `minipavi vdt-offset`
 * (VdtOffsetCommand). Files writes them to disk; another writer can keep them elsewhere
 * (memory in tests).
 */
interface FileWriter
{
    /**
     * Writes the content to the file; false if it can't
     */
    public function write(string $file, string $content): bool;
}
