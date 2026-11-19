<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\Command;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Vdt\Vdt;

/**
 * Sends Videotex content and a command to the gateway: reading an input field
 * (readInput()), what the user types coming back to this controller; or calling another
 * service (connectTo()), this controller's state and parameters kept for when the user comes
 * back
 *
 * @template-covariant TState of State
 *
 * @extends KeepingResponse<TState>
 */
final readonly class RunCommand extends KeepingResponse
{
    /**
     * @param Vdt $vdt Videotex content (empty: none)
     * @param Command $command e.g. Page::inputCommand(); WaitForKeyCommand: only the content is
     *                         sent
     * @param ?TState $state state this controller keeps (null: its initial state); given, as
     *                      it depends on the command: ControllerState::ReadInput to read
     *                      what an InputCommand brings back
     * @param ?Parameters $parameters parameters this controller keeps; null: their defaults
     */
    public function __construct(
        public Vdt $vdt,
        public Command $command,
        ?State $state,
        ?Parameters $parameters = null,
    ) {
        parent::__construct($state, $parameters);
    }

    /**
     * Sends Videotex content and an input command (Page::inputCommand(); null for a page
     * without input: only the content is sent); what is typed comes back to this controller,
     * in the state kept: ControllerState::ReadInput by default, which a controller with its
     * own State enum replaces with one of its states
     *
     * @template T of State = ControllerState::ReadInput
     *
     * @param T $state
     *
     * @return self<T>
     */
    public static function readInput(
        Vdt $vdt,
        ?InputCommand $command,
        State $state = ControllerState::ReadInput,
        ?Parameters $parameters = null,
    ): self {
        return new self($vdt, $command ?? new WaitForKeyCommand(), $state, $parameters);
    }

    /**
     * Connects the user to another service, at url, after showing vdt (e.g. a message on
     * line 0). state and parameters are kept for this controller, for when the user comes
     * back; null: its initial state, and the defaults of its parameters.
     *
     * @template T of State
     *
     * @param ?T $state
     *
     * @return ($state is null ? self<never> : self<T>)
     */
    public static function connectTo(
        string $url,
        Vdt $vdt = new Vdt(),
        ?State $state = null,
        ?Parameters $parameters = null,
    ): self {
        return new self($vdt, new ServiceCallCommand($url, CallMode::Connect), $state, $parameters);
    }

    protected function next(Flow $flow): GatewayResponse
    {
        return $flow->respond($this->vdt, $this->command);
    }
}
