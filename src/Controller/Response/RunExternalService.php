<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Response;

use MiniPaviService\Controller\CallMode;
use MiniPaviService\Controller\Flow;
use MiniPaviService\Controller\Parameters;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\State;
use MiniPaviService\Gateway\GatewayAnswer;

/**
 * Sends the user to another service, at url, after showing vdt; state and parameters are kept
 * for this controller, for when the user comes back
 */
final readonly class RunExternalService extends Response
{
    public CallMode $callMode;

    /**
     * @param string $vdt Videotex content shown before leaving (e.g. a message on line 0)
     * @param string $url URL of the other service
     * @param CallMode|string $callMode a CallMode, or its value ('yes-cnx', 'yes')
     */
    public function __construct(
        ?State $state,
        ?Parameters $parameters,
        public string $vdt,
        public string $url,
        CallMode|string $callMode,
    ) {
        parent::__construct($state, $parameters);
        $this->callMode = \is_string($callMode) ? CallMode::from($callMode) : $callMode;
    }

    public function continueWith(Flow $flow): GatewayAnswer
    {
        return $flow->answer($this->vdt, next: $this->url, directCall: $this->callMode);
    }
}
