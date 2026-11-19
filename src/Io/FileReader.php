<?php

declare(strict_types=1);

namespace MiniPaviService\Io;

/**
 * Reads the files the framework needs: page XML and its Videotex files (PageXmlParser,
 * PageFiles), messages files (MessagesXmlParser), the font of the HTML client
 * (HtmlRenderer). Files reads them from disk; another reader can read them from elsewhere
 * (memory in tests, a cache).
 */
interface FileReader
{
    /**
     * Content of the file (or of a stream, e.g. php://stdin); null if it can't be read
     */
    public function read(string $file): ?string;
}
