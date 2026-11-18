<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Page\Content;
use MiniPaviService\Page\StaticContent;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Page\TemplateContent;

/**
 * The type attribute of <page>: what the page may hold, and what its content is
 */
enum PageType: string
{
    // Drawn as it is: its elements built when it is parsed
    case Static = 'static';
    // With {{placeholders}} and instructions (<foreach>, <if>), filled in when displayed
    case Template = 'template';

    /**
     * The type of a <page>
     *
     * @param string $name of the file, in errors
     *
     * @throws PageXmlException for a type missing or unknown
     */
    public static function of(string $type, string $name): self
    {
        return (
            self::tryFrom($type) ?? throw new PageXmlException(
                "{$name}: <page>: "
                . ($type === '' ? 'type is missing' : "type=\"{$type}\" is unknown")
                . ' (type="'
                . implode('" or type="', array_map(static fn(self $case) => $case->value, self::cases()))
                . '")',
            )
        );
    }

    /**
     * Whether the page may have {{placeholders}} and instructions
     */
    public function isTemplate(): bool
    {
        return $this === self::Template;
    }

    /**
     * The content of a page of this type, from the template of its nodes
     *
     * @throws PageXmlException for a static page's element that can't be built
     */
    public function content(TemplateContent $template): Content
    {
        if ($this === self::Template) {
            return $template;
        }
        // Static page: elements are built now, so that their errors are found now
        try {
            return new StaticContent($template->display([]));
        } catch (TemplateException $e) {
            throw new PageXmlException($e->getMessage(), 0, $e);
        }
    }
}
