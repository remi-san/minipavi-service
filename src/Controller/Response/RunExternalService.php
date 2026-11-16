<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

final readonly class RunExternalService extends Response
{
    public const CALL_MODE_CONNECT = 'yes-cnx';

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
        public string $url,
        public string $callMode,
    ) {
        parent::__construct($state, $parameters);
    }

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt, $this->url, $context, true, null, $this->callMode);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
