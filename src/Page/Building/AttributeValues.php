<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Building;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Position;

/**
 * Attributes of an element of the page XML format, read typed: a value missing gets its
 * default, an invalid one throws a PageXmlException naming the attribute, without location
 * (callers add it)
 */
final readonly class AttributeValues
{
    /**
     * @param array<string, string> $values name => value, as in the XML file (placeholders
     *                                     replaced in a template)
     */
    public function __construct(
        private array $values,
    ) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function string(string $name, string $default = ''): string
    {
        return $this->values[$name] ?? $default;
    }

    /**
     * A value that must be given, trimmed
     *
     * @throws PageXmlException if it is absent or blank
     */
    public function required(string $name): string
    {
        $value = trim($this->values[$name] ?? '');
        if ($value === '') {
            throw new PageXmlException("{$name} is missing");
        }

        return $value;
    }

    /**
     * A number (digits only), or default if absent
     *
     * @throws PageXmlException if it isn't a number
     */
    public function int(string $name, int $default): int
    {
        return $this->optionalInt($name) ?? $default;
    }

    /**
     * A number (digits only), or null if absent
     *
     * @throws PageXmlException if it isn't a number
     */
    public function optionalInt(string $name): ?int
    {
        if (!$this->has($name)) {
            return null;
        }
        $value = trim($this->values[$name]);
        if (!preg_match('/^\d+$/', $value)) {
            throw new PageXmlException("{$name}=\"{$value}\" is not a number");
        }

        return (int) $value;
    }

    /**
     * The cell of the row and col attributes: row defaultRow and column 1 when they are absent
     *
     * @throws PageXmlException if one isn't a number
     * @throws InvalidValueException for a cell off the screen
     */
    public function position(int $defaultRow = 1): Position
    {
        return new Position($this->int('row', $defaultRow), $this->int('col', default: 1));
    }

    /**
     * What one of the values gives, or default if absent
     *
     * @template T
     *
     * @param array<string, T> $values value of the attribute => result
     * @param T $default
     *
     * @return T
     *
     * @throws PageXmlException for another value
     */
    public function choice(string $name, array $values, mixed $default): mixed
    {
        if (!$this->has($name)) {
            return $default;
        }
        $value = $this->values[$name];
        if (!array_key_exists($value, $values)) {
            throw new PageXmlException("{$name}=\"{$value}\" must be " . implode(' or ', array_keys($values)));
        }

        return $values[$value];
    }

    /**
     * The one of these cases whose value is the attribute's, or null if absent, e.g.
     * enum('color', ...Color::cases()). At least one case: the attribute's value can't be
     * among none.
     *
     * @template T of \BackedEnum
     *
     * @param T $case
     * @param T ...$cases
     *
     * @return ?T
     *
     * @throws PageXmlException for another value, naming the cases' values
     */
    public function enum(string $name, \BackedEnum $case, \BackedEnum ...$cases): ?\BackedEnum
    {
        if (!$this->has($name)) {
            return null;
        }
        $value = $this->values[$name];
        $cases = [$case, ...$cases];
        foreach ($cases as $case) {
            if ((string) $case->value === $value) {
                return $case;
            }
        }

        throw new PageXmlException(
            "unknown {$name} \"{$value}\" ("
            . implode(', ', array_map(static fn(\BackedEnum $case) => $case->value, $cases))
            . ')',
        );
    }

    /**
     * These attributes, with name set to value
     */
    public function with(string $name, string $value): self
    {
        return new self([$name => $value] + $this->values);
    }

    /**
     * These attributes, without name
     */
    public function without(string $name): self
    {
        return new self(array_diff_key($this->values, [$name => '']));
    }
}
