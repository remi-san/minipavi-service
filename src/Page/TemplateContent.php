<?php

declare(strict_types=1);

namespace MiniPaviService\Page;

use MiniPaviService\Page\Display\Display;
use MiniPaviService\Page\Template\Nodes;
use MiniPaviService\Page\Template\Scope;

/**
 * Content drawn with data (<page type="template">): its nodes, with {{placeholders}} and
 * instructions (<foreach>, <if>), give the elements for the variables
 */
final readonly class TemplateContent implements Content
{
    public function __construct(
        private Nodes $nodes,
    ) {}

    public function display(array $data): Display
    {
        return new Display(...$this->nodes->elements(new Scope($data)));
    }
}
