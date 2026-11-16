<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

readonly class RunExternalService implements Response
{
    use ResponseTrait;

    public const CALL_MODE_CONNECT = 'yes-cnx';

    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
        public string $url,
        public string $callMode,
    ) {
        $this->init($state, $parameters);
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
