<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Io\Files;

/**
 * Videotex files of a page (<background>, <image>): in its folder or its subfolders only,
 * since in a template the file name may come from the data
 */
final readonly class PageFiles
{
    /**
     * @param string $baseDir folder of the page
     */
    public function __construct(
        private string $baseDir,
    ) {}

    /**
     * Path of a file named relative to the page's folder (e.g. images/logo.vdt)
     *
     * @throws PageXmlException for no file, or one outside the page's folder
     */
    public function path(string $file): string
    {
        $file = trim($file);
        if ($file === '') {
            throw new PageXmlException('the file is missing');
        }
        if (
            str_starts_with($file, '/')
            || \in_array('..', explode('/', str_replace('\\', replace: '/', subject: $file)), strict: true)
        ) {
            throw new PageXmlException("{$file}: only files from the page's folder or its subfolders");
        }

        return $this->baseDir . '/' . $file;
    }

    /**
     * Videotex content of a file named relative to the page's folder
     *
     * @throws PageXmlException for no file, or one outside the page's folder
     * @throws \InvalidArgumentException if it can't be read
     */
    public function read(string $file): string
    {
        $path = $this->path($file);

        return Files::read($path) ?? throw new \InvalidArgumentException("Videotex file not found: {$path}");
    }
}
