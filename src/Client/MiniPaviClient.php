<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\NoClientState;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of the MiniPavi gateway, through the static MiniPaviCli, used unchanged: it reads the
 * gateway's request itself (MiniPaviCli::start(), from the body of the HTTP request), and
 * writes the answer itself (MiniPaviCli::send()): what it writes is captured as the body of
 * the HTTP response, which the Kernel sends. It sends input commands as MiniPavi's (InputTxt,
 * InputMsg). With Vdt (its Videotex helpers), the only class calling MiniPaviCli.
 *
 * @implements Client<NoClientState>
 */
final class MiniPaviClient implements Client
{
    /**
     * Reads the request of the gateway (the body of the HTTP request). A background call
     * (GatewayRequest::isBackgroundCall()) is read too, rather than MiniPaviCli exiting on it:
     * the service answers it with nothing
     *
     * @throws \Exception if it is not valid JSON
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        MiniPaviCli::start(ignoreWMState: false);

        return $this->decoded($serviceUrl);
    }

    /**
     * The request read by MiniPaviCli::start(), as it decoded it: values that aren't text
     * become empty
     *
     * @return GatewayRequest<NoClientState>
     */
    public function decoded(string $serviceUrl = ''): GatewayRequest
    {
        // As (array) would: no content is an empty list, a single value a list of one
        $content = MiniPaviCli::$content ?? [];

        return new GatewayRequest(
            GatewayRequest::keyOf(self::text(MiniPaviCli::$fctn)),
            array_map(self::text(...), array_values(\is_array($content) ? $content : [$content])),
            self::text(MiniPaviCli::$context),
            new Terminal(
                self::text(MiniPaviCli::$remoteAddr),
                self::textOrNull(MiniPaviCli::$typeSocket),
                self::textOrNull(MiniPaviCli::$versionMinitel),
            ),
            $serviceUrl,
        );
    }

    public function initialState(): NoClientState
    {
        return new NoClientState();
    }

    /**
     * The answer to the gateway, as MiniPaviCli::send() writes it, captured as the body of the
     * HTTP response: an answer without next URL calls the service again, at the URL of the
     * request
     */
    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface
    {
        ob_start();
        try {
            MiniPaviCli::send(
                $answer->vdt->toString(),
                $answer->next ?? $request->serviceUrl,
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
                $field->col,
                $field->row,
                $field->length,
                $field->lines,
                $validWith,
                $field->cursor,
                $field->spaceChar,
                $command->prefill,
            );
        }

        return MiniPaviCli::createInputTxtCmd(
            $field->col,
            $field->row,
            $field->length,
            $validWith,
            $field->cursor,
            $field->spaceChar,
            char: '',
            preFill: $command->prefill[0] ?? '',
        );
    }

    /**
     * A value of the gateway's request (decoded JSON) as text: '' if it is not a scalar
     */
    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
