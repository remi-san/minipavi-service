<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Controller\Response;

class ChangePage implements Response {
    public function __construct(public string $page) {
    }

    public function execute(MiniPaviClient $client, Context $context): void {
        $context->route = $this->page;
    }

    public function interrupts(): bool {
        return false;
    }
}