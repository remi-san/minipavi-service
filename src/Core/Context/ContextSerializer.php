<?php

declare(strict_types=1);

namespace MiniPaviService\Core\Context;

use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Core\Context\Signing\ContextSigner;
use MiniPaviService\Core\Context\Signing\ForgedContextException;
use MiniPaviService\Core\Context\Signing\UnsignedContext;
use MiniPaviService\Core\Routing\Router;
use MiniPaviService\Core\Routing\UnknownRouteException;
use MiniPaviService\InvalidValueException;

/**
 * The context as it goes to the gateway and comes back, JSON: {"route": ..., "states": {route:
 * state}, "parameters": {route: {name: value}}}, states as their enum's value, parameters as
 * Parameters::toArray(). Read back typed by the router's routes (Route::restore()). It is
 * signed by the signer (HmacContextSigner), so that a context the service didn't send (e.g.
 * POSTed by anyone, with another route or other parameters) isn't restored.
 */
final readonly class ContextSerializer
{
    /**
     * @param ContextSigner $signer signs the context sent, and checks the one given back;
     *                              UnsignedContext not to sign it
     */
    public function __construct(
        private Router $router,
        private ContextSigner $signer = new UnsignedContext(),
    ) {}

    /**
     * @throws InvalidValueException if a parameter can't be JSON (e.g. text that isn't UTF-8)
     */
    public function serialize(Context $context): string
    {
        // The format, here only: each kept state by its value, each kept parameters by theirs
        $states = [];
        $parameters = [];
        foreach ($context->kept() as $route => $kept) {
            $states[$route] = $kept->state->value;
            // An object, even empty: {} rather than []
            $parameters[$route] = (object) $kept->parameters->toArray();
        }
        try {
            return $this->signer->sign(json_encode(
                [
                    'route' => $context->route(),
                    'states' => (object) $states,
                    'parameters' => (object) $parameters,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } catch (\JsonException $e) {
            throw new InvalidValueException('Cannot serialize context: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * The context serialize() gave, typed by the router's routes: a state their enum doesn't
     * have is the initial one, invalid parameters get their defaults, and kept routes the
     * router doesn't have are dropped. A context without route (e.g. sent by another version
     * of the service) starts at the default route.
     *
     * @throws UnknownRouteException for a context at a route the router doesn't have
     * @throws ForgedContextException if the signer didn't sign it
     * @throws UnreadableContextException if it isn't a JSON object
     */
    public function unserialize(string $serialized): Context
    {
        $context = self::decode($this->signer->verify($serialized));
        $current = \is_string($context['route'] ?? null) ? $context['route'] : $this->router->defaultRoute();
        // Throws UnknownRouteException if the router doesn't have it
        $this->router->route($current);
        $states = \is_array($context['states'] ?? null) ? $context['states'] : [];
        $parameters = \is_array($context['parameters'] ?? null) ? $context['parameters'] : [];

        $routes = [];
        foreach (array_keys($states + $parameters) as $route) {
            if (!(\is_string($route) && $this->router->has($route))) {
                continue;
            }

            $state = \is_string($states[$route] ?? null) ? $states[$route] : null;
            $routes[$route] = $this->router->route($route)->restore(
                $state,
                new Values(self::values($parameters[$route] ?? null)),
            );
        }

        return new Context($this->router, $current, $routes);
    }

    /**
     * Values of the parameters of a route: those with a name
     *
     * @return array<string, mixed>
     */
    private static function values(mixed $parameters): array
    {
        $values = [];
        foreach (\is_array($parameters) ? $parameters : [] as $name => $value) {
            if (!\is_string($name)) {
                continue;
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * @return array<mixed>
     */
    private static function decode(string $serialized): array
    {
        try {
            $context = json_decode($serialized, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnreadableContextException('Cannot unserialize context: ' . $e->getMessage(), previous: $e);
        }
        if (!\is_array($context)) {
            throw new UnreadableContextException('Cannot unserialize context: not a JSON object');
        }

        return $context;
    }
}
