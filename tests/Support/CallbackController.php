<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * Controller running a callback, with the built-in states and the parameters of a class
 *
 * @template TParameters of Parameters
 *
 * @implements Controller<ControllerState, TParameters>
 */
final readonly class CallbackController implements Controller
{
    /**
     * @param class-string<TParameters> $parametersType
     * @param \Closure(Request<ControllerState, TParameters>): Response $run
     */
    public function __construct(
        private string $parametersType,
        private \Closure $run,
    ) {}

    public function initialState(): ControllerState
    {
        return ControllerState::Init;
    }

    public function parametersType(): string
    {
        return $this->parametersType;
    }

    public function run(Request $request): Response
    {
        return ($this->run)($request);
    }
}
