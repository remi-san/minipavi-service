<?php

declare(strict_types=1);

namespace MiniPaviService\Client\Html;

/**
 * HTML of the browser client's page (its screen, HtmlRenderer, and the page around it,
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
