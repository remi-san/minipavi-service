<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
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
use ReflectionParameter;

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
            $this->callsOf(new DisplayPage(new Vdt('Hello')))[0],
        );
    }

    public function testReadInputKeepsItsParameters(): void
    {
        $parameters = new PageParameters(2);

        self::assertSame(
            ['keep', ['state' => ControllerState::ReadInput, 'parameters' => $parameters]],
            $this->callsOf(RunCommand::readInput(new Vdt('Code ?'), command: null, parameters: $parameters))[0],
        );
    }

    public function testGoToKeepsNothing(): void
    {
        // The flow resets the controller left
        self::assertSame(['goTo'], array_column($this->callsOf(new ChangePage('menu')), 0));
    }

    public function testConnectToKeepsWhatItIsGivenForWhenTheUserComesBack(): void
    {
        self::assertSame(
            ['keep', ['state' => null, 'parameters' => null]],
            $this->callsOf(RunCommand::connectTo('https://example.org/service'))[0],
        );

        $parameters = new PageParameters(4);
        self::assertSame(
            ['keep', ['state' => ControllerState::Display, 'parameters' => $parameters]],
            $this->callsOf(RunCommand::connectTo(
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
            $this->callsOf(new DisplayPage(new Vdt('Hello'), ControllerState::Display, $parameters))[0],
        );
    }

    public function testRunCommandKeepsTheStateItIsGiven(): void
    {
        $command = new InputCommand(new InputField(new Span(new Position(2, 1), 4), validWith: [FunctionKey::Send]));

        self::assertSame(
            ['keep', ['state' => ControllerState::ReadInput, 'parameters' => null]],
            $this->callsOf(new RunCommand(new Vdt('Code ?'), $command, ControllerState::ReadInput))[0],
        );
        self::assertSame(
            ['keep', ['state' => null, 'parameters' => null]],
            $this->callsOf(new RunCommand(new Vdt(), new WaitForKeyCommand(), null))[0],
        );
    }

    public function testRunCommandBuiltDirectlyIsGivenItsState(): void
    {
        // no default fits every command: an InputCommand needs ReadInput, or what is typed is
        // dropped
        self::assertFalse((new ReflectionParameter([RunCommand::class, '__construct'], 'state'))->isOptional());
    }

    public function testDisplayCanKeepTheInitialState(): void
    {
        self::assertSame(
            ['keep', ['state' => null, 'parameters' => null]],
            $this->callsOf(new DisplayPage(new Vdt('Hello'), null))[0],
        );
    }

    public function testDisplayRespondsWithItsContent(): void
    {
        [[$method, $arguments]] = $this->respondCallsOf(new DisplayPage(new Vdt('Hello')));

        self::assertSame(['respond', 'Hello'], [$method, $arguments['vdt']]);
        self::assertEquals(new WaitForKeyCommand(), $arguments['command']);
    }

    public function testReadInputRespondsWithTheCommand(): void
    {
        $command = new InputCommand(new InputField(new Span(new Position(2, 1), 4), validWith: [FunctionKey::Send]));

        self::assertSame(
            [['respond', ['vdt' => 'Code ?', 'command' => $command]]],
            $this->respondCallsOf(RunCommand::readInput(new Vdt('Code ?'), $command)),
        );
        // Without content: none sent
        $wait = new WaitForKeyCommand();
        self::assertSame(
            [['respond', ['vdt' => '', 'command' => $wait]]],
            $this->respondCallsOf(new RunCommand(new Vdt(), $wait, ControllerState::ReadInput)),
        );
    }

    public function testGoToRunsTheTargetController(): void
    {
        $from = new FromParameters('home');

        self::assertSame(
            [['goTo', ['route' => 'menu', 'parameters' => $from]]],
            $this->callsOf(new ChangePage('menu', $from)),
        );
    }

    public function testConnectToRespondsWithTheOtherService(): void
    {
        [[$method, $arguments]] = $this->respondCallsOf(RunCommand::connectTo(
            'https://example.org/service',
            new Vdt('Cnx en cours'),
        ));

        self::assertSame(['respond', 'Cnx en cours'], [$method, $arguments['vdt']]);
        self::assertEquals(
            new ServiceCallCommand('https://example.org/service', CallMode::Connect),
            $arguments['command'],
        );
    }

    public function testRunCommandSendsAnyCommand(): void
    {
        // A direct call, as the continuation of the session: neither readInput() nor connectTo() gives one
        $command = new ServiceCallCommand('https://example.org/', CallMode::Direct);

        self::assertSame(
            [['respond', ['vdt' => '', 'command' => $command]]],
            $this->respondCallsOf(new RunCommand(new Vdt(), $command, null)),
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
    private function respondCallsOf(Response $response): array
    {
        return array_values(array_filter($this->callsOf($response), static fn(array $call) => $call[0] !== 'keep'));
    }
}
