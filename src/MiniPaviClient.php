<?php

namespace MiniPaviService;

use MiniPavi\MiniPaviCli;

/**
 * Instance wrapper around the static MiniPaviCli: the request of the gateway (after start()),
 * the answer to it (send()). With VdtHelper, the only classes calling MiniPaviCli.
 */
final class MiniPaviClient {

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

    /*****************
     * Request
     *****************/

    // Reads the request of the gateway
    public function start(): void {
        MiniPaviCli::start();
    }

    public function isConnecting(): bool {
        return $this->fctn() == self::FCTN_CONNECT || $this->fctn() == self::FCTN_DIRECT_CONNECT;
    }

    public function isDisconnecting(): bool {
        return $this->fctn() == self::FCTN_DISCONNECT;
    }

    // Function key used, or a FCTN_* code (CNX, FIN...)
    public function fctn(): string {
        return (string)MiniPaviCli::$fctn;
    }

    // Typed content, one string per line of the input field
    public function content(): array {
        return (array)MiniPaviCli::$content;
    }

    public function contentAsString(): string {
        return implode('', $this->content());
    }

    public function clearContent(): void {
        MiniPaviCli::$content = array();
    }

    // Context sent with the previous answer
    public function context(): string {
        return (string)MiniPaviCli::$context;
    }

    // IP of the user, or "CALLFROM xxxx" when calling by phone
    public function remoteAddr(): string {
        return (string)MiniPaviCli::$remoteAddr;
    }

    // 'websocket' or 'other'
    public function typeSocket(): ?string {
        return MiniPaviCli::$typeSocket;
    }

    // Version of the Minitel if known, '???' otherwise
    public function versionMinitel(): ?string {
        return MiniPaviCli::$versionMinitel;
    }

    /*****************
     * Answer
     *****************/

    public function send(string $vdt, ?string $next, Context $context, bool $echo = true, ?array $cmd = null, bool|string $directCall = false): void {
        MiniPaviCli::send($vdt, $next ?? $this->serviceUri(), $context->serialize(), $echo, $cmd, $directCall);
    }

    public function createInputTxtCmd(int $col, int $row, int $length, int $validWith, bool $cursor, string $spaceChar, string $char = '', string $prefill = ''): array {
        return MiniPaviCli::createInputTxtCmd($col, $row, $length, $validWith, $cursor, $spaceChar, $char, $prefill);
    }

    public function createInputMsgCmd(int $col, int $row, int $width, int $height, int $validWith, bool $cursor, string $spaceChar, array $prefill = array()): array {
        return MiniPaviCli::createInputMsgCmd($col, $row, $width, $height, $validWith, $cursor, $spaceChar, $prefill);
    }

    private function serviceUri(): string {
        return $this->protocol()."://".$_SERVER['HTTP_HOST']."".$_SERVER['PHP_SELF'];
    }

    private function protocol(): string {
		if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
			return 'https';
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
			return 'https';
		if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
			return 'https';
		if (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443)
			return 'https';
		return 'http';
	}
}
