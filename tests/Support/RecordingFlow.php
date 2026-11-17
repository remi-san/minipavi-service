<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * Flow recording what a response asks for, in $calls, and answering with an empty context
 */
final class RecordingFlow implements Flow
{
    /** @var list<array{string, array<string, mixed>}> method and arguments of each call */
    public array $calls = [];

    public function answer(Vdt $vdt, ?InputCommand $command = null, ?string $next = null, ?CallMode $directCall = null): GatewayAnswer
    {
        $this->calls[] = ['answer', ['vdt' => $vdt->toString(), 'command' => $command, 'next' => $next, 'directCall' => $directCall]];

        return new GatewayAnswer($vdt, '{}');
    }

    public function goTo(string $route, ?Parameters $parameters = null): GatewayAnswer
    {
        $this->calls[] = ['goTo', ['route' => $route, 'parameters' => $parameters]];

        return new GatewayAnswer(new Vdt(''), '{}');
    }
}
