<?php

declare(strict_types=1);

namespace MiniPaviService\Io;

/**
 * Finds the files a path names: the pages of `minipavi lint` (LintCommand). Files finds them
 * on disk; another finder can find them elsewhere (memory in tests).
 */
interface FileFinder
{
    /**
     * The file at the path, whatever its extension, or the files with the extension (in any
     * case, e.g. "xml" for MENU.XML) of the folder at the path and its subfolders, sorted;
     * null if there is nothing at the path
     *
     * @return list<string>|null
     */
    public function find(string $path, string $extension): ?array;
}
