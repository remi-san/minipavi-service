<?php

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;
use MiniPaviService\Controller\Response;

class RunExternalService implements Response {

    public const CALL_MODE_CONNECT = 'yes-cnx';

    public function __construct(
        public string $vdt,
        public string $url,
        public string $callMode
    ) {}

    public function execute(MiniPaviClient $client, Context $context): void {
        $client->send($this->vdt, $this->url, $context, true, null, $this->callMode);
    }

    public function interrupts(): bool {
        return true;
    }
}
