<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Template\ForeachNode;
use MiniPaviService\Page\Template\Nodes;

/**
 * The attributes of a <foreach> (ForeachTagParser), as XmlTag::check() reads them: the
 * variable holding the items, the name of each, the row of the first and the rows from one
 * to the next
 *
 * @internal
 */
final readonly class ForeachLoop
{
    /**
     * @param string $items variable holding the items
     * @param string $as name of each item in the children
     * @param int $row first row of the first item; relative to the enclosing item if nested
     * @param int $step rows from an item to the next
     */
    public function __construct(
        private string $items,
        private string $as,
        private int $row,
        private int $step,
    ) {}

    /**
     * The <foreach> drawing children once per item
     *
     * @param string $location file and line of the <foreach>, for its errors
     */
    public function node(Nodes $children, string $location): ForeachNode
    {
        return new ForeachNode($this->items, $this->as, $this->row, $this->step, $children, $location);
    }
}
