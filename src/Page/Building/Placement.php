<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

/**
 * Where a display element is in its page, given to its ElementBuilder, which places itself or
 * refuses to be there: on the page itself, or inside a <foreach>, whose items' rows are known
 * only when they are drawn. Each of its methods answers in all three.
 */
final readonly class Placement
{
    /**
     * @param bool $inLoop whether it is inside a <foreach>
     * @param ?Rows $itemRows the rows of the current item of the <foreach>; null on the page, or
     *                        in a <foreach> when the page is read
     */
    private function __construct(
        private bool $inLoop,
        private ?Rows $itemRows,
    ) {}

    /**
     * On the page, outside of any <foreach>
     */
    public static function page(): self
    {
        return new self(inLoop: false, itemRows: null);
    }

    /**
     * Inside a <foreach>, when the page is read: the rows of its items aren't known yet
     */
    public static function loop(): self
    {
        return new self(inLoop: true, itemRows: null);
    }

    /**
     * Inside an item of a <foreach>, whose rows are itemRows
     */
    public static function loopItem(Rows $itemRows): self
    {
        return new self(inLoop: true, itemRows: $itemRows);
    }

    /**
     * Attributes of an element placed by its row attribute, with row its row of the page:
     * inside an item of a <foreach>, its row (0 by default) is relative to the item's first
     * row; inside a <foreach> when the page is read, without row, checked when the items are
     * drawn
     */
    public function placeByRow(AttributeValues $attributes): AttributeValues
    {
        if (!$this->inLoop) {
            return $attributes;
        }
        if ($this->itemRows === null) {
            return $attributes->without('row');
        }

        return $attributes->with('row', (string) $this->itemRows->rowOf($attributes->int('row', default: 0)));
    }

    /**
     * Refuses an element inside a <foreach> (e.g. one drawn once per page)
     *
     * @throws PageXmlException inside a <foreach>
     */
    public function refuseInLoop(): void
    {
        if ($this->inLoop) {
            throw new PageXmlException('not inside <foreach>');
        }
    }
}
