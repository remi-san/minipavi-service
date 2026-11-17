<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Gateway\GatewayRequest;

/**
 * Client giving a request (or failing to read it), and keeping the answers sent in $sent
 */
final class MemoryClient implements Client
{
    /** @var list<GatewayAnswer> */
    public array $sent = [];

    public function __construct(
        private GatewayRequest|\Throwable $request,
        private ?\Throwable $sendFailure = null,
    ) {}

    public function receive(): GatewayRequest
    {
        return $this->request instanceof \Throwable ? throw $this->request : $this->request;
    }

    public function send(GatewayAnswer $answer): void
    {
        if ($this->sendFailure !== null) {
            throw $this->sendFailure;
        }
        $this->sent[] = $answer;
    }
}
