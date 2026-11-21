<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Sessions;
use MiniPaviService\Core\Signing\ContextSigner;
use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryClient;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceRunnerTest extends TestCase
{
    private MemoryLogger $logger;

    private Service $service;

    protected function setUp(): void
    {
        $routes = new RouterBuilder('home');
        $routes->addRoute(
            'home',
            new CallbackController(
                NoParameters::class,
                static fn(Request $request) => new DisplayPage(new Vdt('home')),
            ),
        );
        $router = $routes->build();
        $this->logger = new MemoryLogger();
        $this->service = new Service(
            new Sessions(new ContextSerializer($router), $router),
            (new MessagesXmlParser())->defaults(),
            logger: $this->logger,
        );
    }

    public function testGivesTheHttpResponseOfTheClientForTheResponse(): void
    {
        $client = new MemoryClient(new GatewayRequest(ConnectionCode::Connect));

        $httpResponse = (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame('home', (string) $httpResponse->getBody());
        [[$request, $response]] = $client->responded;
        self::assertEquals(new Vdt('home'), $response->vdt);
        // The URL of the service, computed by WithUrls, goes to the client
        self::assertSame(['http://services/Svc/index.php'], $client->serviceUrls);
        self::assertEquals(new GatewayRequest(ConnectionCode::Connect), $request);
    }

    public function testRespondsToARequestThatCantBeReadWithAnError(): void
    {
        $client = new MemoryClient(new \RuntimeException('Not JSON'));

        (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        [[$request, $response]] = $client->responded;
        self::assertEquals(
            Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error)),
            $response->vdt,
        );
        // Responded to through the client, for a request it couldn't read: at the service's URL
        self::assertEquals(new GatewayRequest(null), $request);
        self::assertSame(['http://services/Svc/index.php', 'http://services/Svc/index.php'], $client->serviceUrls);
        self::assertSame([LogLevel::ERROR, 'Error serving the request'], [
            $this->logger->records[0][0],
            $this->logger->records[0][1],
        ]);
        $error = $this->logger->records[0][2]['exception'] ?? null;
        self::assertInstanceOf(\Throwable::class, $error);
        self::assertSame('Not JSON', $error->getMessage());
    }

    public function testAFailureAfterTheRequestWasReadIsRespondedToThroughTheExchangeRead(): void
    {
        $client = new MemoryClient(
            new GatewayRequest(FunctionKey::Send, [], 'context'),
            requestFailure: new \RuntimeException('Gone'),
        );

        (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        // Not an exchange of a request that couldn't be read: the one read, e.g. with its page
        self::assertSame(['http://services/Svc/index.php'], $client->serviceUrls);
        [[$request, $response]] = $client->responded;
        self::assertEquals(new GatewayRequest(FunctionKey::Send, [], 'context'), $request);
        self::assertEquals(
            Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error)),
            $response->vdt,
        );
    }

    public function testTheServiceChecksTheContextOfTheRequestAndSignsTheOneOfTheResponse(): void
    {
        $signer = new HmacContextSigner('secret');
        $client = new MemoryClient(
            new GatewayRequest(FunctionKey::Send, [], $signer->sign('{"route":"home","states":{},"parameters":{}}')),
        );

        (new ServiceRunner($this->signingService($signer), $client))->handle(HttpRequests::to('MiniPAVI'));

        [[$request, $response]] = $client->responded;
        self::assertEquals(new Vdt('home'), $response->vdt);
        self::assertSame(
            '{"route":"home","states":{"home":"read-input"},"parameters":{"home":{}}}',
            $signer->verify($response->context),
        );
        // The client gets the request it read, its context still signed
        self::assertSame($signer->sign('{"route":"home","states":{},"parameters":{}}'), $request->context);
    }

    public function testTheServiceRespondsToAForgedContextWithAnErrorWithASignedContext(): void
    {
        $signer = new HmacContextSigner('secret');
        $client = new MemoryClient(
            new GatewayRequest(FunctionKey::Send, [], '{"route":"home","states":{},"parameters":{}}'),
        );

        (new ServiceRunner($this->signingService($signer), $client))->handle(HttpRequests::to('MiniPAVI'));

        [[, $response]] = $client->responded;
        self::assertEquals(
            Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error)),
            $response->vdt,
        );
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $signer->verify($response->context));
        self::assertInstanceOf(ForgedContextException::class, $this->logger->records[0][2]['exception'] ?? null);
    }

    public function testNoResponseToADisconnection(): void
    {
        $client = new MemoryClient(new GatewayRequest(ConnectionCode::Disconnect));

        $httpResponse = (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame(204, $httpResponse->getStatusCode());
        self::assertSame('', (string) $httpResponse->getBody());
        self::assertSame([], $client->responded);
    }

    public function testLogsAResponseThatCantBeGiven(): void
    {
        $logger = new MemoryLogger();
        $client = new MemoryClient(
            new GatewayRequest(ConnectionCode::Connect),
            new \RuntimeException('Connection reset'),
        );

        $httpResponse = (new ServiceRunner($this->service, $client, $logger))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame(500, $httpResponse->getStatusCode());
        self::assertSame([LogLevel::CRITICAL, 'Cannot send the response'], [
            $logger->records[0][0],
            $logger->records[0][1],
        ]);
        $error = $logger->records[0][2]['exception'] ?? null;
        self::assertInstanceOf(\Throwable::class, $error);
        self::assertSame('Connection reset', $error->getMessage());
    }

    public function testLogsAnUnreadableRequestThatTheClientCantRespondTo(): void
    {
        $logger = new MemoryLogger();
        $client = new MemoryClient(
            new \RuntimeException('Not JSON'),
            unreadableFailure: new \RuntimeException('Connection reset'),
        );

        $httpResponse = (new ServiceRunner($this->service, $client, $logger))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame(500, $httpResponse->getStatusCode());
        self::assertSame([], $client->responded);
        // The request's error, logged by the service, then why it couldn't be responded to
        self::assertSame([LogLevel::ERROR, 'Error serving the request'], [
            $this->logger->records[0][0],
            $this->logger->records[0][1],
        ]);
        $error = $this->logger->records[0][2]['exception'] ?? null;
        self::assertInstanceOf(\Throwable::class, $error);
        self::assertSame('Not JSON', $error->getMessage());
        self::assertSame([LogLevel::CRITICAL, 'Cannot send the response'], [
            $logger->records[0][0],
            $logger->records[0][1],
        ]);
        $failure = $logger->records[0][2]['exception'] ?? null;
        self::assertInstanceOf(\Throwable::class, $failure);
        self::assertSame('Connection reset', $failure->getMessage());
    }

    /**
     * With the gateway's client. In a separate process: MiniPaviCli::start() reads the body of
     * the request (empty here, so not JSON), and MiniPaviCli::send() writes the response,
     * captured as the body of the HTTP response
     */
    #[RunInSeparateProcess]
    public function testRespondsToAnUnreadableRequestOfTheGatewayWithAnError(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'MiniPAVI';

        ob_start();
        try {
            $httpResponse = (new ServiceRunner($this->service, new MiniPaviClient()))->handle(HttpRequests::to(
                'MiniPAVI',
            ));
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame('', $output);
        $response = json_decode((string) $httpResponse->getBody(), associative: true);
        self::assertIsArray($response);
        self::assertSame(
            base64_encode(Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error))->toString()),
            $response['content'] ?? null,
        );
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $response['context'] ?? null);
        self::assertSame('http://services/Svc/index.php', $response['next'] ?? null);
        self::assertSame(LogLevel::ERROR, $this->logger->records[0][0] ?? null);
    }

    /**
     * The service of the tests, signing its contexts with signer
     */
    private function signingService(ContextSigner $signer): Service
    {
        $router = (new RouterBuilder('home'))
            ->addRoute(
                'home',
                new CallbackController(
                    NoParameters::class,
                    static fn(Request $request) => new DisplayPage(new Vdt('home')),
                ),
            )
            ->build();

        return new Service(
            new Sessions(new ContextSerializer($router, $signer), $router),
            (new MessagesXmlParser())->defaults(),
            logger: $this->logger,
        );
    }
}
