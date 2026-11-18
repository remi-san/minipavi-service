<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

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
     * The case of a string-backed enum whose value is the attribute's, or null if absent
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return ?T
     *
     * @throws PageXmlException for a value the enum doesn't have
     */
    public function enum(string $name, string $enum): ?\BackedEnum
    {
        if (!$this->has($name)) {
            return null;
        }

        return (
            $enum::tryFrom($this->values[$name]) ?? throw new PageXmlException(
                "unknown {$name} \""
                . $this->values[$name]
                . '" ('
                . implode(', ', array_map(static fn(\BackedEnum $case) => (string) $case->value, $enum::cases()))
                . ')',
            )
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
