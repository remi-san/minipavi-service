<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Core\Terminal;

/**
 * What a controller runs on: its state and parameters, kept in the context from the previous
 * request, and what the user did since: the key pressed and the text typed
 */
readonly class Request
{
    /**
     * @param string $state state kept in the context (a State's value): see stateAs()
     * @param array<string, mixed> $parameters parameters kept in the context: see
     *                                         parametersAs() and int(), string()...
     * @param string $key function key pressed (FunctionKey), or connection code
     *                    (GatewayRequest::FCTN_*)
     * @param list<string> $content text typed, one string per line of the input field
     */
    public function __construct(
        private string $state = ControllerState::Init->value,
        private array $parameters = [],
        private string $key = '',
        private array $content = [],
        private Terminal $terminal = new Terminal(),
    ) {}

    /**
     * The parameters as an object (Parameters::fromRequest())
     *
     * @template T of Parameters
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function parametersAs(string $class): Parameters
    {
        return $class::fromRequest($this);
    }

    /**
     * The state as a case of a State enum (ControllerState, or the controller's own); default
     * for a state the enum doesn't have (e.g. kept by another version of the service, or by
     * another enum)
     *
     * @template T of State
     *
     * @param class-string<T> $enum
     * @param T $default
     *
     * @return T
     */
    public function stateAs(string $enum, State $default): State
    {
        foreach ($enum::cases() as $case) {
            if ((string) $case->value === $this->state) {
                return $case;
            }
        }

        return $default;
    }

    /**
     * A parameter as it was kept, null if missing: int(), string(), bool() and array() give
     * it typed
     */
    private function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * A parameter as an int: an int, or an integer as a float or as text ('12'); default
     * otherwise (missing, or another value, e.g. from a context of another version)
     */
    public function int(string $name, int $default = 0): int
    {
        $value = $this->parameter($name);
        // A float holds integers exactly up to 2^53
        if (\is_float($value) && $value === floor($value) && abs($value) <= 2 ** 53) {
            $value = (int) $value;
        }
        if (\is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            $value = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }

        return \is_int($value) ? $value : $default;
    }

    /**
     * A parameter as text: text, or a number as text; default otherwise
     */
    public function string(string $name, string $default = ''): string
    {
        $value = $this->parameter($name);

        return match (true) {
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => $default,
        };
    }

    /**
     * A parameter as a bool: a bool, 1 or 0, or 'true', 'false', '1' or '0' as text; default
     * otherwise
     */
    public function bool(string $name, bool $default = false): bool
    {
        return match ($this->parameter($name)) {
            true, 1, 'true', '1' => true,
            false, 0, 'false', '0' => false,
            default => $default,
        };
    }

    /**
     * A parameter as an array; default otherwise
     *
     * @param array<mixed> $default
     *
     * @return array<mixed>
     */
    public function array(string $name, array $default = []): array
    {
        $value = $this->parameter($name);

        return \is_array($value) ? $value : $default;
    }

    /**
     * Name of the function key pressed (FunctionKey), or connection code
     * (GatewayRequest::FCTN_*); see functionKey()
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Function key pressed, null for a connection code (GatewayRequest::FCTN_*)
     */
    public function functionKey(): ?FunctionKey
    {
        return FunctionKey::tryFrom($this->key);
    }

    /**
     * Text typed, one string per line of the input field
     *
     * @return list<string>
     */
    public function content(): array
    {
        return $this->content;
    }

    /**
     * Text typed, its lines put together
     */
    public function contentAsString(): string
    {
        return implode('', $this->content);
    }

    public function terminal(): Terminal
    {
        return $this->terminal;
    }
}
