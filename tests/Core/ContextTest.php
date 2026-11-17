<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\Terminal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    public function testNewRouteStartsInInitWithoutParameters(): void
    {
        $context = new Context('home');

        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame([], $context->parameters());
        self::assertEquals(new Controller\Request(Controller::STATE_INIT, []), $context->getRequest());
    }

    public function testRequestCarriesTheInputOfTheGateway(): void
    {
        $context = new Context('home');
        $context->updateState(Controller::STATE_READ_INPUT);
        $terminal = new Terminal('192.0.2.1', 'websocket', 'Cv5');

        self::assertEquals(
            new Controller\Request(Controller::STATE_READ_INPUT, [], 'ENVOI', ['abc'], $terminal),
            $context->getRequest('ENVOI', ['abc'], $terminal),
        );
    }

    public function testKeepsStateAndParametersPerRoute(): void
    {
        $context = new Context('home');
        $context->updateState(Controller::STATE_READ_INPUT);
        $context->updateParameters(['page' => 2]);
        $context->changeRoute('directory');

        self::assertSame(Controller::STATE_INIT, $context->state());

        $context->changeRoute('home');
        self::assertSame(Controller::STATE_READ_INPUT, $context->state());
        self::assertSame(['page' => 2], $context->parameters());
        self::assertSame(2, $context->getRequest()->parameter('page'));
    }

    public function testResetsTheCurrentRoute(): void
    {
        $context = new Context('home');
        $context->updateState(Controller::STATE_READ_INPUT);
        $context->updateParameters(['page' => 2]);
        $context->resetState();
        $context->resetParameters();

        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame([], $context->parameters());
    }

    public function testSurvivesSerialization(): void
    {
        $context = new Context('home');
        $context->updateState(Controller::STATE_READ_INPUT);
        $context->updateParameters(['page' => 2, 'query' => 'abc']);
        $context->changeRoute('directory');
        $context->updateState(Controller::STATE_DISPLAY);

        $restored = Context::unserialize($context->serialize(), 'home');

        self::assertSame('directory', $restored->route());
        self::assertSame(Controller::STATE_DISPLAY, $restored->state());
        $restored->changeRoute('home');
        self::assertSame(Controller::STATE_READ_INPUT, $restored->state());
        self::assertSame(['page' => 2, 'query' => 'abc'], $restored->parameters());
    }

    public function testSerializesAsJson(): void
    {
        $context = new Context('home');
        $context->updateState(Controller::STATE_READ_INPUT);
        $context->updateParameters(['query' => 'café/thé', 'page' => 2]);

        self::assertSame(
            '{"route":"home","states":{"home":"read-input"},"parameters":{"home":{"query":"café/thé","page":2}}}',
            $context->serialize(),
        );
    }

    public function testParametersThatAreNotJsonCannotBeSerialized(): void
    {
        $context = new Context('home');
        $context->updateParameters(['query' => "\xE9t\xE9"]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize context');

        $context->serialize();
    }

    public function testUnserializeWithoutRouteStartsAtTheDefaultRoute(): void
    {
        $context = Context::unserialize('{"states":{"menu":"read-input"}}', 'home');

        self::assertSame('home', $context->route());
        self::assertSame(Controller::STATE_INIT, $context->state());
    }

    public function testUnserializeDropsMalformedEntries(): void
    {
        $context = Context::unserialize((string) json_encode([
            'route' => 'home',
            'states' => ['home' => Controller::STATE_READ_INPUT, 3 => 'display', 'menu' => ['not a state']],
            'parameters' => ['home' => ['page' => 2, 0 => 'no name'], 'menu' => 'not parameters'],
        ]), 'home');

        self::assertSame(Controller::STATE_READ_INPUT, $context->state());
        self::assertSame(['page' => 2], $context->parameters());
        $context->changeRoute('menu');
        self::assertSame(Controller::STATE_INIT, $context->state());
        self::assertSame([], $context->parameters());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notContexts(): iterable
    {
        yield 'text' => ['not a context'];
        yield 'JSON scalar' => ['123'];
        yield 'truncated JSON' => ['{"route":"ho'];
        // PHP's serialize(), the format used before JSON
        yield 'PHP serialized context' => [serialize(['route' => 'home', 'states' => [], 'parameters' => []])];
    }

    #[DataProvider('notContexts')]
    public function testUnserializeRejectsWhatIsNotAContext(string $serialized): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unserialize context');

        Context::unserialize($serialized, 'home');
    }
}
