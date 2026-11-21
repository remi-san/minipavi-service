<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Page\Template\IfNode;
use MiniPaviService\Page\Template\Nodes;

/**
 * The test of an <if> (IfTagParser), as XmlTag::check() reads it: the variable checked, and
 * whether a "!" negates it
 *
 * @internal
 */
final readonly class IfTest
{
    /**
     * @param string $variable variable to check, a name or a dotted path
     * @param bool $negate true for test="!variable"
     */
    public function __construct(
        private string $variable,
        private bool $negate,
    ) {}

    /**
     * The <if> drawing children when this test holds
     *
     * @param string $location file and line of the <if>, for its errors
     */
    public function node(Nodes $children, string $location): IfNode
    {
        return new IfNode($this->variable, $this->negate, $children, $location);
    }
}
