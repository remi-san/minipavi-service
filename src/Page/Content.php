<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\TemplateException;

/**
 * What a MinitelPage draws: always the same (StaticContent), or a template drawn with data
 * (TemplateContent)
 */
interface Content
{
    /**
     * Elements drawn for the variables of a template
     *
     * @param array<string, mixed> $data
     *
     * @throws TemplateException if a template can't be drawn with these
     */
    public function display(array $data): Display;
}
