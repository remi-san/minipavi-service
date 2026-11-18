<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Display;

use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

/**
 * Content of a screen, as a list of elements drawn in order, convertible to Videotex.
 * Its elements are those of the page XML format (documented in the framework's README.md, read by PageXmlParser).
 */
final readonly class Display
{
    /** @var Element[] */
    public array $elements;

    public function __construct(Element ...$elements)
    {
        $this->elements = $elements;
    }

    /**
     * Videotex content drawing the elements, without clearing the screen first
     */
    public function toVdt(Terminal $terminal): Vdt
    {
        return Vdt::join(...array_map(static fn(Element $element) => $element->toVdt($terminal), $this->elements));
    }
}
