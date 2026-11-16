<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\{Scope};
use MiniPaviService\Vdt\VdtHelper;

final readonly class TemplatePage implements Page
{
    public function __construct(
        private MiniPaviClient $client,
        private array $nodes,
        private ?Input $input = null,
    ) {}

    public function display(array $data = []): string
    {
        return VdtHelper::clearScreen() . VDT_CUROFF . $this->toDisplay($data)->toVdt($this->client);
    }

    public function readInput(array $prefill = []): ?array
    {
        return $this->input?->toCommand($this->client, $prefill);
    }

    public function toDisplay(array $data = []): Display
    {
        $scope = new Scope($data);
        $elements = [];
        foreach ($this->nodes as $node) {
            array_push($elements, ...$node->elements($scope));
        }
        return new Display(...$elements);
    }
}
