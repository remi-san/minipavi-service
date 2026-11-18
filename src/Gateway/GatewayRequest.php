<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;

/**
 * Request of the MiniPavi gateway: what the user did (the key pressed, the text typed), the
 * context sent with the previous answer, and where the user connects from
 *
 * @template-covariant TState of ClientState
 */
final readonly class GatewayRequest
{
    /**
     * @param ?Key $key function key pressed (FunctionKey), or connection code (ConnectionCode);
     *                  null for another key (see keyOf())
     * @param list<string> $content text typed, one string per line of the input field
     * @param string $context context sent with the previous answer ('' if none), as it comes
     *                        from the gateway: signed (ContextSerializer)
     * @param string $serviceUrl URL of the service the request was sent to (Http\RequestUrl):
     *                           the next URL of answers without one; '' for a request built
     *                           in memory
     * @param TState $clientState what the client needs to answer, besides what the service
     *                            gets: given back to it with the request (Client::response())
     */
    public function __construct(
        public ?Key $key,
        public array $content = [],
        public string $context = '',
        public Terminal $terminal = new Terminal(),
        public string $serviceUrl = '',
        public ClientState $clientState = new NoClientState(),
    ) {}

    /**
     * Key of a request from what the gateway sends (fctn): a function key, a connection code,
     * or null for anything else
     */
    public static function keyOf(string $fctn): ?Key
    {
        return FunctionKey::tryFrom($fctn) ?? ConnectionCode::of($fctn);
    }

    /**
     * Name of the key, as the gateway sends it ('ENVOI', 'CNX'...); '' for none
     */
    public function fctn(): string
    {
        return (string) ($this->key->value ?? '');
    }

    /**
     * A connection (ConnectionCode::isConnection()): a new one, or from another service
     */
    public function isConnecting(): bool
    {
        return $this->key?->connectionCode()?->isConnection() ?? false;
    }

    /**
     * A disconnection (ConnectionCode::Disconnect)
     */
    public function isDisconnecting(): bool
    {
        return $this->key === ConnectionCode::Disconnect;
    }

    /**
     * A background call of the gateway (ConnectionCode::BackgroundCall), which the user didn't
     * make
     */
    public function isBackgroundCall(): bool
    {
        return $this->key === ConnectionCode::BackgroundCall;
    }
}
