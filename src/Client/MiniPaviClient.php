<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Minitel\Terminal;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of the MiniPavi gateway, through the static MiniPaviCli, used unchanged: it reads the
 * gateway's request itself (MiniPaviCli::start(), from the body of the HTTP request), and
 * writes the answer itself (MiniPaviCli::send()): what it writes is captured as the body of
 * the HTTP response, which the Kernel sends. It sends input commands as MiniPavi's (InputTxt,
 * InputMsg). With Vdt (its Videotex helpers), the only class calling MiniPaviCli.
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
    public function exchange(ServerRequestInterface $request, string $serviceUrl): MiniPaviExchange
    {
        MiniPaviCli::start(ignoreWMState: false);

        return new MiniPaviExchange($this->decoded(), $serviceUrl);
    }

    public function unreadable(string $serviceUrl): MiniPaviExchange
    {
        return new MiniPaviExchange(new GatewayRequest(null), $serviceUrl);
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
