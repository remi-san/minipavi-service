<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Parameters\Values;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\Context;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\KeptRoute;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Core\UnknownRouteException;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\SearchController;
use MiniPaviService\Tests\Support\SearchState;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextSerializerTest extends TestCase
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
                static fn(Request $request) => new DisplayPage(Vdt::text(
                    'home '
                        . $request->state()->value
                        . ' '
                        . $request->parameters()->page
                        . ' '
                        . (string) $request->key()?->value
                        . ' '
                        . $request->contentAsString()
                        . ' '
                        . $request->terminal()->remoteAddr(),
                )),
            ),
        );
        $routes->addRoute('search', new SearchController());
        $routes->addRoute(
            'menu',
            new CallbackController(
                NoParameters::class,
                static fn(Request $request) => new DisplayPage(new Vdt('menu')),
            ),
        );
        $routes->addRoute(
            'origin',
            new CallbackController(
                FromParameters::class,
                /** @param Request<ControllerState, FromParameters> $request */
                static fn(Request $request) => new DisplayPage(Vdt::text('from ' . $request->parameters()->from)),
            ),
        );
        $this->router = $routes->build();
    }

    public function testSurvivesSerializationTyped(): void
    {
        $context = new Context($this->router, 'search', [
            'home' => $this->router->route('home')->restore('read-input', new Values(['page' => 2])),
        ]);
        $context->keep(SearchState::ReadQuery, new PageParameters(5));

        $restored = $this->serializer()->unserialize($this->serializer()->serialize($context));

        self::assertSame('search', $restored->route());
        self::assertSame(SearchState::ReadQuery, $restored->state());
        self::assertEquals(new PageParameters(5), $restored->parameters());
        // The other route's too, typed by its controller
        self::assertEquals(new KeptRoute(ControllerState::ReadInput, new PageParameters(2)), $restored->kept()['home']);
    }

    public function testParametersSurviveSerializationAsTheyAre(): void
    {
        $context = new Context($this->router, 'origin');
        $context->keep(ControllerState::ReadInput, new FromParameters('café/thé "1"'));

        self::assertEquals(
            new FromParameters('café/thé "1"'),
            $this->serializer()->unserialize($this->serializer()->serialize($context))->parameters(),
        );
    }

    public function testParametersThatAreNotJsonCannotBeSerialized(): void
    {
        $context = new Context($this->router, 'origin');
        $context->keep(null, new FromParameters("\xE9t\xE9"));

        try {
            $this->serializer()->serialize($context);
        } catch (\RuntimeException $e) {
            self::assertSame(
                'Cannot serialize context: Malformed UTF-8 characters, possibly incorrectly encoded',
                $e->getMessage(),
            );
            // For the log: what JSON failed on
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());

            return;
        }
        self::fail('Parameters that are not JSON were serialized');
    }

    public function testUnserializeWithoutRouteStartsAtTheDefaultRoute(): void
    {
        $context = $this->serializer()->unserialize('{"states":{"menu":"read-input"}}');

        self::assertSame('home', $context->route());
        self::assertSame(ControllerState::Init, $context->state());
    }

    public function testUnserializeRefusesARouteTheRouterDoesntHave(): void
    {
        // E.g. removed in a new version of the service
        try {
            $this->serializer()->unserialize('{"route":"removed","states":{"home":"read-input"}}');
            self::fail('A context at an unknown route was read');
        } catch (UnknownRouteException $e) {
            self::assertSame('removed', $e->route);
            // Not taken for a context that can't be read (\RuntimeException)
            self::assertNotInstanceOf(\RuntimeException::class, $e);
        }
    }

    public function testUnserializeTypesWhatWasKeptOrFallsBack(): void
    {
        $context = $this->serializer()->unserialize((string) json_encode([
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
        ]));

        self::assertSame(ControllerState::ReadInput, $context->state());
        self::assertEquals(new PageParameters(2), $context->parameters());
        $kept = $context->kept();
        // A state its enum doesn't have, invalid parameters: its controller's initial state
        // (of its enum), and its parameters' defaults (of its class)
        self::assertSame(SearchState::Init, $kept['search']->state);
        self::assertEquals(new PageParameters(), $kept['search']->parameters);
        self::assertSame(ControllerState::Init, $kept['menu']->state);
        // A value of another type, coerced when safe
        self::assertEquals(new FromParameters('12'), $kept['origin']->parameters);
        // Routes the router doesn't have are dropped
        self::assertStringNotContainsString('removed', $this->serializer()->serialize($context));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notContexts(): iterable
    {
        yield 'text' => ['not a context', 'Cannot unserialize context: Syntax error'];
        yield 'JSON scalar' => ['123', 'Cannot unserialize context: not a JSON object'];
        yield 'truncated JSON' => ['{"route":', 'Cannot unserialize context: Syntax error'];
        // PHP's serialize(), the format used before JSON
        yield 'PHP serialized context' => [
            serialize(['route' => 'home', 'states' => [], 'parameters' => []]),
            'Cannot unserialize context: Syntax error',
        ];
    }

    #[DataProvider('notContexts')]
    public function testUnserializeRejectsWhatIsNotAContext(string $serialized, string $error): void
    {
        self::assertSame($error, $this->unserializeError($serialized)->getMessage());
    }

    public function testUnserializeKeepsTheJsonError(): void
    {
        // For the log: what JSON failed on
        self::assertInstanceOf(\JsonException::class, $this->unserializeError('{"route":')->getPrevious());
    }

    public function testSignsTheContextItSendsAndChecksTheOneGivenBack(): void
    {
        $signing = new ContextSerializer($this->router, new HmacContextSigner('secret'));
        $context = new Context($this->router, 'home');
        $context->keep(ControllerState::ReadInput, new PageParameters(2));

        $restored = $signing->unserialize($signing->serialize($context));

        self::assertEquals(new PageParameters(2), $restored->parameters());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgedContexts(): iterable
    {
        $json = '{"route":"search","states":{},"parameters":{}}';

        yield 'unsigned' => [$json];
        yield 'signed with another key' => [(new HmacContextSigner('other'))->sign($json)];
    }

    #[DataProvider('forgedContexts')]
    public function testRefusesAContextItDidntSign(string $forged): void
    {
        $this->expectException(ForgedContextException::class);

        (new ContextSerializer($this->router, new HmacContextSigner('secret')))->unserialize($forged);
    }

    private function serializer(): ContextSerializer
    {
        return new ContextSerializer($this->router);
    }

    /**
     * Error unserializing what isn't a context
     */
    private function unserializeError(string $serialized): \RuntimeException
    {
        try {
            $this->serializer()->unserialize($serialized);
        } catch (\RuntimeException $e) {
            return $e;
        }

        self::fail('Not a context, unserialized');
    }
}
