<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\Node;
use MiniPaviService\Page\Template\Scope;
use MiniPaviService\Vdt\VdtHelper;
use MiniPaviService\Vdt\Videotex;

final readonly class TemplatePage implements Page
{
    /**
     * @param Node[] $nodes
     */
    public function __construct(
        private MiniPaviClient $client,
        private array $nodes,
        private ?Input $input = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function display(array $data = []): string
    {
        return VdtHelper::clearScreen() . Videotex::CURSOR_OFF . $this->toDisplay($data)->toVdt($this->client);
    }

    /**
     * @param string[] $prefill
     */
    public function inputCommand(array $prefill = []): ?InputCommand
    {
        return $this->input?->toCommand($this->client, $prefill);
    }

    /**
     * @param array<string, mixed> $data
     */
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
