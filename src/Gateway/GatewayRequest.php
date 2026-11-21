<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Key;
use MiniPaviService\Minitel\Terminal;

/**
 * Request of the MiniPavi gateway: what the user did (the key pressed, the text typed), the
 * context sent with the previous response, and where the user connects from
 */
final readonly class GatewayRequest
{
    /**
     * @param ?Key $key function key pressed (FunctionKey), or connection code (ConnectionCode);
     *                  null for another key (see keyOf())
     * @param list<string> $content text typed, one string per line of the input field
     * @param string $context context sent with the previous response ('' if none), as it comes
     *                        from the gateway: signed (ContextSerializer)
     */
    public function __construct(
        public ?Key $key,
        public array $content = [],
        public string $context = '',
        public Terminal $terminal = new Terminal(),
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
}
