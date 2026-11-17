<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Tests\Support\PageParameters;
use MiniPaviService\Tests\Support\SearchState;
use PHPUnit\Framework\TestCase;

/**
 * A controller's state as a State enum, and its parameters as a Parameters object
 */
final class TypedStateTest extends TestCase
{
    public function testParametersAreReadAsAnObject(): void
    {
        $request = new Request(parameters: ['page' => '2']);

        self::assertEquals(new PageParameters(2), $request->parametersAs(PageParameters::class));
    }

    public function testInvalidOrMissingParametersGetTheDefaultsOfTheObject(): void
    {
        self::assertEquals(new PageParameters(), (new Request(parameters: ['page' => -5]))->parametersAs(PageParameters::class));
        self::assertEquals(new PageParameters(), (new Request(parameters: ['page' => 'abc']))->parametersAs(PageParameters::class));
        self::assertEquals(new PageParameters(), (new Request())->parametersAs(PageParameters::class));
    }

    public function testResponsesKeepTheirParametersObject(): void
    {
        $parameters = new PageParameters(3);

        self::assertSame($parameters, Response::display('', parameters: $parameters)->parameters());
        self::assertSame($parameters, Response::readInput('', null, parameters: $parameters)->parameters());
        self::assertSame($parameters, Response::connectTo('https://example.org/', parameters: $parameters)->parameters());
        self::assertSame($parameters, Response::goTo('search', $parameters)->targetParameters);
    }

    public function testResponsesKeepNoParametersByDefault(): void
    {
        self::assertNull(Response::display('')->parameters());
        self::assertNull(Response::goTo('search')->targetParameters);
    }

    public function testStateIsReadAsACaseOfItsEnum(): void
    {
        self::assertSame(SearchState::ReadQuery, (new Request('read-query'))->stateAs(SearchState::class, SearchState::Init));
        // The value of a built-in state, kept by AbstractController
        self::assertSame(SearchState::ReadInput, (new Request(ControllerState::ReadInput->value))->stateAs(SearchState::class, SearchState::Init));
        self::assertSame(ControllerState::ReadInput, (new Request('read-input'))->stateAs(ControllerState::class, ControllerState::Init));
    }

    public function testUnknownStateGivesTheDefault(): void
    {
        // E.g. kept by an older version of the service, or another enum's
        self::assertSame(SearchState::Init, (new Request('removed'))->stateAs(SearchState::class, SearchState::Init));
        self::assertSame(ControllerState::Init, (new Request('read-query'))->stateAs(ControllerState::class, ControllerState::Init));
    }

    public function testResponsesKeepTheirState(): void
    {
        self::assertSame(SearchState::ReadQuery, Response::display('', SearchState::ReadQuery)->state());
        self::assertSame(SearchState::ReadQuery, Response::readInput('', null, SearchState::ReadQuery)->state());
        self::assertSame(SearchState::ReadQuery, Response::connectTo('https://example.org/', state: SearchState::ReadQuery)->state());
        self::assertSame(ControllerState::ReadInput, Response::display('')->state());
        self::assertSame(ControllerState::Init, Response::goTo('search')->state());
    }
}
