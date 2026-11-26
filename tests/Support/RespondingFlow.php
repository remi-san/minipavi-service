<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\Command\Command;
use MiniPaviService\Gateway\Command\WaitForKeyCommand;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Vdt\Vdt;

/**
 * Flow responding with what a response sends, and an empty context, keeping nothing: to read
 * the content of a response without a service (what the service does with it: ServiceTest)
 *
 * @implements Flow<State>
 */
final class RespondingFlow implements Flow
{
    public function keep(?State $state, ?Parameters $parameters): void {}

    public function respond(Vdt $vdt, Command $command = new WaitForKeyCommand()): GatewayResponse
    {
        return new GatewayResponse($vdt, '{}', $command);
    }

    public function goTo(string $route, ?Parameters $parameters = null): GatewayResponse
    {
        return new GatewayResponse(new Vdt(''), '{}');
    }
}
