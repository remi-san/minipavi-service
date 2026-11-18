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
     * The builders of the elements, for a page. Called once more when PageXmlParser is built, to
     * check their tags: building them must not read the files.
     *
     * @param PageFiles $files the files of the page's folder (e.g. for an element drawing a file)
     * @param Messages $messages the page's messages: the service's, with the page's <message>
     *
     * @return array<ElementBuilder> its keys are ignored
     */
    public function builders(PageFiles $files, Messages $messages): array;
}
