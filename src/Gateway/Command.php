<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * What the gateway does after showing an answer's content, besides waiting for a key: read an
 * input field (InputCommand), call a service (ServiceCallCommand)... Each client sends it its
 * own way, through its CommandSender: a command calls the sender's method for it.
 */
interface Command
{
    /**
     * Sends this command with the sender's method for it
     *
     * @template T
     *
     * @param CommandSender<T> $sender
     *
     * @return T
     */
    public function sendWith(CommandSender $sender): mixed;
}
