<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\NoClientState;
use MiniPaviService\Http\EmptyHttpResponse;
use MiniPaviService\Minitel\Terminal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of the MiniPavi gateway, through the static MiniPaviCli, used unchanged: it reads the
 * gateway's request itself (MiniPaviCli::start(), from the body of the HTTP request), and
 * writes the answer itself (MiniPaviCli::send()), so the HTTP response is an
 * EmptyHttpResponse, which the Kernel doesn't send. With VdtHelper (Videotex helpers) and
 * InputCommand (input commands), the only classes calling MiniPaviCli.
 *
 * @implements Client<NoClientState>
 */
final class MiniPaviClient implements Client
{
    /**
     * Reads the request of the gateway (the body of the HTTP request)
     *
     * @throws \Exception if it is not valid JSON
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        MiniPaviCli::start();

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
            self::text(MiniPaviCli::$fctn),
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

    /**
     * Writes the answer to the gateway (the body of the HTTP response): an answer without next
     * URL calls the service again, at the URL of the request
     */
    public function initialState(): NoClientState
    {
        return new NoClientState();
    }

    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface
    {
        MiniPaviCli::send(
            $answer->vdt->toString(),
            $answer->next ?? $request->serviceUrl,
            $answer->context,
            $answer->echo,
            $answer->command,
            $answer->directCall->value ?? false,
        );

        return new EmptyHttpResponse();
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
