<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

/**
 * The type attribute of <page> (read by PageSettings, as its other attributes): what the
 * page may hold
 */
enum PageType: string
{
    // Drawn as it is: its elements built when it is parsed
    case Static = 'static';
    // With {{placeholders}} and instructions (<foreach>, <if>), filled in when displayed
    case Template = 'template';

    /**
     * Whether the page may have {{placeholders}} and instructions, filled in when displayed
     */
    public function isTemplate(): bool
    {
        return $this === self::Template;
    }
}
