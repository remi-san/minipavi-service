<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDisplaySendsContentAndReadsTheNextKey(): void
    {
        $response = Response::display('Hello');
        $context = new Context('home');
        $context->update($response);

        self::assertInstanceOf(DisplayPage::class, $response);
        self::assertSame(Controller::STATE_READ_INPUT, $response->state());
        self::assertSame([], $response->parameters());
        // To this service (next: null), waiting for the user, with the context
        self::assertEquals(new GatewayAnswer('Hello', $context->serialize()), $response->execute($context));
    }

    public function testReadInputSendsTheCommand(): void
    {
        $command = new InputCommand(['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]]);
        $response = Response::readInput('Code ?', $command, parameters: ['page' => 2]);

        self::assertInstanceOf(RunCommand::class, $response);
        self::assertSame(Controller::STATE_READ_INPUT, $response->state());
        self::assertSame(['page' => 2], $response->parameters());
        $answer = $this->answer($response);
        self::assertSame('Code ?', $answer->vdt);
        self::assertSame(['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]], $answer->command);
        self::assertNull($answer->next);
        self::assertNull($answer->directCall);
    }

    public function testReadInputWithoutCommandOnlySendsTheContent(): void
    {
        // Page::inputCommand() gives null for a page without <input>
        $answer = $this->answer(Response::readInput('Hello', null));

        self::assertSame('Hello', $answer->vdt);
        self::assertNull($answer->command);
    }

    public function testGoToChangesTheRouteWithoutAnsweringTheGateway(): void
    {
        $response = Response::goTo('menu');
        $context = new Context('home');

        self::assertInstanceOf(ChangePage::class, $response);
        self::assertSame(Controller::STATE_INIT, $response->state());
        self::assertNull($response->execute($context));
        self::assertSame('menu', $context->route());
    }

    public function testConnectToSendsTheUserToAnotherService(): void
    {
        $response = Response::connectTo('https://example.org/service', 'Cnx en cours');

        self::assertInstanceOf(RunExternalService::class, $response);
        self::assertSame(Controller::STATE_INIT, $response->state());
        $answer = $this->answer($response);
        self::assertSame('Cnx en cours', $answer->vdt);
        self::assertSame('https://example.org/service', $answer->next);
        self::assertSame(CallMode::Connect, $answer->directCall);
    }

    public function testRunExternalServiceTakesACallMode(): void
    {
        $direct = new RunExternalService(Controller::STATE_INIT, [], '', 'https://example.org/', CallMode::Direct);
        // The call mode can also be given by its value
        $connect = new RunExternalService(Controller::STATE_INIT, [], '', 'https://example.org/', 'yes-cnx');

        self::assertSame(CallMode::Direct, $this->answer($direct)->directCall);
        self::assertSame(CallMode::Connect, $connect->callMode);
        self::assertSame(CallMode::Connect, $this->answer($connect)->directCall);
    }

    public function testStateAndParametersCanBeGiven(): void
    {
        $response = Response::display('Hello', Controller::STATE_DISPLAY, ['from' => 'home']);

        self::assertSame(Controller::STATE_DISPLAY, $response->state());
        self::assertSame(['from' => 'home'], $response->parameters());
    }

    public function testGoToStartsTheTargetWithItsParametersAndResetsThisController(): void
    {
        $context = new Context('home', ['home' => Controller::STATE_READ_INPUT, 'menu' => Controller::STATE_READ_INPUT], ['home' => ['page' => 2], 'menu' => ['page' => 5]]);
        $response = Response::goTo('menu', ['from' => 'home']);

        $context->update($response);
        $response->execute($context);

        self::assertSame('menu', $context->route());
        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame(['from' => 'home'], $context->parameters());
        $context->changeRoute('home');
        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame([], $context->parameters());
    }

    /**
     * Answer of a response to the gateway, whose context it gives back
     */
    private function answer(Response $response): GatewayAnswer
    {
        $context = new Context('home');
        $answer = $response->execute($context) ?? self::fail('No answer to the gateway');
        self::assertSame($context->serialize(), $answer->context);

        return $answer;
    }
}
