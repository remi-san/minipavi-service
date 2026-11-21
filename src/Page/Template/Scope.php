<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Page\Building\Placement;
use MiniPaviService\Page\Building\Rows;
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
     * The rows given in the scope (e.g. a nested <foreach>'s): those of the page outside of
     * any <foreach> (from row 0), those of the current item inside
     */
    public function rows(): Rows
    {
        return new Rows($this->rowOffset ?? 0);
    }

    /**
     * Where the elements of the scope are, for their ElementBuilder: on the page, or in the
     * current item of a <foreach>
     */
    public function placement(): Placement
    {
        return $this->rowOffset === null ? Placement::page() : Placement::loopItem($this->rows());
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
     * @throws InvalidValueException for an unknown variable, or a value that isn't text
     */
    public function substitute(string $text): string
    {
        return (new Placeholders($this->vars))->substitute($text);
    }

    /**
     * Value of a variable, following dots through arrays and object properties
     * (Text\Placeholders::value())
     *
     * @throws InvalidValueException for an unknown variable
     */
    public function value(string $path): mixed
    {
        return (new Placeholders($this->vars))->value($path);
    }
}
