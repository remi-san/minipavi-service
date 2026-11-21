<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Io\FileReader;
use MiniPaviService\Io\Files;
use MiniPaviService\Page\Building\PageXmlException;

/**
 * Videotex files of a page (<background>, <image>): in its folder or its subfolders only,
 * since in a template the file name may come from the data
 */
final readonly class PageFiles
{
    /**
     * @param string $baseDir folder of the page
     * @param FileReader $files reads the Videotex files
     */
    public function __construct(
        private string $baseDir,
        private FileReader $files = new Files(),
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
        // Either separator, as on Windows
        $path = str_replace('\\', replace: '/', subject: $file);
        if (
            str_starts_with($path, '/')
            // A Windows drive (C:/..., or C:... relative to its current folder)
            || preg_match('/^[a-z]:/i', $path) === 1
            || \in_array('..', explode('/', $path), strict: true)
        ) {
            throw new PageXmlException("{$file}: only files from the page's folder or its subfolders");
        }

        return $this->baseDir . '/' . $file;
    }

    /**
     * Videotex content of a file named relative to the page's folder
     *
     * @throws PageXmlException for no file, one outside the page's folder, or one that can't be
     *                          read
     */
    public function read(string $file): string
    {
        $path = $this->path($file);

        return $this->files->read($path) ?? throw new PageXmlException("Videotex file not found: {$path}");
    }
}
