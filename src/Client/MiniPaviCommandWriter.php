<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\CommandSender;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Gateway\ServiceCallCommand;
use MiniPaviService\Gateway\WaitForKeyCommand;
use MiniPaviService\Minitel\FunctionKey;

/**
 * Writes an answer as MiniPaviCli::send() does (MiniPaviExchange), sending its command as
 * what send() takes: the next URL, cmd and directCall. By default
 * (WaitForKeyCommand), the gateway waits for a key, then calls this service again.
 *
 * @implements CommandSender<string>
 *
 * @internal
 */
final readonly class MiniPaviCommandWriter implements CommandSender
{
    /**
     * @param string $serviceUrl URL the request was sent to: the next URL, unless the command
     *                           calls another service
     */
    public function __construct(
        private GatewayResponse $answer,
        private string $serviceUrl,
    ) {}

    /**
     * What MiniPaviCli::send() writes for the answer
     */
    public function body(): string
    {
        return $this->answer->command->sendWith($this);
    }

    /**
     * This service, called with the next key
     */
    public function waitForKey(WaitForKeyCommand $command): string
    {
        return $this->sent($this->serviceUrl);
    }

    /**
     * InputTxt for a text field, InputMsg for a message
     */
    public function readInput(InputCommand $command): string
    {
        return $this->sent($this->serviceUrl, cmd: self::input($command));
    }

    /**
     * The service called as send()'s next URL, at once (directCall)
     */
    public function callService(ServiceCallCommand $command): string
    {
        return $this->sent($command->url, directCall: $command->mode->value);
    }

    /**
     * What MiniPaviCli::send() writes, captured
     *
     * @param ?array<string, mixed> $cmd
     */
    private function sent(string $next, ?array $cmd = null, string|false $directCall = false): string
    {
        ob_start();
        try {
            MiniPaviCli::send(
                $this->answer->vdt->toString(),
                $next,
                $this->answer->context,
                // The gateway echoes what the user types: no response turns it off
                echo: true,
                cmd: $cmd,
                directCall: $directCall,
            );
        } finally {
            $body = (string) ob_get_clean();
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private static function input(InputCommand $command): array
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
}
