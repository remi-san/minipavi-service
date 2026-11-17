<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;

final readonly class RunExternalService extends Response
{
    public CallMode $callMode;

    /**
     * @param array<string, mixed> $parameters
     * @param CallMode|string $callMode a CallMode, or its value ('yes-cnx', 'yes')
     */
    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
        public string $url,
        CallMode|string $callMode,
    ) {
        parent::__construct($state, $parameters);
        $this->callMode = \is_string($callMode) ? CallMode::from($callMode) : $callMode;
    }

    public function execute(Context $context): GatewayAnswer
    {
        return new GatewayAnswer($this->vdt, $context->serialize(), $this->url, directCall: $this->callMode);
    }
}
