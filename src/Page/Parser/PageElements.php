<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\Messages;
use MiniPaviService\Page\Parser\Element\ElementBuilder;

/**
 * Display elements of the page XML format, built for each page: the framework's
 * (StandardElements), and those a service adds by giving its own to PageXmlParser
 */
interface PageElements
{
    /**
     * The builders of the elements, for a page
     *
     * @param PageFiles $files the files of the page's folder (e.g. for an element drawing a file)
     * @param Messages $messages the page's messages: the service's, with the page's <message>
     *
     * @return list<ElementBuilder>
     */
    public function builders(PageFiles $files, Messages $messages): array;
}
