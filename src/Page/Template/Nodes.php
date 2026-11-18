<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

/**
 * Nodes one after the other (a template's, or the children of <foreach> and <if>): their
 * elements in order
 */
final readonly class Nodes implements Node
{
    /**
     * @param list<Node> $nodes
     */
    public function __construct(
        private array $nodes,
    ) {}

    public function elements(Scope $scope): array
    {
        $elements = [];
        foreach ($this->nodes as $node) {
            array_push($elements, ...$node->elements($scope));
        }

        return $elements;
    }
}
