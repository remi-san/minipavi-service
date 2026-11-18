<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser\Tag;

use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Page\Parser\ElementFactory;
use MiniPaviService\Page\Parser\PageFiles;
use MiniPaviService\Page\Parser\Tag\IfTagParser;
use MiniPaviService\Page\Parser\Tag\NodesParser;
use MiniPaviService\Tests\Support\StarsElements;
use PHPUnit\Framework\TestCase;

final class NodesParserTest extends TestCase
{
    public function testDisplayElementTakesNoTagOfItsParsers(): void
    {
        $elements = ElementFactory::of(
            new PageFiles(__DIR__),
            (new MessagesXmlParser())->defaults(),
            new StarsElements('if'),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/^home\.xml: <if> \(.+\) is a tag of the page format/');

        new NodesParser(['if' => new IfTagParser()], $elements, 'home.xml');
    }
}
