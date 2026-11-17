<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Response;

/**
 * Sends Videotex content to the gateway; the next key comes back to this controller
 */
final readonly class DisplayPage extends Response
{
    /**
     * @param array<string, mixed> $parameters
     * @param string $vdt Videotex content
     */
    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
    ) {
        parent::__construct($state, $parameters);
    }
}
