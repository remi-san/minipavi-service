<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

use MiniPaviService\Page\Parser\AttributeValues;
use MiniPaviService\Text\Placeholders;

/**
 * Variables visible while displaying a part of a template, and where that part is
 * drawn: inside a <foreach>, rows are relative to the first row of the current item.
 */
final readonly class Scope
{
    /**
     * @param array<string, mixed> $vars name => value (scalar, array, object with public properties, iterable)
     * @param ?int $rowOffset row where relative rows start, or null outside of any <foreach>
     */
    public function __construct(
        private array $vars = [],
        private ?int $rowOffset = null,
    ) {}

    /**
     * Row of the page of a row in the scope: inside a <foreach>, relative to the current item
     * (0 = its first row); outside, the row itself
     */
    public function rowOf(int $row): int
    {
        return ($this->rowOffset ?? 0) + $row;
    }

    /**
     * Attributes of an element placed by its row attribute (ElementBuilder::isPlacedByRow():
     * only for such an element, as it sets row), at its row of the page: inside a <foreach>,
     * its row (0 by default) is relative to the current item
     */
    public function placedByRow(AttributeValues $attributes): AttributeValues
    {
        if ($this->rowOffset === null) {
            return $attributes;
        }

        return $attributes->with('row', (string) $this->rowOf($attributes->int('row', default: 0)));
    }

    /**
     * Scope with more variables, drawn from another row
     *
     * @param array<string, mixed> $vars
     */
    public function with(array $vars, int $rowOffset): self
    {
        return new self(array_merge($this->vars, $vars), $rowOffset);
    }

    /**
     * Text with its {{placeholders}} replaced by the values of the variables
     * (Text\Placeholders::substitute())
     *
     * @throws \InvalidArgumentException for an unknown variable, or a value that isn't text
     */
    public function substitute(string $text): string
    {
        return (new Placeholders($this->vars))->substitute($text);
    }

    /**
     * Value of a variable, following dots through arrays and object properties
     * (Text\Placeholders::value())
     *
     * @throws \InvalidArgumentException for an unknown variable
     */
    public function value(string $path): mixed
    {
        return (new Placeholders($this->vars))->value($path);
    }
}
