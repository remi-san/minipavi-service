<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\ScreenRows;
use PHPUnit\Framework\TestCase;

/**
 * The files a page reads: the page itself, and its Videotex files
 */
final class PageFilesTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testTemplateReadsEachImageOnce(): void
    {
        $files = new MemoryFiles([
            '/pages/home.xml' => '<page type="template"><image row="{{row}}">images/logo.vdt</image></page>',
            '/pages/images/logo.vdt' => 'ok',
        ]);
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults(), files: $files))->parseFile('/pages/home.xml');

        $page->display(new Terminal(), ['row' => 3]);
        $vdt = $page->display(new Terminal(), ['row' => 5]);

        self::assertSame([' 5|ok                                      |'], ScreenRows::drawn($vdt));
        self::assertSame(['/pages/home.xml', '/pages/images/logo.vdt'], $files->read);
    }

    public function testReadsThePageAndItsVideotexFilesThroughItsFileReader(): void
    {
        $files = new MemoryFiles([
            '/pages/home.xml' => '<page type="static"><image row="3" col="2">images/logo.vdt</image></page>',
            '/pages/images/logo.vdt' => 'ok',
        ]);
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), files: $files);

        $page = $parser->parseFile('/pages/home.xml');

        self::assertSame(
            [' 3| ok                                     |'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
        self::assertSame(['/pages/home.xml', '/pages/images/logo.vdt'], $files->read);
    }

    public function testPageItsFileReaderDoesntHaveIsNotFound(): void
    {
        $parser = new PageXmlParser((new MessagesXmlParser())->defaults(), files: new MemoryFiles([]));

        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('/pages/home.xml: file not found');

        $parser->parseFile('/pages/home.xml');
    }

    public function testParseFileReadsFilesFromTheFolderOfThePage(): void
    {
        $page = $this->pages->file(ParsedPages::FOLDER . '/logo.xml');

        self::assertSame(
            [' 1|##                                      |', ' 2|ok                                      |'],
            ScreenRows::drawn($page->display(new Terminal())),
        );
    }

    public function testMissingImageGivesThePageAndLine(): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage(
            'f.xml line 2: <image>: Videotex file not found: ' . ParsedPages::FOLDER . '/images/missing.vdt',
        );

        $this->pages->parse("<page type=\"static\">\n<image>images/missing.vdt</image></page>", name: 'f.xml');
    }

    public function testParseFileThrowsWhenTheFileIsMissing(): void
    {
        $this->expectException(PageXmlException::class);
        $this->expectExceptionMessage('missing.xml: file not found');

        $this->pages->file(ParsedPages::FOLDER . '/missing.xml');
    }

    public function testTemplateFileOutsideTheFolderIsRefusedAtDisplay(): void
    {
        $page = $this->pages->parse('<page type="template"><image>{{file}}</image></page>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage("only files from the page's folder or its subfolders");

        $page->display(new Terminal(), ['file' => 'images/../../secret.vdt']);
    }
}
