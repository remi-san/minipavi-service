<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\ParametersType;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Vdt\Vdt;

/**
 * Controller whose states are backed by numbers (StepState): shows its step, then reads the
 * second
 *
 * @implements Controller<StepState, NoParameters>
 */
final class StepController implements Controller
{
    public function initialState(): StepState
    {
        return StepState::First;
    }

    public function parametersType(): ParametersType
    {
        return ParametersType::of(NoParameters::class);
    }

    public function run(Request $request): Response
    {
        return new DisplayPage(Vdt::text('step ' . $request->state->value), StepState::Second);
    }
}
