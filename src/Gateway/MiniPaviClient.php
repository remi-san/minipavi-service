<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPavi\MiniPaviCli;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Minitel\Terminal;

/**
 * Adapter between the static MiniPaviCli and the service: reads the request of the gateway as
 * a GatewayRequest (receive()), and sends it a GatewayAnswer (send()). With VdtHelper
 * (Videotex helpers) and InputCommand (input commands), the only classes calling MiniPaviCli.
 */
final class MiniPaviClient
{
    private RequestUrl $url;

    /**
     * @param ?RequestUrl $url URLs of the service, by default from the environment
     *                         (RequestUrl::fromEnvironment()): the next URL of answers
     *                         without one
     */
    public function __construct(?RequestUrl $url = null)
    {
        $this->url = $url ?? RequestUrl::fromEnvironment();
    }

    /**
     * Reads the request of the gateway (the body of the HTTP request)
     *
     * @throws \Exception if it is not valid JSON
     */
    public function receive(): GatewayRequest
    {
        MiniPaviCli::start();

        return $this->request();
    }

    /**
     * The request read by receive(), as MiniPaviCli decoded it: values that aren't text
     * become empty
     */
    public function request(): GatewayRequest
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
        );
    }

    /**
     * Sends the answer to the gateway (the body of the HTTP response)
     */
    public function send(GatewayAnswer $answer): void
    {
        MiniPaviCli::send(
            $answer->vdt->toString(),
            $answer->next ?? $this->url->script(),
            $answer->context,
            $answer->echo,
            $answer->command,
            $answer->directCall->value ?? false,
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
