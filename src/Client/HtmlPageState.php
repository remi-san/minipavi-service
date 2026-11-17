<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\ClientState;
use MiniPaviService\Vdt\Vdt;

/**
 * What the page of HtmlClient posted back with a key: the screen it showed (the answer is
 * drawn over it), and its input command (restored when leaving the service is blocked)
 */
final readonly class HtmlPageState implements ClientState
{
    /**
     * @param ?array{name: string, param: array<string, mixed>} $command input command of the
     *                                                                   page; null: none
     */
    public function __construct(
        public Vdt $screen = new Vdt(),
        public ?array $command = null,
    ) {}
}
