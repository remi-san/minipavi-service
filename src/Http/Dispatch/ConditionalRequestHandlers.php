<?php

declare(strict_types=1);

namespace MiniPaviService\Http\Dispatch;

/**
 * The cases of the Dispatcher, in the order they are tried
 *
 * @implements \IteratorAggregate<int, ConditionalRequestHandler>
 */
final readonly class ConditionalRequestHandlers implements \IteratorAggregate, \Countable
{
    /** @var list<ConditionalRequestHandler> */
    private array $handlers;

    public function __construct(ConditionalRequestHandler ...$handlers)
    {
        $this->handlers = array_values($handlers);
    }

    /**
     * @return \ArrayIterator<int, ConditionalRequestHandler>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->handlers);
    }

    public function count(): int
    {
        return \count($this->handlers);
    }
}
