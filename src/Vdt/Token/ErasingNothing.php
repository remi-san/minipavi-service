<?php

declare(strict_types=1);

namespace MiniPaviService\Vdt\Token;

/**
 * A token that erases nothing (any but a clear screen): its bytes are kept as read, after
 * content as over it
 *
 * @internal
 */
abstract readonly class ErasingNothing implements Token
{
    /**
     * @param string $source the bytes as read, to write the content back unchanged
     */
    public function __construct(
        private string $source,
    ) {}

    public function appendTo(string $content): string
    {
        return $content . $this->source;
    }

    public function drawnOver(): string
    {
        return $this->source;
    }
}
