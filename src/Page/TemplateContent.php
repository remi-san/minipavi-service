<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\Node;
use MiniPaviService\Page\Template\Scope;

/**
 * Content drawn with data (<page type="template">): its nodes, with {{placeholders}} and
 * instructions (<foreach>, <if>), give the elements for the variables
 */
final readonly class TemplateContent implements Content
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        private array $nodes,
    ) {}

    public function display(array $data): Display
    {
        $scope = new Scope($data);
        $elements = [];
        foreach ($this->nodes as $node) {
            array_push($elements, ...$node->elements($scope));
        }

        return new Display(...$elements);
    }
}
