<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Core;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Values;
use MiniPaviService\Core\Route;
use MiniPaviService\Core\Terminal;
use MiniPaviService\Tests\Support\FromParameters;
use MiniPaviService\Tests\Support\PageParameters;
use MiniPaviService\Tests\Support\SearchController;
use MiniPaviService\Tests\Support\SearchState;
use PHPUnit\Framework\TestCase;

/**
 * What a route keeps, typed as its controller declares it (Route, RouteContext)
 */
final class RouteTest extends TestCase
{
    public function testStartsAtTheInitialStateWithTheDefaultParameters(): void
    {
        $route = (new Route(new SearchController()))->start();

        self::assertSame(SearchState::Init, $route->state());
        self::assertEquals(new PageParameters(), $route->parameters());
    }

    public function testRestoresWhatWasKeptTyped(): void
    {
        $route = (new Route(new SearchController()))->restore('read-query', new Values(['page' => '2']));

        self::assertSame(SearchState::ReadQuery, $route->state());
        self::assertEquals(new PageParameters(2), $route->parameters());
    }

    public function testUnknownStateIsTheInitialOne(): void
    {
        // E.g. kept by an older version of the service
        self::assertSame(SearchState::Init, (new Route(new SearchController()))->restore('removed', new Values())->state());
    }

    public function testInvalidParametersGetTheirDefaults(): void
    {
        self::assertEquals(new PageParameters(), (new Route(new SearchController()))->restore(null, new Values(['page' => -5]))->parameters());
        self::assertEquals(new PageParameters(), (new Route(new SearchController()))->restore(null, new Values(['page' => 'abc']))->parameters());
    }

    public function testRunsTheControllerOnWhatItKeeps(): void
    {
        $route = (new Route(new SearchController()))->restore('read-query', new Values(['page' => 3]));

        self::assertEquals(Response::display('read-query 3', SearchState::ReadQuery), $route->run('ENVOI', [], new Terminal()));
    }

    public function testKeepsAStateAndParameters(): void
    {
        $route = (new Route(new SearchController()))->start();

        $route->keep(SearchState::ReadQuery, new PageParameters(4));

        self::assertSame(SearchState::ReadQuery, $route->state());
        self::assertEquals(new PageParameters(4), $route->parameters());
    }

    public function testKeepingNothingResets(): void
    {
        $route = (new Route(new SearchController()))->restore('read-query', new Values(['page' => 3]));

        $route->keep(null, null);

        self::assertSame(SearchState::Init, $route->state());
        self::assertEquals(new PageParameters(), $route->parameters());
    }

    public function testStateOfAnotherEnumIsRejected(): void
    {
        $route = (new Route(new SearchController()))->start();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(SearchController::class . ' keeps states of ' . SearchState::class . ', not ' . ControllerState::class);

        $route->keep(ControllerState::ReadInput, null);
    }

    public function testParametersOfAnotherClassAreRejected(): void
    {
        $route = (new Route(new SearchController()))->start();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(SearchController::class . ' keeps parameters of ' . PageParameters::class . ', not ' . FromParameters::class);

        $route->keep(null, new FromParameters('home'));
    }
}
