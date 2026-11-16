<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;

interface Response
{
    public function execute(MiniPaviClient $client, Context $context): void;

    public function interrupts(): bool;

    public function state(): string;

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array;
}
