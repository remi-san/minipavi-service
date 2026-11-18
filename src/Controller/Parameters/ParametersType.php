<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Parameters;

/**
 * The type of a controller's parameters (Controller::parametersType()): reads them from the
 * values kept in the context, gives their defaults, and checks that parameters are of it.
 *
 * @template-covariant T of Parameters
 */
final readonly class ParametersType
{
    /**
     * @param class-string<T> $class
     */
    private function __construct(
        private string $class,
    ) {}

    /**
     * Parameters of a class implementing Parameters
     *
     * @template P of Parameters
     *
     * @param class-string<P> $class
     *
     * @return self<P>
     */
    public static function of(string $class): self
    {
        return new self($class);
    }

    /**
     * No parameters (NoParameters)
     *
     * @return self<NoParameters>
     */
    public static function none(): self
    {
        return new self(NoParameters::class);
    }

    /**
     * Parameters read from values (Parameters::fromValues()): what's missing or invalid gets
     * its default
     *
     * @return T
     */
    public function fromValues(Values $values): Parameters
    {
        return $this->class::fromValues($values);
    }

    /**
     * The default parameters
     *
     * @return T
     */
    public function defaults(): Parameters
    {
        return $this->fromValues(new Values());
    }

    /**
     * Whether parameters are of this type
     */
    public function accepts(Parameters $parameters): bool
    {
        return $parameters instanceof $this->class;
    }

    /**
     * Its class, e.g. in errors
     *
     * @return class-string<T>
     */
    public function name(): string
    {
        return $this->class;
    }
}
