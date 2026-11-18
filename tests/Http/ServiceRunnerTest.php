<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Signing\ContextSigner;
use MiniPaviService\Core\Signing\ForgedContextException;
use MiniPaviService\Core\Signing\HmacContextSigner;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\MessagesXmlParser;
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
        $routes->addRoute('home', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => Response::display(new Vdt('home'))));
        $router = $routes->build();
        $this->logger = new MemoryLogger();
        $this->service = new Service(
            $router,
            (new MessagesXmlParser())->defaults(),
            new ContextSerializer($router),
            logger: $this->logger,
        );
    }

    public function testGivesTheResponseOfTheClientToTheAnswer(): void
    {
        $client = new MemoryClient(new GatewayRequest(ConnectionCode::Connect));

        $response = (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame('home', (string) $response->getBody());
        [[$request, $answer]] = $client->responded;
        self::assertEquals(new Vdt('home'), $answer->vdt);
        // The URL of the service, computed by WithUrls, is in the request
        self::assertSame('http://services/Svc/index.php', $request->serviceUrl);
    }

    public function testAnswersARequestThatCantBeReadAsAnError(): void
    {
        $client = new MemoryClient(new \RuntimeException('Not JSON'));

        (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        [[$request, $answer]] = $client->responded;
        self::assertEquals(
            Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error)),
            $answer->vdt,
        );
        self::assertSame('http://services/Svc/index.php', $request->serviceUrl);
        self::assertSame([LogLevel::ERROR, 'Error serving the request: Not JSON'], [
            $this->logger->records[0][0],
            $this->logger->records[0][1],
        ]);
    }

    public function testTheServiceChecksTheContextOfTheRequestAndSignsTheOneOfTheAnswer(): void
    {
        $signer = new HmacContextSigner('secret');
        $client = new MemoryClient(
            new GatewayRequest(FunctionKey::Send, [], $signer->sign('{"route":"home","states":{},"parameters":{}}')),
        );

        (new ServiceRunner($this->signingService($signer), $client))->handle(HttpRequests::to('MiniPAVI'));

        [[$request, $answer]] = $client->responded;
        self::assertEquals(new Vdt('home'), $answer->vdt);
        self::assertSame(
            '{"route":"home","states":{"home":"read-input"},"parameters":{"home":{}}}',
            $signer->verify($answer->context),
        );
        // The client gets the request it read, its context still signed
        self::assertSame($signer->sign('{"route":"home","states":{},"parameters":{}}'), $request->context);
    }

    public function testTheServiceAnswersAForgedContextAsAnErrorWithASignedContext(): void
    {
        $signer = new HmacContextSigner('secret');
        $client = new MemoryClient(
            new GatewayRequest(FunctionKey::Send, [], '{"route":"home","states":{},"parameters":{}}'),
        );

        (new ServiceRunner($this->signingService($signer), $client))->handle(HttpRequests::to('MiniPAVI'));

        [[, $answer]] = $client->responded;
        self::assertEquals(
            Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error)),
            $answer->vdt,
        );
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $signer->verify($answer->context));
        self::assertInstanceOf(ForgedContextException::class, $this->logger->records[0][2]['exception'] ?? null);
    }

    public function testNothingToAnswerToADisconnection(): void
    {
        $client = new MemoryClient(new GatewayRequest(ConnectionCode::Disconnect));

        $response = (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame([], $client->responded);
    }

    public function testLogsAnAnswerThatCantBeGiven(): void
    {
        $logger = new MemoryLogger();
        $client = new MemoryClient(
            new GatewayRequest(ConnectionCode::Connect),
            new \RuntimeException('Connection reset'),
        );

        $response = (new ServiceRunner($this->service, $client, $logger))->handle(HttpRequests::to('MiniPAVI'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([LogLevel::CRITICAL, 'Cannot send the answer: Connection reset'], [
            $logger->records[0][0],
            $logger->records[0][1],
        ]);
    }

    /**
     * With the gateway's client. In a separate process: MiniPaviCli::start() reads the body of
     * the request (empty here, so not JSON), and MiniPaviCli::send() writes the answer, captured
     * as the body of the response
     */
    #[RunInSeparateProcess]
    public function testAnswersAnUnreadableRequestOfTheGatewayAsAnError(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'MiniPAVI';

        ob_start();
        try {
            $response = (new ServiceRunner($this->service, new MiniPaviClient()))->handle(HttpRequests::to('MiniPAVI'));
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame('', $output);
        $answer = json_decode((string) $response->getBody(), associative: true);
        self::assertIsArray($answer);
        self::assertSame(
            base64_encode(Vdt::line0((new MessagesXmlParser())->defaults()->get(FrameworkMessage::Error))->toString()),
            $answer['content'] ?? null,
        );
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $answer['context'] ?? null);
        self::assertSame('http://services/Svc/index.php', $answer['next'] ?? null);
        self::assertSame(LogLevel::ERROR, $this->logger->records[0][0] ?? null);
    }

    /**
     * The service of the tests, signing its contexts with signer
     */
    private function signingService(ContextSigner $signer): Service
    {
        $router = (new RouterBuilder('home'))
            ->addRoute('home', new CallbackController(NoParameters::class, static fn(
                Request $request,
            ) => Response::display(new Vdt('home'))))
            ->build();

        return new Service(
            $router,
            (new MessagesXmlParser())->defaults(),
            new ContextSerializer($router, $signer),
            logger: $this->logger,
        );
    }
}
