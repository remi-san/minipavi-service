<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayAnswer;

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

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->answer($this->vdt);
    }
}
