<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

/**
 * What a request of the gateway comes with, as its key (fctn): a case of a string-backed enum
 * implementing this interface, whose value is what the gateway sends. FunctionKey has the keys
 * the user presses; ConnectionCode the connections, disconnections and background calls.
 */
interface Key extends \BackedEnum
{
    /**
     * The function key it is, null for a connection code
     */
    public function functionKey(): ?FunctionKey;

    /**
     * The connection code it is, null for a function key
     */
    public function connectionCode(): ?ConnectionCode;

    /**
     * Tells the handler what happened, with its method for this key
     *
     * @template T
     *
     * @param KeyHandler<T> $handler
     *
     * @return T
     */
    public function handleWith(KeyHandler $handler): mixed;
}
