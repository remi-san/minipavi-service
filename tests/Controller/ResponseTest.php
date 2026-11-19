<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\RecordingFlow;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * What a response keeps, and what it asks the flow for: what the flow does is Service's
 * (ServiceTest)
 */
final class ResponseTest extends TestCase
{
    public function testDisplayReadsTheNextKeyWithTheDefaultParameters(): void
    {
        // null: the defaults of the controller's parameters
        self::assertSame(
            ['keep', ['state' => ControllerState::ReadInput, 'parameters' => null]],
            $this->callsOf(Response::display(new Vdt('Hello')))[0],
        );
    }

    public function testReadInputKeepsItsParameters(): void
    {
        $parameters = new PageParameters(2);

        self::assertSame(
            ['keep', ['state' => ControllerState::ReadInput, 'parameters' => $parameters]],
            $this->callsOf(Response::readInput(new Vdt('Code ?'), command: null, parameters: $parameters))[0],
        );
    }

    public function testGoToKeepsNothing(): void
    {
        // The flow resets the controller left
        self::assertSame(['goTo'], array_column($this->callsOf(Response::goTo('menu')), 0));
    }

    public function testConnectToKeepsWhatItIsGivenForWhenTheUserComesBack(): void
    {
        self::assertSame(
            ['keep', ['state' => null, 'parameters' => null]],
            $this->callsOf(Response::connectTo('https://example.org/service'))[0],
        );

        $parameters = new PageParameters(4);
        self::assertSame(
            ['keep', ['state' => ControllerState::Display, 'parameters' => $parameters]],
            $this->callsOf(Response::connectTo(
                'https://example.org/service',
                state: ControllerState::Display,
                parameters: $parameters,
            ))[0],
        );
    }

    public function testStateAndParametersCanBeGiven(): void
    {
        $parameters = new FromParameters('home');

        self::assertSame(
            ['keep', ['state' => ControllerState::Display, 'parameters' => $parameters]],
            $this->callsOf(Response::display(new Vdt('Hello'), ControllerState::Display, $parameters))[0],
        );
    }

    public function testDisplayAnswersWithItsContent(): void
    {
        [[$method, $arguments]] = $this->answersOf(Response::display(new Vdt('Hello')));

        self::assertSame(['answer', 'Hello'], [$method, $arguments['vdt']]);
        self::assertEquals(new WaitForKeyCommand(), $arguments['command']);
    }

    public function testReadInputAnswersWithTheCommand(): void
    {
        $command = new InputCommand(new InputField(new Span(new Position(2, 1), 4), validWith: [FunctionKey::Send]));

        self::assertSame(
            [['answer', ['vdt' => 'Code ?', 'command' => $command]]],
            $this->answersOf(Response::readInput(new Vdt('Code ?'), $command)),
        );
        // Without content: none sent
        $wait = new WaitForKeyCommand();
        self::assertSame(
            [['answer', ['vdt' => '', 'command' => $wait]]],
            $this->answersOf(new RunCommand(ControllerState::ReadInput, null, new Vdt(), $wait)),
        );
    }

    public function testGoToRunsTheTargetController(): void
    {
        $from = new FromParameters('home');

        self::assertSame(
            [['goTo', ['route' => 'menu', 'parameters' => $from]]],
            $this->callsOf(Response::goTo('menu', $from)),
        );
    }

    public function testConnectToAnswersWithTheOtherService(): void
    {
        [[$method, $arguments]] = $this->answersOf(Response::connectTo(
            'https://example.org/service',
            new Vdt('Cnx en cours'),
        ));

        self::assertSame(['answer', 'Cnx en cours'], [$method, $arguments['vdt']]);
        self::assertEquals(
            new ServiceCallCommand('https://example.org/service', CallMode::Connect),
            $arguments['command'],
        );
    }

    public function testRunCommandSendsAnyCommand(): void
    {
        // A direct call, as the continuation of the session: no named constructor gives one
        $command = new ServiceCallCommand('https://example.org/', CallMode::Direct);

        self::assertSame(
            [['answer', ['vdt' => '', 'command' => $command]]],
            $this->answersOf(new RunCommand(null, null, new Vdt(), $command)),
        );
    }

    /**
     * What response asks the flow for, in order
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function callsOf(Response $response): array
    {
        $flow = new RecordingFlow();
        $response->continueWith($flow);

        return $flow->calls;
    }

    /**
     * What response asks the flow for once it has kept its state and parameters
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function answersOf(Response $response): array
    {
        return array_values(array_filter($this->callsOf($response), static fn(array $call) => $call[0] !== 'keep'));
    }
}
