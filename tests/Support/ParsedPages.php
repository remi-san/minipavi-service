<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Page;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use PHPUnit\Framework\Assert;

/**
 * Pages of the fixtures folder, parsed from XML with the framework's messages
 */
final readonly class ParsedPages
{
    public const FOLDER = __DIR__ . '/../fixtures/pages';

    private PageXmlParser $parser;

    public function __construct()
    {
        $this->parser = new PageXmlParser((new MessagesXmlParser())->defaults());
    }

    /**
     * The page, read from the fixtures folder
     */
    public function parse(string $xml, string $name = 'page'): Page
    {
        return $this->parser->parse($xml, self::FOLDER, $name);
    }

    /**
     * The page of the XML file
     */
    public function file(string $path): Page
    {
        return $this->parser->parseFile($path);
    }

    /**
     * Error parsing the page, named f.xml
     */
    public function error(string $xml): PageXmlException
    {
        try {
            $this->parser->parse($xml, self::FOLDER, name: 'f.xml');
        } catch (PageXmlException $e) {
            return $e;
        }

        Assert::fail('The page was parsed');
    }
}
