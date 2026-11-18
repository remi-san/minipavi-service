<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\Parameters\ParametersType;
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
    /** @var ParametersType<TParameters> */
    private ParametersType $parametersType;

    /**
     * @param class-string<TParameters> $parameters the class of its parameters
     * @param \Closure(Request<ControllerState, TParameters>): Response $run
     */
    public function __construct(
        string $parameters,
        private \Closure $run,
    ) {
        $this->parametersType = ParametersType::of($parameters);
    }

    public function initialState(): ControllerState
    {
        return ControllerState::Init;
    }

    public function parametersType(): ParametersType
    {
        return $this->parametersType;
    }

    public function run(Request $request): Response
    {
        return ($this->run)($request);
    }
}
