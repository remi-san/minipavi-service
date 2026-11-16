<?php

namespace MiniPaviService\Vdt;

/**
 * Part of Videotex content (see TokenType), with the bytes it was read from
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        // Bytes as read, to write the content back unchanged
        public string $source,
        public string $bytes = '',
        public int $count = 0,
        public ?string $group = null,
        public int $row = 0,
        public int $col = 0,
    ) {}
}
