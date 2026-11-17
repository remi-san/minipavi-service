<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Client\MiniPaviClient;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Http\EmptyHttpResponse;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryClient;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceRunnerTest extends TestCase
{
    private MemoryLogger $logger;

    private Service $service;

    protected function setUp(): void
    {
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, fn(Request $request) => Response::display(new Vdt('home'))));
        $router->setDefaultRoute('home');
        $this->logger = new MemoryLogger();
        $this->service = new Service($router, Messages::defaults(), $this->logger);
    }

    public function testGivesTheResponseOfTheClientToTheAnswer(): void
    {
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_CONNECT));

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
        self::assertEquals(VdtHelper::writeLine0(Messages::defaults()->get(FrameworkMessage::Error)), $answer->vdt);
        self::assertSame('http://services/Svc/index.php', $request->serviceUrl);
        self::assertSame([LogLevel::ERROR, 'Error serving the request: Not JSON'], [$this->logger->records[0][0], $this->logger->records[0][1]]);
    }

    public function testNothingToAnswerToADisconnection(): void
    {
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_DISCONNECT));

        $response = (new ServiceRunner($this->service, $client))->handle(HttpRequests::to('MiniPAVI'));

        self::assertInstanceOf(EmptyHttpResponse::class, $response);
        self::assertSame([], $client->responded);
    }

    public function testLogsAnAnswerThatCantBeGiven(): void
    {
        $logger = new MemoryLogger();
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_CONNECT), new \RuntimeException('Connection reset'));

        $response = (new ServiceRunner($this->service, $client, $logger))->handle(HttpRequests::to('MiniPAVI'));

        self::assertInstanceOf(EmptyHttpResponse::class, $response);
        self::assertSame([LogLevel::CRITICAL, 'Cannot send the answer: Connection reset'], [$logger->records[0][0], $logger->records[0][1]]);
    }

    /**
     * With the gateway's client. In a separate process: MiniPaviCli::start() reads the body of
     * the request (empty here, so not JSON), and MiniPaviCli::send() writes the answer
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

        self::assertInstanceOf(EmptyHttpResponse::class, $response);
        $answer = json_decode($output, true);
        self::assertIsArray($answer);
        self::assertSame(base64_encode(VdtHelper::writeLine0(Messages::defaults()->get(FrameworkMessage::Error))->toString()), $answer['content'] ?? null);
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $answer['context'] ?? null);
        self::assertSame('http://services/Svc/index.php', $answer['next'] ?? null);
        self::assertSame(LogLevel::ERROR, $this->logger->records[0][0] ?? null);
    }
}
