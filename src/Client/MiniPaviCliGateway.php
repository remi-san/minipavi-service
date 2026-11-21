<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Vdt;

/**
 * The MiniPavi gateway through the static MiniPaviCli, used unchanged: it reads the gateway's
 * request itself (MiniPaviCli::start(), from the body of the HTTP request), and writes the
 * response itself (MiniPaviCli::send()): what it writes is captured as the body of the HTTP
 * response, which the Kernel sends. It sends input fields as MiniPavi's commands (InputTxt,
 * InputMsg). With Vdt (its Videotex helpers), the only class calling MiniPaviCli.
 */
final readonly class MiniPaviCliGateway implements MiniPaviGateway
{
    /**
     * The request read by MiniPaviCli::start(), as it decoded it: values that aren't text
     * become empty. A background call is read too, rather than MiniPaviCli exiting on it
     */
    public function read(): GatewayRequest
    {
        MiniPaviCli::start(ignoreWMState: false);

        // As (array) would: no content is an empty list, a single value a list of one. It is
        // decoded as objects: an array is always a list
        $content = MiniPaviCli::$content ?? [];
        $lines = [];
        foreach (\is_array($content) ? $content : [$content] as $line) {
            $lines[] = self::text($line);
        }

        return new GatewayRequest(
            GatewayRequest::keyOf(self::text(MiniPaviCli::$fctn)),
            $lines,
            self::text(MiniPaviCli::$context),
            new Terminal(
                self::text(MiniPaviCli::$remoteAddr),
                self::textOrNull(MiniPaviCli::$typeSocket),
                self::textOrNull(MiniPaviCli::$versionMinitel),
            ),
        );
    }

    /**
     * What MiniPaviCli::send() writes for the response, captured
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
