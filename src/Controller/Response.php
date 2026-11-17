<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Core\Context;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * What a controller returns: what to do now (execute()), and the state and parameters it
 * keeps for its next run, in the context.
 *
 * The named constructors give each response the state it usually keeps: read the next
 * input after showing something, start again after leaving. Pass $state or $parameters
 * (by name) to keep others.
 */
abstract readonly class Response
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private string $state,
        private array $parameters,
    ) {}

    /**
     * Sends Videotex content to the gateway; the next key comes back to this controller
     *
     * @param array<string, mixed> $parameters
     */
    public static function display(string $vdt, string $state = Controller::STATE_READ_INPUT, array $parameters = []): DisplayPage
    {
        return new DisplayPage($state, $parameters, $vdt);
    }

    /**
     * Sends Videotex content and an input command (Page::inputCommand(); null for a page
     * without input: only the content is sent); what is typed comes back to this controller
     *
     * @param array<string, mixed> $parameters
     */
    public static function readInput(string $vdt, ?InputCommand $command, string $state = Controller::STATE_READ_INPUT, array $parameters = []): RunCommand
    {
        return new RunCommand($state, $parameters, $vdt, $command);
    }

    /**
     * Switches to the controller of another route, and runs it at once: it starts at
     * STATE_INIT with parameters, without the user's input. This controller is reset.
     *
     * @param array<string, mixed> $parameters parameters of the target controller
     */
    public static function goTo(string $route, array $parameters = []): ChangePage
    {
        return new ChangePage($route, $parameters);
    }

    /**
     * Connects the user to another service, at url, after showing vdt (e.g. a message on
     * line 0). state and parameters are kept for this controller, for when the user
     * comes back.
     *
     * @param array<string, mixed> $parameters
     */
    public static function connectTo(string $url, string $vdt = '', string $state = Controller::STATE_INIT, array $parameters = []): RunExternalService
    {
        return new RunExternalService($state, $parameters, $vdt, $url, CallMode::Connect);
    }

    /**
     * Does what the response says, once the context has the state and parameters it keeps
     * (Context::update()): the answer to the gateway, ending the request, or null to go on
     * (e.g. ChangePage, which runs another controller)
     */
    abstract public function execute(Context $context): ?GatewayAnswer;

    public function state(): string
    {
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
