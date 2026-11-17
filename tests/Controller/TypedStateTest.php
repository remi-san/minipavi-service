<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Tests\Support\PageParameters;
use MiniPaviService\Tests\Support\SearchState;
use PHPUnit\Framework\TestCase;

/**
 * A controller's state as an enum, and its parameters as an object (Parameters)
 */
final class TypedStateTest extends TestCase
{
    public function testParametersAreReadAsAnObject(): void
    {
        $request = new Request(parameters: ['page' => '2', 'query' => 'abc']);

        self::assertEquals(new PageParameters(2, 'abc'), $request->parametersAs(PageParameters::class));
    }

    public function testInvalidOrMissingParametersGetTheDefaultsOfTheObject(): void
    {
        $request = new Request(parameters: ['page' => -5, 'query' => ['abc']]);

        self::assertEquals(new PageParameters(), $request->parametersAs(PageParameters::class));
        self::assertEquals(new PageParameters(), (new Request())->parametersAs(PageParameters::class));
    }

    public function testResponsesKeepParametersGivenAsAnObject(): void
    {
        $parameters = new PageParameters(3, 'abc');

        self::assertSame(['page' => 3, 'query' => 'abc'], Response::display('', parameters: $parameters)->parameters());
        self::assertSame(['page' => 3, 'query' => 'abc'], Response::readInput('', null, parameters: $parameters)->parameters());
        self::assertSame(['page' => 3, 'query' => 'abc'], Response::connectTo('https://example.org/', parameters: $parameters)->parameters());
        self::assertSame(['page' => 3, 'query' => 'abc'], Response::goTo('search', $parameters)->targetParameters);
    }

    public function testStateIsReadAsAnEnum(): void
    {
        self::assertSame(SearchState::ReadQuery, (new Request('read-query'))->stateAs(SearchState::class, SearchState::Init));
        self::assertSame(SearchState::ReadInput, (new Request(Controller::STATE_READ_INPUT))->stateAs(SearchState::class, SearchState::Init));
    }

    public function testUnknownStateGivesTheDefault(): void
    {
        // E.g. kept by an older version of the service
        self::assertSame(SearchState::Init, (new Request('removed'))->stateAs(SearchState::class, SearchState::Init));
    }

    public function testResponsesKeepAStateGivenAsAnEnum(): void
    {
        self::assertSame('read-query', Response::display('', SearchState::ReadQuery)->state());
        self::assertSame('read-query', Response::readInput('', null, SearchState::ReadQuery)->state());
        self::assertSame('read-query', Response::connectTo('https://example.org/', state: SearchState::ReadQuery)->state());
    }
}
