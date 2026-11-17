<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\PageParameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Page\InputCommand;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\RecordingFlow;
use PHPUnit\Framework\TestCase;

/**
 * What a response keeps, and what it asks the flow for: what the flow does is Service's
 * (ServiceTest)
 */
final class ResponseTest extends TestCase
{
    public function testDisplaySendsContentAndReadsTheNextKey(): void
    {
        $response = Response::display('Hello');

        self::assertInstanceOf(DisplayPage::class, $response);
        self::assertSame('Hello', $response->vdt);
        self::assertSame(ControllerState::ReadInput, $response->state());
        self::assertNull($response->parameters());
    }

    public function testReadInputSendsTheCommand(): void
    {
        $command = new InputCommand(['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]]);
        $response = Response::readInput('Code ?', $command, parameters: new PageParameters(2));

        self::assertInstanceOf(RunCommand::class, $response);
        self::assertSame('Code ?', $response->vdt);
        self::assertSame($command, $response->command);
        self::assertSame(ControllerState::ReadInput, $response->state());
        self::assertEquals(new PageParameters(2), $response->parameters());
    }

    public function testGoToResetsThisControllerAndGivesTheTargetItsParameters(): void
    {
        $response = Response::goTo('menu', new FromParameters('home'));

        self::assertInstanceOf(ChangePage::class, $response);
        self::assertSame('menu', $response->route);
        self::assertEquals(new FromParameters('home'), $response->targetParameters);
        // null: the controller's initial state
        self::assertNull($response->state());
        self::assertNull($response->parameters());
    }

    public function testConnectToSendsTheUserToAnotherService(): void
    {
        $response = Response::connectTo('https://example.org/service', 'Cnx en cours');

        self::assertInstanceOf(RunExternalService::class, $response);
        self::assertSame('Cnx en cours', $response->vdt);
        self::assertSame('https://example.org/service', $response->url);
        self::assertSame(CallMode::Connect, $response->callMode);
        // null: the controller's initial state
        self::assertNull($response->state());
    }

    public function testRunExternalServiceTakesACallMode(): void
    {
        $direct = new RunExternalService(ControllerState::Init, null, '', 'https://example.org/', CallMode::Direct);
        // The call mode can also be given by its value
        $connect = new RunExternalService(ControllerState::Init, null, '', 'https://example.org/', 'yes-cnx');

        self::assertSame(CallMode::Direct, $direct->callMode);
        self::assertSame(CallMode::Connect, $connect->callMode);
    }

    public function testStateAndParametersCanBeGiven(): void
    {
        $response = Response::display('Hello', ControllerState::Display, new FromParameters('home'));

        self::assertSame(ControllerState::Display, $response->state());
        self::assertEquals(new FromParameters('home'), $response->parameters());
    }

    public function testDisplayAnswersWithItsContent(): void
    {
        self::assertSame([['answer', ['vdt' => 'Hello', 'command' => null, 'next' => null, 'directCall' => null]]], $this->callsOf(Response::display('Hello')));
    }

    public function testReadInputAnswersWithTheCommand(): void
    {
        $command = new InputCommand(['COMMAND' => ['name' => 'InputTxt']]);

        self::assertSame([['answer', ['vdt' => 'Code ?', 'command' => $command, 'next' => null, 'directCall' => null]]], $this->callsOf(Response::readInput('Code ?', $command)));
        // Without content: none sent
        self::assertSame([['answer', ['vdt' => '', 'command' => null, 'next' => null, 'directCall' => null]]], $this->callsOf(new RunCommand(ControllerState::ReadInput, null, null, null)));
    }

    public function testGoToRunsTheTargetController(): void
    {
        $from = new FromParameters('home');

        self::assertSame([['goTo', ['route' => 'menu', 'parameters' => $from]]], $this->callsOf(Response::goTo('menu', $from)));
    }

    public function testConnectToAnswersWithTheOtherService(): void
    {
        self::assertSame(
            [['answer', ['vdt' => 'Cnx en cours', 'command' => null, 'next' => 'https://example.org/service', 'directCall' => CallMode::Connect]]],
            $this->callsOf(Response::connectTo('https://example.org/service', 'Cnx en cours')),
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
