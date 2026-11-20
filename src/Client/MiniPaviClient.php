<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of the MiniPavi gateway, through the static MiniPaviCli, used unchanged: it reads the
 * gateway's request itself (MiniPaviCli::start(), from the body of the HTTP request), and
 * writes the response itself (MiniPaviCli::send()): what it writes is captured as the body of
 * the HTTP response, which the Kernel sends. It sends input commands as MiniPavi's (InputTxt,
 * InputMsg). With Vdt (its Videotex helpers), the only class calling MiniPaviCli: the exchange
 * (MiniPaviExchange) and the commands (MiniPaviCommandWriter) go through it.
 */
final class MiniPaviClient implements Client
{
    /**
     * Reads the request of the gateway (the body of the HTTP request). A background call
     * (GatewayRequest::isBackgroundCall()) is read too, rather than MiniPaviCli exiting on it:
     * the service gives it no response
     *
     * @throws \Exception if it is not valid JSON
     */
    public function exchange(ServerRequestInterface $request, string $serviceUrl): MiniPaviExchange
    {
        MiniPaviCli::start(ignoreWMState: false);

        return new MiniPaviExchange($this, $this->decoded(), $serviceUrl);
    }

    public function unreadable(string $serviceUrl): MiniPaviExchange
    {
        return new MiniPaviExchange($this, new GatewayRequest(null), $serviceUrl);
    }

    /**
     * What MiniPaviCli::send() writes for a response, captured: the content and the context,
     * the URL the gateway calls next, and what it does before
     *
     * @param ?InputCommand $input field the gateway reads, as MiniPavi's InputTxt or InputMsg
     * @param ?CallMode $directCall calls next at once, without waiting for the user
     */
    public function send(
        Vdt $vdt,
        string $next,
        string $context,
        ?InputCommand $input = null,
        ?CallMode $directCall = null,
    ): string {
        // Its command built first: MiniPaviCli::send() can't fail (only its json_encode() could,
        // which it writes as false), so the buffer is always closed
        $cmd = $input === null ? null : self::inputCmd($input);
        ob_start();
        MiniPaviCli::send(
            $vdt->toString(),
            $next,
            $context,
            // The gateway echoes what the user types: no response turns it off
            echo: true,
            cmd: $cmd,
            directCall: $directCall->value ?? false,
        );

        // A string here (false only without a buffer)
        return (string) ob_get_clean();
    }

    /**
     * The request read by MiniPaviCli::start(), as it decoded it: values that aren't text
     * become empty
     */
    public function decoded(): GatewayRequest
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
        );
    }

    /**
     * MiniPavi's command reading the field: InputTxt for a text field, InputMsg for a message
     *
     * @return array<string, mixed>
     */
    private static function inputCmd(InputCommand $command): array
    {
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
