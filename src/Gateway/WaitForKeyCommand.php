<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * Command waiting for the user's next key, then calling this service with it: what the gateway
 * does when an answer asks for nothing else (Response::display())
 */
final readonly class WaitForKeyCommand implements Command
{
    public function sendWith(CommandSender $sender): mixed
    {
        return $sender->waitForKey($this);
    }
}
