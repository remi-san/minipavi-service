<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\Context;
use MiniPaviService\Controller;
use MiniPaviService\Controller\Response\RunCommand;
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

    public function testKeepsStateAndParametersPerRoute(): void
    {
        $context = new Context('home');
        $context->update(new RunCommand(Controller::STATE_READ_INPUT, ['page' => 2], null, []));
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

        $restored = Context::unserialize($context->serialize());

        self::assertSame('directory', $restored->route());
        self::assertSame(Controller::STATE_DISPLAY, $restored->state());
        $restored->changeRoute('home');
        self::assertSame(Controller::STATE_READ_INPUT, $restored->state());
        self::assertSame(['page' => 2, 'query' => 'abc'], $restored->parameters());
    }

    public function testUnserializeRejectsWhatIsNotAContext(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unserialize context');

        Context::unserialize('not a context');
    }

    public function testUnserializeDoesNotCreateObjects(): void
    {
        // The context comes back from the gateway: no class may be instantiated from it
        $context = Context::unserialize(serialize(['route' => 'home', 'parameters' => ['home' => ['object' => new \ArrayObject()]]]));

        self::assertInstanceOf(\__PHP_Incomplete_Class::class, $context->parameters()['object']);
    }
}
