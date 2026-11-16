<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\Controller\Response;
use MiniPaviService\MiniPaviClient;

class DisplayPage implements Response
{
    use ResponseTrait;

    public function __construct(
        string $state,
        array $parameters,
        public string $vdt,
    ) {
        $this->init($state, $parameters);
    }

    public function execute(MiniPaviClient $client, Context $context): void
    {
        $client->send($this->vdt, null, $context, true, null, false);
    }

    public function interrupts(): bool
    {
        return true;
    }
}
