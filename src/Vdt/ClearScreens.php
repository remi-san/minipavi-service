<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

use MiniPaviService\Vdt\Playing\Parser;
use MiniPaviService\Vdt\Playing\Token\Token;

/**
 * What the clear screens (FF) of Videotex content do to it: what still shows after the last
 * one, or the content without them, to draw it over other content
 */
final readonly class ClearScreens
{
    public function __construct(
        private Parser $parser = new Parser(),
    ) {}

    /**
     * The content from its last clear screen, which erases what comes before it but row 0:
     * what still shows, without the content drawn over since
     */
    public function fromLast(Vdt $vdt): Vdt
    {
        return new Vdt(array_reduce(
            $this->parser->parse($vdt->toString()),
            static fn(string $content, Token $sequence) => $sequence->appendTo($content),
            initial: '',
        ));
    }

    /**
     * The content without clearing the screen, to be drawn over other content: its clear
     * screens only bring the cursor home, as RS
     */
    public function removed(Vdt $vdt): Vdt
    {
        return new Vdt(implode('', array_map(
            static fn(Token $sequence) => $sequence->drawnOver(),
            $this->parser->parse($vdt->toString()),
        )));
    }
}
