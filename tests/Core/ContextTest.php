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
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\RecordingFlow;
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
        $routes = new RouterBuilder('home');
        $routes->addRoute(
            'home',
            new CallbackController(
                PageParameters::class,
                /** @param Request<ControllerState, PageParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text(
                    'home '
                        . $request->state()->value
                        . ' '
                        . $request->parameters()->page
                        . ' '
                        . $request->key()
                        . ' '
                        . $request->contentAsString()
                        . ' '
                        . $request->terminal()->remoteAddr(),
                )),
            ),
        );
        $routes->addRoute('search', new SearchController());
        $routes->addRoute('menu', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(new Vdt('menu'))));
        $routes->addRoute(
            'origin',
            new CallbackController(
                FromParameters::class,
                /** @param Request<ControllerState, FromParameters> $request */
                static fn(Request $request) => Response::display(Vdt::text('from ' . $request->parameters()->from)),
            ),
        );
        $this->router = $routes->build();
    }

    public function testNewRouteHasTheInitialStateAndDefaultParametersOfItsController(): void
    {
        $context = new Context($this->router, 'search');

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
    }

    public function testRunsTheControllerOnWhatItKeptAndTheInput(): void
    {
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));

        self::assertSame(
            'home read-input 2 ENVOI abc 192.0.2.1',
            $context
                ->run('ENVOI', ['abc'], new Terminal('192.0.2.1'))
                ->continueWith(new RecordingFlow())
                ->vdt->toString(),
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
        $context->keep(null, parameters: null);

        self::assertSame(SearchState::Init, $context->state());
        self::assertEquals(new PageParameters(), $context->parameters());
    }

    public function testKeepingAnotherTypeThanTheControllersIsAnError(): void
    {
        $context = new Context($this->router, 'search');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('keeps states of ' . SearchState::class . ', not ' . ControllerState::class);

        $context->keep(ControllerState::ReadInput, parameters: null);
    }

    public function testKeepingParametersOfAnotherClassThanTheControllersIsAnError(): void
    {
        $context = new Context($this->router, 'search');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            SearchController::class
            . ' keeps parameters of '
            . PageParameters::class
            . ', not '
            . FromParameters::class,
        );

        $context->keep(null, new FromParameters('home'));
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

    public function testParametersSurviveSerializationAsTheyAre(): void
    {
        $context = new Context($this->router, 'origin');
        $context->keep(ControllerState::ReadInput, new FromParameters('café/thé "1"'));

        self::assertEquals(
            new FromParameters('café/thé "1"'),
            Context::unserialize($context->serialize(), $this->router)->parameters(),
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
                    'origin' => ['from' => 12],
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
        // A value of another type, coerced when safe
        $context->changeRoute('origin');
        self::assertEquals(new FromParameters('12'), $context->parameters());
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
