<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\Router;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\SearchController;
use MiniPaviService\Tests\Support\SearchState;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        // home: built-in states, a page; search: states of its own, a page; menu: nothing;
        // origin: the route it was opened from
        $this->router = new Router();
        $this->router->addRoute(
            'home',
            new CallbackController(
                PageParameters::class,
                /** @param Request<ControllerState, PageParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text(
                    'home ' . $request->state()->value . ' ' . $request->parameters()->page,
                )),
            ),
        );
        $this->router->addRoute('search', new SearchController());
        $this->router->addRoute('menu', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(new Vdt('menu'))));
        $this->router->addRoute(
            'origin',
            new CallbackController(
                FromParameters::class,
                /** @param Request<ControllerState, FromParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text('from ' . $request->parameters()->from)),
            ),
        );
        $this->router->setDefaultRoute('home');
    }

    public function testNewRouteHasTheInitialStateAndDefaultParametersOfItsController(): void
    {
        $context = new Context($this->router, 'search');

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
        self::assertSame('{"route":"search","states":{},"parameters":{}}', $context->serialize());
    }

    public function testRunsTheControllerOnWhatItKeptAndTheInput(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));

        self::assertEquals(
            Response::display(new Vdt('home read-input 2')),
            $context->run('ENVOI', ['abc'], new Terminal('192.0.2.1')),
        );
    }

    public function testKeepsStateAndParametersPerRoute(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));
        $context->changeRoute('search');

        self::assertSame(SearchState::Init, $context->state());

        $context->changeRoute('home');
        self::assertSame(ControllerState::ReadInput, $context->state());
        self::assertEquals(new PageParameters(2), $context->parameters());
    }

    public function testKeepingNothingResetsTheCurrentRoute(): void
    {
        $context = new Context($this->router, 'search');
        $context->keep(SearchState::ReadQuery, new PageParameters(2));
        $context->keep(null, null);

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
    }

    public function testKeepingAnotherTypeThanTheControllersIsAnError(): void
    {
        $context = new Context($this->router, 'search');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('keeps states of ' . SearchState::class . ', not ' . ControllerState::class);

        $context->keep(ControllerState::ReadInput, null);
    }

    public function testSurvivesSerializationTyped(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));
        $context->changeRoute('search');
        $context->keep(SearchState::ReadQuery, new PageParameters(5));

        $restored = Context::unserialize($context->serialize(), $this->router);

        self::assertSame('search', $restored->route());
        self::assertSame(SearchState::ReadQuery, $restored->state());
        self::assertEquals(new PageParameters(5), $restored->parameters());
        $restored->changeRoute('home');
        self::assertSame(ControllerState::ReadInput, $restored->state());
        self::assertEquals(new PageParameters(2), $restored->parameters());
    }

    public function testSerializesAsJson(): void
    {
        $context = new Context($this->router, 'origin');
        $context->keep(ControllerState::ReadInput, new FromParameters('café/thé'));

        self::assertSame(
            '{"route":"origin","states":{"origin":"read-input"},"parameters":{"origin":{"from":"café/thé"}}}',
            $context->serialize(),
        );
    }

    public function testParametersThatAreNotJsonCannotBeSerialized(): void
    {
        $context = new Context($this->router, 'origin');
        $context->keep(null, new FromParameters("\xE9t\xE9"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize context');

        $context->serialize();
    }

    public function testUnserializeWithoutRouteStartsAtTheDefaultRoute(): void
    {
        $context = Context::unserialize('{"states":{"menu":"read-input"}}', $this->router);

        self::assertSame('home', $context->route());
        self::assertSame(ControllerState::Init, $context->state());
    }

    public function testUnserializeTypesWhatWasKeptOrFallsBack(): void
    {
        $context = Context::unserialize(
            (string) json_encode([
                'route' => 'home',
                'states' => [
                    'home' => 'read-input',
                    'search' => 'removed state',
                    3 => 'display',
                    'menu' => ['not a state'],
                    'removed' => 'display',
                ],
                'parameters' => [
                    'home' => ['page' => '2', 0 => 'no name'],
                    'search' => ['page' => -1],
                    'menu' => 'not parameters',
                ],
            ]),
            $this->router,
        );

        self::assertSame(ControllerState::ReadInput, $context->state());
        self::assertEquals(new PageParameters(2), $context->parameters());
        // A state its enum doesn't have, invalid parameters
        $context->changeRoute('search');
        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
        $context->changeRoute('menu');
        self::assertSame(ControllerState::Init, $context->state());
        // Routes the router doesn't have are dropped
        self::assertStringNotContainsString('removed', $context->serialize());
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

        Context::unserialize($serialized, $this->router);
    }
}
