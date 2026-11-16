<?php

namespace MiniPaviService;

use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * User context, sent to the gateway with each answer and given back with the next request:
 * the current route, and the state and data of each controller
 */
final class Context
{
    public function __construct(
        private string $route,
        private array $states = [],
        private array $parameters = [],
    ) {}

    public static function unserialize(string $serialized): self
    {
        $context = @unserialize($serialized, ['allowed_classes' => false]);
        if (!\is_array($context)) {
            throw new \RuntimeException('Cannot unserialize context');
        }

        return new self(
            isset($context['route']) ? (string) $context['route'] : null,
            (array) ($context['states'] ?? []),
            (array) ($context['parameters'] ?? []),
        );
    }

    public function serialize(): string
    {
        return serialize([
            'route' => $this->route,
            'states' => $this->states,
            'parameters' => $this->parameters,
        ]);
    }

    public function route(): string
    {
        return $this->route;
    }

    public function changeRoute(string $route): void
    {
        $this->route = $route;
    }

    public function getRequest(): Request
    {
        return new Request(
            $this->state(),
            $this->parameters(),
        );
    }

    public function update(Response $response): void
    {
        $this->updateState($response->state());
        $this->updateParameters($response->parameters());
    }

    public function state(): ?string
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

    public function parameters(): array
    {
        return $this->parameters[$this->route] ?? [];
    }

    public function updateParameters(array $data): void
    {
        $this->parameters[$this->route] = $data;
    }

    public function resetParameters(): void
    {
        $this->updateParameters([]);
    }
}
