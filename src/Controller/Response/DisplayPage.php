<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;

final readonly class DisplayPage extends Response
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
    ) {
        parent::__construct($state, $parameters);
    }

    public function execute(Context $context): GatewayAnswer
    {
        return new GatewayAnswer($this->vdt, $context->serialize());
    }
}
