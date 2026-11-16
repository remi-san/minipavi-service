<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

/**
 * MiniPavi command reading an input field (InputTxt or InputMsg), as built by
 * Page::inputCommand(), and sent to the gateway with Response::readInput()
 */
final readonly class InputCommand
{
    /**
     * @param array<string, mixed> $command the command, as MiniPaviCli builds it
     */
    public function __construct(
        private array $command,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->command;
    }
}
