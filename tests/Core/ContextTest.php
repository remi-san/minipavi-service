<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\Terminal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    public function testNewRouteStartsInInitWithoutParameters(): void
    {
        $context = new Context('home');

        self::assertSame(ControllerState::Init->value, $context->state());
        self::assertSame([], $context->parameters());
        self::assertEquals(new Controller\Request(ControllerState::Init->value, []), $context->getRequest());
    }

    public function testRequestCarriesTheInputOfTheGateway(): void
    {
        $context = new Context('home');
        $context->updateState(ControllerState::ReadInput);
        $terminal = new Terminal('192.0.2.1', 'websocket', 'Cv5');

        self::assertEquals(
            new Controller\Request(ControllerState::ReadInput->value, [], 'ENVOI', ['abc'], $terminal),
            $context->getRequest('ENVOI', ['abc'], $terminal),
        );
    }

    public function testKeepsStateAndParametersPerRoute(): void
    {
        $context = new Context('home');
        $context->updateState(ControllerState::ReadInput);
        $context->updateParameters(['page' => 2]);
        $context->changeRoute('directory');

        self::assertSame(ControllerState::Init->value, $context->state());

        $context->changeRoute('home');
        self::assertSame(ControllerState::ReadInput->value, $context->state());
        self::assertSame(['page' => 2], $context->parameters());
        self::assertSame(2, $context->getRequest()->int('page'));
    }

    public function testResetsTheCurrentRoute(): void
    {
        $context = new Context('home');
        $context->updateState(ControllerState::ReadInput);
        $context->updateParameters(['page' => 2]);
        $context->resetState();
        $context->resetParameters();

        self::assertSame(ControllerState::Init->value, $context->state());
        self::assertSame([], $context->parameters());
    }

    public function testSurvivesSerialization(): void
    {
        $context = new Context('home');
        $context->updateState(ControllerState::ReadInput);
        $context->updateParameters(['page' => 2, 'query' => 'abc']);
        $context->changeRoute('directory');
        $context->updateState(ControllerState::Display);

        $restored = Context::unserialize($context->serialize(), 'home');

        self::assertSame('directory', $restored->route());
        self::assertSame(ControllerState::Display->value, $restored->state());
        $restored->changeRoute('home');
        self::assertSame(ControllerState::ReadInput->value, $restored->state());
        self::assertSame(['page' => 2, 'query' => 'abc'], $restored->parameters());
    }

    public function testSerializesAsJson(): void
    {
        $context = new Context('home');
        $context->updateState(ControllerState::ReadInput);
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
        self::assertSame(ControllerState::Init->value, $context->state());
    }

    public function testUnserializeDropsMalformedEntries(): void
    {
        $context = Context::unserialize((string) json_encode([
            'route' => 'home',
            'states' => ['home' => ControllerState::ReadInput->value, 3 => 'display', 'menu' => ['not a state']],
            'parameters' => ['home' => ['page' => 2, 0 => 'no name'], 'menu' => 'not parameters'],
        ]), 'home');

        self::assertSame(ControllerState::ReadInput->value, $context->state());
        self::assertSame(['page' => 2], $context->parameters());
        $context->changeRoute('menu');
        self::assertSame(ControllerState::Init->value, $context->state());
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
