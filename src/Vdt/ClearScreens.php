<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt;

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
        $tokens = $this->parser->parse($vdt->toString());
        $start = 0;
        foreach ($tokens as $i => $token) {
            if ($token->type !== TokenType::Clear) {
                continue;
            }

            $start = $i;
        }

        return new Vdt(implode('', array_map(
            static fn(Token $sequence) => $sequence->source,
            \array_slice($tokens, $start),
        )));
    }

    /**
     * The content without clearing the screen, to be drawn over other content: its clear
     * screens only bring the cursor home, as RS
     */
    public function removed(Vdt $vdt): Vdt
    {
        return new Vdt(implode('', array_map(static fn(Token $sequence) => $sequence->type === TokenType::Clear
            ? Videotex::HOME
            : $sequence->source, $this->parser->parse($vdt->toString()))));
    }
}
