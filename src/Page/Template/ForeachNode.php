<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

/**
 * <foreach items="services" as="service" row="5" step="4">: draws its children once
 * per item, each item step rows below the previous one. Children rows are relative
 * to the first row of the item (0 = first row). Inside, {{service}} is the item and
 * {{loop.index}} (from 1), loop.count, loop.first and loop.last describe the loop.
 */
final readonly class ForeachNode implements Node
{
    /**
     * @param string $items variable holding the items (array or iterable)
     * @param int $row first row of the first item; relative to the enclosing item if nested
     * @param Node[] $children
     */
    public function __construct(
        private string $items,
        private string $as,
        private int $row,
        private int $step,
        private array $children,
        private string $location,
    ) {}

    public function elements(Scope $scope): array
    {
        try {
            $items = $scope->value($this->items);
        } catch (\InvalidArgumentException $e) {
            throw new TemplateException("$this->location: <foreach>: " . $e->getMessage(), 0, $e);
        }
        if (!is_iterable($items)) {
            throw new TemplateException("$this->location: <foreach>: {{{$this->items}}} is not a list (" . get_debug_type($items) . ')');
        }
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        $start = ($scope->rowOffset ?? 0) + $this->row;
        $count = count($items);
        $elements = [];
        foreach ($items as $i => $item) {
            $itemScope = $scope->with([
                $this->as => $item,
                'loop' => ['index' => $i + 1, 'count' => $count, 'first' => $i == 0, 'last' => $i == $count - 1],
            ], $start + $i * $this->step);
            foreach ($this->children as $child) {
                array_push($elements, ...$child->elements($itemScope));
            }
        }
        return $elements;
    }
}
