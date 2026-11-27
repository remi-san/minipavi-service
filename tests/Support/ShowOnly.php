<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Response;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Vdt\Vdt;

/**
 * A response of a service's own, implementing Response rather than extending a framework
 * class: shows vdt, keeping nothing (the controller keeps what it had), so a
 * response of any controller
 *
 * @implements Response<never>
 */
final readonly class ShowOnly implements Response
{
    public function __construct(
        private string $vdt,
    ) {}

    public function continueWith(Flow $flow): GatewayResponse
    {
        return $flow->respond(Vdt::text($this->vdt));
    }
}
