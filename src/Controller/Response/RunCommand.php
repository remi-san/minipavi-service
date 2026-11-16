<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Controller\Response;

class RunCommand implements Response {
    public function __construct(
        private ?string $vdt,
        private array $cmd
    ) {}

    public function execute(MiniPaviClient $client, Context $context): void {
        $client->send($this->vdt ?? '', null, $context, true, $this->cmd, false);
    }

    public function interrupts(): bool {
        return true;
    }
}
