<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\AbstractController;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Tests\Support\PageParameters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbstractControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{ControllerState, string}>
     */
    public static function states(): iterable
    {
        yield 'init shows the screen' => [ControllerState::Init, 'display'];
        yield 'display' => [ControllerState::Display, 'display'];
        yield 'init-input shows the screen again' => [ControllerState::InitInput, 'display'];
        yield 'read-input' => [ControllerState::ReadInput, 'read'];
    }

    #[DataProvider('states')]
    public function testRunsTheMethodOfTheState(ControllerState $state, string $content): void
    {
        $response = self::controller()->run(new Request($state, new NoParameters()));

        self::assertInstanceOf(Response\DisplayPage::class, $response);
        self::assertSame($content, $response->vdt);
    }

    public function testStartsAtInit(): void
    {
        self::assertSame(ControllerState::Init, self::controller()->initialState());
    }

    public function testInitAndInitInputCanBeOverridden(): void
    {
        $controller = new /** @extends AbstractController<NoParameters> */ class extends AbstractController {
            public function parametersType(): string
            {
                return NoParameters::class;
            }

            protected function onInit(Request $request): Response
            {
                return Response::display('first');
            }

            protected function onDisplay(Request $request): Response
            {
                return Response::display('display');
            }

            protected function onInitInput(Request $request): Response
            {
                return Response::display('input');
            }

            protected function onReadInput(Request $request): Response
            {
                return Response::display('read');
            }
        };

        self::assertSame('first', self::vdt($controller->run(new Request(ControllerState::Init, new NoParameters()))));
        self::assertSame('input', self::vdt($controller->run(new Request(ControllerState::InitInput, new NoParameters()))));
    }

    public function testGetsItsParametersTyped(): void
    {
        $controller = new /** @extends AbstractController<PageParameters> */ class extends AbstractController {
            public function parametersType(): string
            {
                return PageParameters::class;
            }

            protected function onDisplay(Request $request): Response
            {
                return Response::display('page ' . $request->parameters()->page);
            }

            protected function onReadInput(Request $request): Response
            {
                return Response::display('read');
            }
        };

        self::assertSame(PageParameters::class, $controller->parametersType());
        self::assertSame('page 3', self::vdt($controller->run(new Request(ControllerState::Display, new PageParameters(3)))));
    }

    /**
     * @return AbstractController<NoParameters>
     */
    private static function controller(): AbstractController
    {
        return new /** @extends AbstractController<NoParameters> */ class extends AbstractController {
            public function parametersType(): string
            {
                return NoParameters::class;
            }

            protected function onDisplay(Request $request): Response
            {
                return Response::display('display');
            }

            protected function onReadInput(Request $request): Response
            {
                return Response::display('read');
            }
        };
    }

    private static function vdt(Response $response): string
    {
        if (!$response instanceof Response\DisplayPage) {
            self::fail('Not a DisplayPage: ' . $response::class);
        }

        return $response->vdt;
    }
}
