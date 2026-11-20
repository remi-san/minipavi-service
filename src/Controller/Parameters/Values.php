<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Parameters;

/**
 * Raw parameters of a controller, as the context keeps them (JSON, from a previous request,
 * possibly of another version of the service, or forged), read typed by
 * Parameters::fromValues(): each accessor coerces what is safe, and gives the default for a
 * missing or other value
 */
final readonly class Values
{
    /**
     * @param array<string, mixed> $values name => JSON value
     */
    public function __construct(
        private array $values = [],
    ) {}

    /**
     * A value as an int: an int, an integer as a float, or decimal digits as text, with an
     * optional sign and leading zeros ('12', '-3', '+3', '007'), within the int range;
     * default otherwise
     */
    public function int(string $name, int $default = 0): int
    {
        $value = $this->values[$name] ?? null;
        // A float holds integers exactly up to 2^53: within them, an integer is the same as
        // its int
        if (\is_float($value) && abs($value) <= (2 ** 53) && (float) (int) $value === $value) {
            $value = (int) $value;
        }
        // filter_var() rejects leading zeros and accepts surrounding whitespace: the digits
        // are checked here (\z, as $ allows a final newline), and given without their zeros
        $matches = [];
        if (\is_string($value) && preg_match('/^([+-]?)0*([0-9]+)\z/', $value, $matches) === 1) {
            $value = filter_var($matches[1] . $matches[2], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }

        return \is_int($value) ? $value : $default;
    }

    /**
     * A value as text: text, or a number as text; default otherwise
     */
    public function string(string $name, string $default = ''): string
    {
        $value = $this->values[$name] ?? null;

        return match (true) {
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => $default,
        };
    }

    /**
     * A value as a bool: a bool, 1 or 0, or 'true', 'false', '1' or '0' as text; default
     * otherwise
     */
    public function bool(string $name, bool $default = false): bool
    {
        return match ($this->values[$name] ?? null) {
            true, 1, 'true', '1' => true,
            false, 0, 'false', '0' => false,
            default => $default,
        };
    }

    /**
     * A value as an array; default otherwise
     *
     * @param array<mixed> $default
     *
     * @return array<mixed>
     */
    public function array(string $name, array $default = []): array
    {
        $value = $this->values[$name] ?? null;

        return \is_array($value) ? $value : $default;
    }
}
