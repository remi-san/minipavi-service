<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client\Html;

use MiniPaviService\Client\Html\Html;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'markup' => ['<a href="x">&</a>', '&lt;a href=&quot;x&quot;&gt;&amp;&lt;/a&gt;'];
        // Single quotes too, as HTML5 names them: safe in any attribute
        yield 'a single quote' => ["l'été", 'l&apos;été'];
        // Not UTF-8: replaced, not dropped (the whole text would be)
        yield 'invalid UTF-8' => ["a\xFFb", "a\u{FFFD}b"];
    }

    #[DataProvider('texts')]
    public function testEscapesTextForContentAndAttributes(string $text, string $escaped): void
    {
        self::assertSame($escaped, Html::escape($text));
    }
}
