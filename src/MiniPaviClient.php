<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPavi\MiniPaviCli;

/**
 * Instance wrapper around the static MiniPaviCli: the request of the gateway (after start()),
 * the answer to it (send()). With VdtHelper, the only classes calling MiniPaviCli.
 */
final class MiniPaviClient
{
    // Function keys, and the codes of fctn() for connections
    public const KEY_REFRESH = 'REPETITION';
    public const KEY_GUIDE = 'GUIDE';
    public const KEY_SEND = 'ENVOI';
    public const KEY_NEXT = 'SUITE';
    public const KEY_PREVIOUS = 'RETOUR';
    public const KEY_SUMMARY = 'SOMMAIRE';
    public const FCTN_CONNECT = 'CNX';
    public const FCTN_DIRECT_CONNECT = 'DIRECTCNX';
    public const FCTN_DISCONNECT = 'FIN';

    // Request of the gateway

    // Reads the request of the gateway
    public function start(): void
    {
        MiniPaviCli::start();
    }

    public function isConnecting(): bool
    {
        return $this->fctn() == self::FCTN_CONNECT || $this->fctn() == self::FCTN_DIRECT_CONNECT;
    }

    public function isDisconnecting(): bool
    {
        return $this->fctn() == self::FCTN_DISCONNECT;
    }

    // Function key used, or a FCTN_* code (CNX, FIN...)
    public function fctn(): string
    {
        return self::text(MiniPaviCli::$fctn);
    }

    // Typed content, one string per line of the input field
    /**
     * @return string[]
     */
    public function content(): array
    {
        // As (array) would: no content is an empty list, a single value a list of one
        $content = MiniPaviCli::$content ?? [];

        return array_map(self::text(...), array_values(\is_array($content) ? $content : [$content]));
    }

    public function contentAsString(): string
    {
        return implode('', $this->content());
    }

    public function clearContent(): void
    {
        MiniPaviCli::$content = [];
    }

    // Context sent with the previous answer
    public function context(): string
    {
        return self::text(MiniPaviCli::$context);
    }

    // IP of the user, or "CALLFROM xxxx" when calling by phone
    public function remoteAddr(): string
    {
        return self::text(MiniPaviCli::$remoteAddr);
    }

    // 'websocket' or 'other'
    public function typeSocket(): ?string
    {
        return self::textOrNull(MiniPaviCli::$typeSocket);
    }

    // Version of the Minitel if known, '???' otherwise
    public function versionMinitel(): ?string
    {
        return self::textOrNull(MiniPaviCli::$versionMinitel);
    }

    // Answer to the gateway

    /**
     * @param ?array<string, mixed> $cmd
     */
    public function send(string $vdt, ?string $next, Context $context, bool $echo = true, ?array $cmd = null, bool|string $directCall = false): void
    {
        MiniPaviCli::send($vdt, $next ?? $this->serviceUri(), $context->serialize(), $echo, $cmd, $directCall);
    }

    /**
     * @return array<string, mixed>
     */
    public function createInputTxtCmd(int $col, int $row, int $length, int $validWith, bool $cursor, string $spaceChar, string $char = '', string $prefill = ''): array
    {
        return MiniPaviCli::createInputTxtCmd($col, $row, $length, $validWith, $cursor, $spaceChar, $char, $prefill);
    }

    /**
     * @param string[] $prefill
     * @return array<string, mixed>
     */
    public function createInputMsgCmd(int $col, int $row, int $width, int $height, int $validWith, bool $cursor, string $spaceChar, array $prefill = []): array
    {
        return MiniPaviCli::createInputMsgCmd($col, $row, $width, $height, $validWith, $cursor, $spaceChar, $prefill);
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

    private function serviceUri(): string
    {
        return $this->protocol() . '://' . ServerVariable::get('HTTP_HOST') . ServerVariable::get('PHP_SELF');
    }

    private function protocol(): string
    {
        if (ServerVariable::isOn('HTTPS')) {
            return 'https';
        }
        if (strtolower(ServerVariable::get('HTTP_X_FORWARDED_PROTO')) === 'https') {
            return 'https';
        }
        if (ServerVariable::isOn('HTTP_FRONT_END_HTTPS')) {
            return 'https';
        }
        if ((int) ServerVariable::get('SERVER_PORT') === 443) {
            return 'https';
        }
        return 'http';
    }
}
