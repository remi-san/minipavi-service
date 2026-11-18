<?php

declare(strict_types=1);

namespace MiniPaviService\Html;

/**
 * HTML of the framework's pages (the screen, HtmlRenderer, and the web client's page,
 * HtmlForm), escaped the same way
 *
 * @internal
 */
final readonly class Html
{
    /**
     * Text escaped for HTML (content and attribute values)
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, encoding: 'UTF-8');
    }
}
