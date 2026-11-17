<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Http\Dispatch\ConditionalRequestHandler;
use MiniPaviService\Http\Dispatch\QueryParameterEquals;
use MiniPaviService\Http\Emulator\EmulatorConfig;
use MiniPaviService\Http\KernelBuilder;
use MiniPaviService\Http\Url\RequestUrl;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Tests\Support\RecordingHandler;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class KernelBuilderTest extends TestCase
{
    private KernelBuilder $builder;

    protected function setUp(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, fn(Request $request) => Response::display(VdtHelper::setPos(1, 1)->append(Vdt::text('HOME')))));
        $router->setDefaultRoute('home');
        $this->builder = KernelBuilder::for($router, Messages::defaults());
    }

    public function testBuildsAKernel(): void
    {
        $this->expectNotToPerformAssertions();

        $this->builder->withContextKey('secret')->build();
    }

    public function testBrowsersAskingWebGetTheServiceWithASignedContext(): void
    {
        $html = (string) $this->builder->withContextKey('secret')->handler()->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody();

        preg_match('#name="context" value="([^"]*)"#', $html, $context);
        self::assertSame('{"route":"home","states":{"home":"read-input"},"parameters":{"home":{}}}', (new HmacContextSigner('secret'))->verify(html_entity_decode($context[1] ?? '')));
    }

    public function testOtherBrowsersAreSentToTheEmulator(): void
    {
        $default = $this->builder->handler()->handle(HttpRequests::to('Mozilla/5.0'));
        $local = $this->builder->withEmulator(new EmulatorConfig('http://localhost:8081/', 'ws://localhost:8182/', 'services'))
            ->handler()->handle(HttpRequests::to('Mozilla/5.0'));

        self::assertSame(302, $default->getStatusCode());
        self::assertStringStartsWith(EmulatorConfig::PUBLIC_EMULATOR_URL, $default->getHeaderLine('Location'));
        self::assertStringStartsWith('http://localhost:8081/', $local->getHeaderLine('Location'));
    }

    public function testHandlersOfTheServiceAreTriedFirst(): void
    {
        $api = new RecordingHandler('api');
        $handler = $this->builder->withHandler(new ConditionalRequestHandler('api', new QueryParameterEquals('web', 'true'), $api))->handler();

        self::assertSame('api', (string) $handler->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody());
        self::assertCount(1, $api->requests);
    }

    public function testComputesTheUrlsWithTheRequestUrl(): void
    {
        $html = (string) $this->builder->withRequestUrl(new RequestUrl('https://minitel.example.org'))
            ->handler()->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']))->getBody();

        // The page's title is the service's URL
        self::assertStringContainsString('<title>https://minitel.example.org', $html);
    }

    public function testLogsAndWarnsThatTheContextIsNotSignedWithoutKey(): void
    {
        $logger = new MemoryLogger();

        $this->builder->withLogger($logger)->handler()->handle(HttpRequests::to('Mozilla/5.0', ['web' => 'true']));

        self::assertSame([LogLevel::WARNING, 'No key signing the context: it is not signed, and can be forged'], [$logger->records[0][0], $logger->records[0][1]]);
        // The service logs to it too
        self::assertContains([LogLevel::INFO, 'Connection', ['fctn' => 'CNX']], $logger->records);
    }

    public function testNoWarningWithAKey(): void
    {
        $logger = new MemoryLogger();

        $this->builder->withLogger($logger)->withContextKey('secret')->handler();

        self::assertSame([], $logger->records);
    }
}
