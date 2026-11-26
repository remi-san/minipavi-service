<?php

declare(strict_types=1);

namespace MiniPaviService\Client\MiniPavi;

use MiniPaviService\Gateway\Command\CommandSender;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Gateway\Command\ServiceCallCommand;
use MiniPaviService\Gateway\Command\WaitForKeyCommand;
use MiniPaviService\Gateway\GatewayResponse;

/**
 * Writes a response through the MiniPaviGateway (MiniPaviExchange), sending its command as what
 * the gateway takes: the next URL, the input field and the direct call. By default
 * (WaitForKeyCommand), the gateway waits for a key, then calls this service again.
 *
 * @implements CommandSender<string>
 *
 * @internal
 */
final readonly class MiniPaviCommandWriter implements CommandSender
{
    /**
     * @param MiniPaviGateway $gateway sends the response
     * @param string $serviceUrl URL the request was sent to: the next URL, unless the command
     *                           calls another service
     */
    public function __construct(
        private MiniPaviGateway $gateway,
        private GatewayResponse $response,
        private string $serviceUrl,
    ) {}

    /**
     * What is sent to the gateway for the response
     */
    public function body(): string
    {
        return $this->response->command->sendWith($this);
    }

    /**
     * This service, called with the next key
     */
    public function waitForKey(WaitForKeyCommand $command): string
    {
        return $this->gateway->send($this->response->vdt, $this->serviceUrl, $this->response->context);
    }

    /**
     * This service, called with what was typed in the field
     */
    public function readInput(InputCommand $command): string
    {
        return $this->gateway->send($this->response->vdt, $this->serviceUrl, $this->response->context, input: $command);
    }

    /**
     * The service called as the next URL, at once (directCall)
     */
    public function callService(ServiceCallCommand $command): string
    {
        return $this->gateway->send(
            $this->response->vdt,
            $command->url,
            $this->response->context,
            directCall: $command->mode,
        );
    }
}
