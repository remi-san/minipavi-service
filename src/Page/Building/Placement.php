<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

/**
 * Where a display element is in its page, given to its ElementBuilder, which places itself or
 * refuses to be there: on the page itself, or inside a <foreach>, whose items' rows are known
 * only when they are drawn
 */
final readonly class Placement
{
    /**
     * @param bool $inLoop whether it is inside a <foreach>
     * @param ?int $firstRow first row of the page of the current item; null on the page, or in a
     *                       <foreach> when the page is read
     */
    private function __construct(
        private bool $inLoop,
        private ?int $firstRow,
    ) {}

    /**
     * On the page, outside of any <foreach>
     */
    public static function page(): self
    {
        return new self(inLoop: false, firstRow: null);
    }

    /**
     * Inside a <foreach>, when the page is read: the rows of its items aren't known yet
     */
    public static function loop(): self
    {
        return new self(inLoop: true, firstRow: null);
    }

    /**
     * Inside an item of a <foreach>, drawn from firstRow of the page
     */
    public static function loopItem(int $firstRow): self
    {
        return new self(inLoop: true, firstRow: $firstRow);
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
        if ($this->firstRow === null) {
            return $attributes->without('row');
        }

        return $attributes->with('row', (string) ($this->firstRow + $attributes->int('row', default: 0)));
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
