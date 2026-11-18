<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\ClientExchange;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A request of the MiniPavi gateway (MiniPaviClient), answered as MiniPaviCli::send() writes
 * it: an answer without next URL calls the service again, at the URL the request was sent to
 */
final readonly class MiniPaviExchange implements ClientExchange
{
    public function __construct(
        private GatewayRequest $request,
        private string $serviceUrl,
    ) {}

    public function request(): GatewayRequest
    {
        return $this->request;
    }

    /**
     * The answer, as MiniPaviCli::send() writes it, captured as the body of the HTTP response
     */
    public function respond(GatewayResponse $answer): ResponseInterface
    {
        ob_start();
        try {
            MiniPaviCli::send(
                $answer->vdt->toString(),
                $answer->next ?? $this->serviceUrl,
                $answer->context,
                // The gateway echoes what the user types: no response turns it off
                echo: true,
                cmd: self::command($answer->command),
                directCall: $answer->directCall->value ?? false,
            );
        } finally {
            $body = (string) ob_get_clean();
        }

        return new Response(200, [], $body);
    }

    /**
     * The command for the gateway, as MiniPaviCli builds it: InputTxt for a text field,
     * InputMsg for a message; null for none
     *
     * @return ?array<string, mixed>
     */
    private static function command(?InputCommand $command): ?array
    {
        if ($command === null) {
            return null;
        }
        $field = $command->field;
        $validWith = FunctionKey::masks(...$field->validWith);
        if ($field->isMessage()) {
            return MiniPaviCli::createInputMsgCmd(
                $field->span->start->col,
                $field->span->start->row,
                $field->span->length,
                $field->lines,
                $validWith,
                $field->cursor,
                $field->spaceChar,
                $command->prefill,
            );
        }

        return MiniPaviCli::createInputTxtCmd(
            $field->span->start->col,
            $field->span->start->row,
            $field->span->length,
            $validWith,
            $field->cursor,
            $field->spaceChar,
            char: '',
            preFill: $command->prefill[0] ?? '',
        );
    }
}
