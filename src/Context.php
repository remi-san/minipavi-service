<?php

declare(strict_types=1);

namespace MiniPaviService;

use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * User context, sent to the gateway with each answer and given back with the next request:
 * the current route, and the state and data of each controller
 */
final class Context
{
    /**
     * @param array<string, string> $states state of each route
     * @param array<string, array<string, mixed>> $parameters parameters of each route
     */
    public function __construct(
        private string $route,
        private array $states = [],
        private array $parameters = [],
    ) {}

    /**
     * Context sent with the previous answer (JSON, see serialize()); a context without route
     * (e.g. sent by another version of the service) starts at defaultRoute
     */
    public static function unserialize(string $serialized, string $defaultRoute): self
    {
        $context = self::decode($serialized);

        return new self(
            \is_string($context['route'] ?? null) ? $context['route'] : $defaultRoute,
            self::validStates($context['states'] ?? []),
            self::validParameters($context['parameters'] ?? []),
        );
    }

    /**
     * States of an unserialized context: route => state, other entries are dropped
     *
     * @return array<string, string>
     */
    private static function validStates(mixed $states): array
    {
        $valid = [];
        foreach (\is_array($states) ? $states : [] as $route => $state) {
            if (\is_string($route) && \is_string($state)) {
                $valid[$route] = $state;
            }
        }

        return $valid;
    }

    /**
     * Parameters of an unserialized context: route => (name => value), other entries are
     * dropped
     *
     * @return array<string, array<string, mixed>>
     */
    private static function validParameters(mixed $parameters): array
    {
        $valid = [];
        foreach (\is_array($parameters) ? $parameters : [] as $route => $values) {
            if (\is_string($route) && \is_array($values)) {
                $valid[$route] = array_filter($values, \is_string(...), ARRAY_FILTER_USE_KEY);
            }
        }

        return $valid;
    }

    /**
     * The context as JSON: {"route": ..., "states": {route: state}, "parameters": {route:
     * {name: value}}}. Parameters must be JSON values (UTF-8 text, numbers, booleans, null,
     * arrays).
     */
    public function serialize(): string
    {
        try {
            return json_encode([
                'route' => $this->route,
                'states' => (object) $this->states,
                'parameters' => (object) array_map(fn(array $parameters) => (object) $parameters, $this->parameters),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot serialize context: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<mixed>
     */
    private static function decode(string $serialized): array
    {
        try {
            $context = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot unserialize context: ' . $e->getMessage(), 0, $e);
        }
        if (!\is_array($context)) {
            throw new \RuntimeException('Cannot unserialize context: not a JSON object');
        }

        return $context;
    }

    public function route(): string
    {
        return $this->route;
    }

    /**
     * Starts again at route, as a new connection: without the states and parameters of the
     * routes
     */
    public function restart(string $route): void
    {
        $this->route = $route;
        $this->states = [];
        $this->parameters = [];
    }

    public function changeRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * Request for the controller of the current route, with the input of the gateway
     *
     * @param list<string> $content
     */
    public function getRequest(string $key = '', array $content = [], Terminal $terminal = new Terminal()): Request
    {
        return new Request(
            $this->state(),
            $this->parameters(),
            $key,
            $content,
            $terminal,
        );
    }

    public function update(Response $response): void
    {
        $this->updateState($response->state());
        $this->updateParameters($response->parameters());
    }

    public function state(): string
    {
        return $this->states[$this->route] ?? Controller::STATE_INIT;
    }

    public function updateState(string $state): void
    {
        $this->states[$this->route] = $state;
    }

    public function resetState(): void
    {
        $this->updateState(Controller::STATE_INIT);
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters[$this->route] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateParameters(array $data): void
    {
        $this->parameters[$this->route] = $data;
    }

    public function resetParameters(): void
    {
        $this->updateParameters([]);
    }
}
