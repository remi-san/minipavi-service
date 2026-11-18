<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

/**
 * Part of a template: a display element (ElementNode), an instruction (ForeachNode, IfNode),
 * or nodes one after the other (Nodes)
 */
interface Node
{
    /**
     * Display elements of this part for the variables of the scope.
     *
     * @return \MiniPaviService\Page\Display\Element[]
     * @throws TemplateException if it cannot be displayed
     */
    public function elements(Scope $scope): array;
}
