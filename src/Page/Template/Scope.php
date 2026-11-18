<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Template;

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
        public array $vars = [],
        public ?int $rowOffset = null,
    ) {}

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
