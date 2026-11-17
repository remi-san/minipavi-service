<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\Gateway\GatewayAnswer;
use MiniPaviService\Page\InputCommand;

/**
 * What a controller returns: the state and parameters it keeps for its next run, in the
 * context, and what comes next (continueWith()). Responses are readonly and don't change the
 * context: the runtime keeps their state and parameters, then they say what comes next
 * through a Flow.
 *
 * The named constructors give each response the state it usually keeps: read the next
 * input after showing something, start again after leaving. Pass $state or $parameters
 * (by name) to keep others: a state, or an enum case whose value is one (see
 * Request::stateAs()), and parameters as an array or a Parameters object.
 */
abstract readonly class Response
{
    private string $state;

    /** @var array<string, mixed> */
    private array $parameters;

    /**
     * @param string|\BackedEnum $state a state, or an enum case whose value is one
     * @param array<string, mixed>|Parameters $parameters
     */
    public function __construct(string|\BackedEnum $state, array|Parameters $parameters)
    {
        $this->state = \is_string($state) ? $state : (string) $state->value;
        $this->parameters = self::parametersArray($parameters);
    }

    /**
     * Parameters as kept in the context
     *
     * @param array<string, mixed>|Parameters $parameters
     *
     * @return array<string, mixed>
     */
    protected static function parametersArray(array|Parameters $parameters): array
    {
        return \is_array($parameters) ? $parameters : $parameters->toArray();
    }

    /**
     * Sends Videotex content to the gateway; the next key comes back to this controller
     *
     * @param array<string, mixed>|Parameters $parameters
     */
    public static function display(string $vdt, string|\BackedEnum $state = Controller::STATE_READ_INPUT, array|Parameters $parameters = []): DisplayPage
    {
        return new DisplayPage($state, $parameters, $vdt);
    }

    /**
     * Sends Videotex content and an input command (Page::inputCommand(); null for a page
     * without input: only the content is sent); what is typed comes back to this controller
     *
     * @param array<string, mixed>|Parameters $parameters
     */
    public static function readInput(string $vdt, ?InputCommand $command, string|\BackedEnum $state = Controller::STATE_READ_INPUT, array|Parameters $parameters = []): RunCommand
    {
        return new RunCommand($state, $parameters, $vdt, $command);
    }

    /**
     * Switches to the controller of another route, and runs it at once: it starts at
     * STATE_INIT with parameters, without the user's input. This controller is reset.
     *
     * @param array<string, mixed>|Parameters $parameters parameters of the target controller
     */
    public static function goTo(string $route, array|Parameters $parameters = []): ChangePage
    {
        return new ChangePage($route, $parameters);
    }

    /**
     * Connects the user to another service, at url, after showing vdt (e.g. a message on
     * line 0). state and parameters are kept for this controller, for when the user
     * comes back.
     *
     * @param array<string, mixed>|Parameters $parameters
     */
    public static function connectTo(string $url, string $vdt = '', string|\BackedEnum $state = Controller::STATE_INIT, array|Parameters $parameters = []): RunExternalService
    {
        return new RunExternalService($state, $parameters, $vdt, $url, CallMode::Connect);
    }

    /**
     * What comes next, once the context keeps this response's state and parameters: answer
     * the gateway (Flow::answer()) or run another controller (Flow::goTo()); gives the answer
     * the request ends with
     */
    abstract public function continueWith(Flow $flow): GatewayAnswer;

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
