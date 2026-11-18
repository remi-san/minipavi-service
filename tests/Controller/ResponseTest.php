<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
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
        $response = Response::display(new Vdt('Hello'));

        self::assertSame(ControllerState::ReadInput, $response->state());
        // null: the defaults of the controller's parameters
        self::assertNull($response->parameters());
    }

    public function testReadInputKeepsItsParameters(): void
    {
        $response = Response::readInput(new Vdt('Code ?'), command: null, parameters: new PageParameters(2));

        self::assertSame(ControllerState::ReadInput, $response->state());
        self::assertEquals(new PageParameters(2), $response->parameters());
    }

    public function testGoToResetsThisController(): void
    {
        $response = Response::goTo('menu', new FromParameters('home'));

        // null: the controller's initial state, the defaults of its parameters
        self::assertNull($response->state());
        self::assertNull($response->parameters());
    }

    public function testConnectToKeepsWhatItIsGivenForWhenTheUserComesBack(): void
    {
        self::assertNull(Response::connectTo('https://example.org/service')->state());

        $response = Response::connectTo(
            'https://example.org/service',
            state: ControllerState::Display,
            parameters: new PageParameters(4),
        );

        self::assertSame(ControllerState::Display, $response->state());
        self::assertEquals(new PageParameters(4), $response->parameters());
    }

    public function testStateAndParametersCanBeGiven(): void
    {
        $response = Response::display(new Vdt('Hello'), ControllerState::Display, new FromParameters('home'));

        self::assertSame(ControllerState::Display, $response->state());
        self::assertEquals(new FromParameters('home'), $response->parameters());
    }

    public function testDisplayAnswersWithItsContent(): void
    {
        self::assertSame(
            [['answer', ['vdt' => 'Hello', 'command' => null, 'next' => null, 'directCall' => null]]],
            $this->callsOf(Response::display(new Vdt('Hello'))),
        );
    }

    public function testReadInputAnswersWithTheCommand(): void
    {
        $command = InputCommand::text(1, row: 2, length: 4, validWith: [FunctionKey::Send]);

        self::assertSame(
            [['answer', ['vdt' => 'Code ?', 'command' => $command, 'next' => null, 'directCall' => null]]],
            $this->callsOf(Response::readInput(new Vdt('Code ?'), $command)),
        );
        // Without content: none sent
        self::assertSame(
            [['answer', ['vdt' => '', 'command' => null, 'next' => null, 'directCall' => null]]],
            $this->callsOf(new RunCommand(ControllerState::ReadInput, null, new Vdt(), null)),
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
        self::assertSame(
            [[
                'answer',
                [
                    'vdt' => 'Cnx en cours',
                    'command' => null,
                    'next' => 'https://example.org/service',
                    'directCall' => CallMode::Connect,
                ],
            ]],
            $this->callsOf(Response::connectTo('https://example.org/service', new Vdt('Cnx en cours'))),
        );
    }

    public function testRunExternalServiceCanCallTheOtherServiceDirectly(): void
    {
        $answer = [
            'answer',
            ['vdt' => '', 'command' => null, 'next' => 'https://example.org/', 'directCall' => CallMode::Direct],
        ];

        self::assertSame(
            [$answer],
            $this->callsOf(new RunExternalService(null, null, new Vdt(), 'https://example.org/', CallMode::Direct)),
        );
        // The call mode can also be given by its value
        self::assertSame(
            [$answer],
            $this->callsOf(new RunExternalService(null, null, new Vdt(), 'https://example.org/', 'yes')),
        );
    }

    /**
     * What response asks the flow for
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function callsOf(Response $response): array
    {
        $flow = new RecordingFlow();
        $response->continueWith($flow);

        return $flow->calls;
    }
}
