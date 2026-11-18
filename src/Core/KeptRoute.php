<?php

declare(strict_types=1);

namespace MiniPaviService\Core;

use MiniPaviService\Controller\Parameters\Parameters;
use MiniPaviService\Controller\State;

/**
 * What a route of the context keeps (Context::kept()): the state and parameters of its
 * controller, typed as it declares them. A snapshot: changing the context doesn't change it,
 * nor the other way round.
 *
 * @template-covariant TState of State
 * @template-covariant TParameters of Parameters
 */
final readonly class KeptRoute
{
    /**
     * @param TState $state a case of the enum of its controller's states
     * @param TParameters $parameters of the class its controller declares
     */
    public function __construct(
        public State $state,
        public Parameters $parameters,
    ) {}
}
