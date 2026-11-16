<?php

namespace MiniPaviService;

/**
 * User context, sent to the gateway with each answer and given back with the next request:
 * the current route, and the state and data of each controller
 */
final class Context {

    /**
     * @param array<string, string> $controllerStates state of each controller, by route
     * @param array<string, array> $controllerData data of each controller, by route
     */
    public function __construct(
        public ?string $route = null,
        private array $controllerStates = [],
        private array $controllerData = [],
    ) {
    }

    /**
     * Context given back by the gateway, or an empty one if it is missing or invalid.
     * It is stored as a plain array, so that no object is built from what the gateway sends
     */
    public static function unserialize(string $serialized): self {
        $context = @unserialize($serialized, ['allowed_classes' => false]);
        if (!\is_array($context)) {
            return new self();
        }

        return new self(
            isset($context['route']) ? (string)$context['route'] : null,
            (array)($context['controller_state'] ?? []),
            (array)($context['controller_data'] ?? []),
        );
    }

    public function serialize(): string {
        return serialize([
            'route' => $this->route,
            'controller_state' => $this->controllerStates,
            'controller_data' => $this->controllerData,
        ]);
    }

    public function controllerState(string $route): ?string {
        return $this->controllerStates[$route] ?? null;
    }

    public function setControllerState(string $route, string $state): void {
        $this->controllerStates[$route] = $state;
    }

    public function controllerData(string $route): array {
        return $this->controllerData[$route] ?? [];
    }

    public function setControllerData(string $route, array $data): void {
        $this->controllerData[$route] = $data;
    }
}
