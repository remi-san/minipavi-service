<?php

declare(strict_types=1);

namespace MiniPaviService\Minitel;

/**
 * What is done with a request of the gateway, by what its key says happened
 * (Key::handleWith()): a method per kind of key, so that each key calls the one it is about,
 * and a handler can't be written without handling them all. E.g. Core\Service serves the
 * request, Controller\Request reads the function key pressed.
 *
 * @template T what the handler makes of the key
 */
interface KeyHandler
{
    /**
     * The user pressed a function key
     *
     * @return T
     */
    public function pressed(FunctionKey $key): mixed;

    /**
     * The user connected: a new connection, or one from another service
     * (ConnectionCode::Connect, ConnectionCode::DirectConnect)
     *
     * @return T
     */
    public function connected(ConnectionCode $code): mixed;

    /**
     * The user disconnected (ConnectionCode::Disconnect)
     *
     * @return T
     */
    public function disconnected(): mixed;

    /**
     * The gateway called in the background (ConnectionCode::BackgroundCall), not the user
     *
     * @return T
     */
    public function calledInBackground(): mixed;
}
