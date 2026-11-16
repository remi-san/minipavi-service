<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

final readonly class RunExternalService extends Response
{
    /** @deprecated use CallMode::Connect */
    public const CALL_MODE_CONNECT = CallMode::Connect->value;

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

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt, $this->url, $context, true, null, $this->callMode->value);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
