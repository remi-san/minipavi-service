<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Controller\Response;

class RunCommand implements Response {
    use ResponseTrait;

    public function __construct(
        string $state,
        array $parameters,
        private ?string $vdt,
        private array $cmd
    ) {
        $this->init($state, $parameters);
    }

    public function execute(MiniPaviClient $client, Context $context): void {
        $client->send($this->vdt ?? '', null, $context, true, $this->cmd, false);
    }

    public function interrupts(): bool {
        return true;
    }
}
