<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Controller\Response;

class DisplayPage implements Response {
    public function __construct(public string $vdt) {
    }

    public function execute(MiniPaviClient $client, Context $context): void {
        $client->send($this->vdt, null, $context, true, null, false);
    }

    public function interrupts(): bool {
        return true;
    }
}
