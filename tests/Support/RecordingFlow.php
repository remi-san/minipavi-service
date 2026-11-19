<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\Command;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * Flow recording what a response asks for, in $calls, and responding with an empty context
 */
final class RecordingFlow implements Flow
{
    /** @var list<array{string, array<string, mixed>}> method and arguments of each call */
    public array $calls = [];

    public function keep(?State $state, ?Parameters $parameters): void
    {
        $this->calls[] = ['keep', ['state' => $state, 'parameters' => $parameters]];
    }

    public function respond(Vdt $vdt, Command $command = new WaitForKeyCommand()): GatewayResponse
    {
        $this->calls[] = ['respond', ['vdt' => $vdt->toString(), 'command' => $command]];

        return new GatewayResponse($vdt, '{}');
    }

    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse
    {
        $this->calls[] = ['goTo', ['route' => $route, 'parameters' => $parameters]];

        return new GatewayResponse(new Vdt(''), '{}');
    }
}
