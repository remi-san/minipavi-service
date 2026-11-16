<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Vdt\VdtHelper;

/**
 * Page whose content is always the same: a Display, and optionally an input field
 */
final readonly class StaticPage implements Page
{
    public function __construct(
        private MiniPaviClient $client,
        private Display $display,
        private ?Input $input = null,
    ) {}

    /**
     * Clears the screen, then draws the display; a static page has no variables,
     * data is not used
     *
     * @param array<string, mixed> $data
     */
    public function display(array $data = []): string
    {
        return VdtHelper::clearScreen() . VDT_CUROFF . $this->display->toVdt($this->client);
    }

    /**
     * @param string[] $prefill
     * @return ?array<string, mixed>
     */
    public function readInput(array $prefill = []): ?array
    {
        return $this->input?->toCommand($this->client, $prefill);
    }
}
