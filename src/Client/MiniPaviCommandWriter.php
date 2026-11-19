<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\CommandSender;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;

/**
 * Writes a response through MiniPaviClient (MiniPaviExchange), sending its command as what
 * MiniPaviCli::send() takes: the next URL, the input field and the direct call. By default
 * (WaitForKeyCommand), the gateway waits for a key, then calls this service again.
 *
 * @implements CommandSender<string>
 *
 * @internal
 */
final readonly class MiniPaviCommandWriter implements CommandSender
{
    /**
     * @param MiniPaviClient $client writes the response, as MiniPaviCli::send()
     * @param string $serviceUrl URL the request was sent to: the next URL, unless the command
     *                           calls another service
     */
    public function __construct(
        private MiniPaviClient $client,
        private GatewayResponse $response,
        private string $serviceUrl,
    ) {}

    /**
     * What MiniPaviCli::send() writes for the response
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
        return $this->client->send($this->response->vdt, $this->serviceUrl, $this->response->context);
    }

    /**
     * This service, called with what was typed in the field
     */
    public function readInput(InputCommand $command): string
    {
        return $this->client->send($this->response->vdt, $this->serviceUrl, $this->response->context, input: $command);
    }

    /**
     * The service called as the next URL, at once (directCall)
     */
    public function callService(ServiceCallCommand $command): string
    {
        return $this->client->send(
            $this->response->vdt,
            $command->url,
            $this->response->context,
            directCall: $command->mode,
        );
    }
}
