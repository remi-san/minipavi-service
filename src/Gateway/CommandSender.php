<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * How a client sends each command to its terminal (Command::sendWith()): a method per
 * command, named after what the gateway does, so that a client can't be loaded without
 * sending them all. A new command adds its method here, and to each client's sender
 * (MiniPaviCommandWriter, HtmlCommandRenderer).
 *
 * @template T what the client makes of a command
 */
interface CommandSender
{
    /**
     * Waits for the user's next key, then calls this service with it
     *
     * @return T
     */
    public function waitForKey(WaitForKeyCommand $command): mixed;

    /**
     * Reads an input field, then calls this service with what was typed
     *
     * @return T
     */
    public function readInput(InputCommand $command): mixed;

    /**
     * Calls a service at once, without waiting for the user
     *
     * @return T
     */
    public function callService(ServiceCallCommand $command): mixed;
}
