<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Client\MiniPavi\MiniPaviGateway;
use MiniPaviService\Gateway\Command\CallMode;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Vdt\Vdt;

/**
 * Gateway giving a request (or failing to read it), and keeping what it is sent in $sent:
 * each response as the arguments of send(). It writes each response as "response <n>".
 */
final class MemoryMiniPaviGateway implements MiniPaviGateway
{
    /** @var list<array{Vdt, string, string, ?InputCommand, ?CallMode}> */
    public array $sent = [];

    public int $reads = 0;

    public function __construct(
        private readonly GatewayRequest|\Exception $request = new \Exception('Nothing to read'),
    ) {}

    public function read(): GatewayRequest
    {
        ++$this->reads;
        if ($this->request instanceof \Exception) {
            throw $this->request;
        }

        return $this->request;
    }

    public function send(
        Vdt $vdt,
        string $next,
        string $context,
        ?InputCommand $input = null,
        ?CallMode $directCall = null,
    ): string {
        $this->sent[] = [$vdt, $next, $context, $input, $directCall];

        return 'response ' . \count($this->sent);
    }
}
