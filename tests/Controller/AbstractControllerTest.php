<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\AbstractController;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbstractControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function states(): iterable
    {
        yield 'init shows the screen' => [ControllerState::Init->value, 'display'];
        yield 'display' => [ControllerState::Display->value, 'display'];
        yield 'init-input shows the screen again' => [ControllerState::InitInput->value, 'display'];
        yield 'read-input' => [ControllerState::ReadInput->value, 'read'];
        yield 'unknown state starts again' => ['state of an older version', 'display'];
    }

    #[DataProvider('states')]
    public function testRunsTheMethodOfTheState(string $state, string $content): void
    {
        $response = self::controller()->run(new Request($state));

        self::assertInstanceOf(Response\DisplayPage::class, $response);
        self::assertSame($content, $response->vdt);
    }

    public function testInitAndInitInputCanBeOverridden(): void
    {
        $controller = new class extends AbstractController {
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

        self::assertSame('first', self::vdt($controller->run(new Request(ControllerState::Init->value))));
        self::assertSame('input', self::vdt($controller->run(new Request(ControllerState::InitInput->value))));
    }

    private static function controller(): AbstractController
    {
        return new class extends AbstractController {
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
