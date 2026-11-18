<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Core\Signing\ContextSigner;
use MiniPaviService\Core\Signing\ForgedContextException;
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
     * @param string $key function key pressed (FunctionKey), or connection code (ConnectionCode), as the
     *                    gateway sends it
     * @param list<string> $content text typed, one string per line of the input field
     * @param string $context context sent with the previous answer ('' if none), signed as it
     *                        comes from the gateway (see verify())
     * @param string $serviceUrl URL of the service the request was sent to (Http\RequestUrl):
     *                           the next URL of answers without one; '' for a request built
     *                           in memory
     * @param TState $clientState what the client needs to answer, besides what the service
     *                            gets: given back to it with the request (Client::response())
     */
    public function __construct(
        public string $key,
        public array $content = [],
        public string $context = '',
        public Terminal $terminal = new Terminal(),
        public string $serviceUrl = '',
        public ClientState $clientState = new NoClientState(),
    ) {}

    /**
     * The request with its context checked by signer, and without its signature: the context
     * the service sent with the previous answer (GatewayResponse::sign()). Unchanged for a
     * connection, a disconnection or a background call, whose context isn't read.
     *
     * @return self<TState>
     *
     * @throws ForgedContextException if signer didn't sign the context
     */
    public function verify(ContextSigner $signer): self
    {
        if ($this->isConnecting() || $this->isDisconnecting() || $this->isBackgroundCall()) {
            return $this;
        }

        return new self(
            $this->key,
            $this->content,
            $signer->verify($this->context),
            $this->terminal,
            $this->serviceUrl,
            $this->clientState,
        );
    }

    /**
     * Connection code of the key, null for a function key
     */
    public function connectionCode(): ?ConnectionCode
    {
        return ConnectionCode::of($this->key);
    }

    public function isConnecting(): bool
    {
        return $this->connectionCode()?->isConnection() ?? false;
    }

    public function isDisconnecting(): bool
    {
        return $this->connectionCode() === ConnectionCode::Disconnect;
    }

    /**
     * A background call of the gateway (ConnectionCode::BackgroundCall), which the user didn't
     * make
     */
    public function isBackgroundCall(): bool
    {
        return $this->connectionCode() === ConnectionCode::BackgroundCall;
    }
}
