<?php

namespace MiniPaviService\Controller;

use MiniPaviService\Context;
use MiniPaviService\MiniPaviClient;

interface Response {

    public function execute(MiniPaviClient $client, Context $context): void;

    public function interrupts(): bool;

    public function state(): string;

    public function parameters(): array;
}
