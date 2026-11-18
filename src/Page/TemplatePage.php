<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\Message;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page;
use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\Node;
use MiniPaviService\Page\Template\Scope;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use MiniPaviService\Vdt\Videotex;

final readonly class TemplatePage implements Page
{
    /**
     * @param Node[] $nodes
     */
    public function __construct(
        private array $nodes,
        private Messages $messages,
        private ?InputField $input = null,
        private Setup $setup = new Setup(),
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function display(Terminal $terminal, array $data = []): Vdt
    {
        return Vdt::join(
            $this->setup->toVdt(),
            VdtHelper::clearScreen(),
            new Vdt(Videotex::CURSOR_OFF),
            $this->toDisplay($data)->toVdt($terminal),
        );
    }

    /**
     * @param string[] $prefill
     */
    public function inputCommand(array $prefill = []): ?InputCommand
    {
        return $this->input === null ? null : new InputCommand($this->input, array_values($prefill));
    }

    public function message(Message $message, array $variables = []): string
    {
        return $this->messages->get($message, $variables);
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
