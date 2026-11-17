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
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
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

    public function testSendsTheAnswerToTheRequestOfTheClient(): void
    {
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_CONNECT));

        (new ServiceRunner($this->service, $client))->run();

        self::assertCount(1, $client->sent);
        self::assertEquals(new Vdt('home'), $client->sent[0]->vdt);
    }

    public function testAnswersARequestThatCantBeReadAsAnError(): void
    {
        $client = new MemoryClient(new \RuntimeException('Not JSON'));

        (new ServiceRunner($this->service, $client))->run();

        self::assertEquals(VdtHelper::writeLine0(Messages::defaults()->get(FrameworkMessage::Error)), $client->sent[0]->vdt);
        self::assertSame([LogLevel::ERROR, 'Error serving the request: Not JSON'], [$this->logger->records[0][0], $this->logger->records[0][1]]);
    }

    public function testSendsNothingForADisconnection(): void
    {
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_DISCONNECT));

        (new ServiceRunner($this->service, $client))->run();

        self::assertSame([], $client->sent);
    }

    public function testLogsAnAnswerThatCantBeSent(): void
    {
        $logger = new MemoryLogger();
        $client = new MemoryClient(new GatewayRequest(GatewayRequest::FCTN_CONNECT), new \RuntimeException('Connection reset'));

        (new ServiceRunner($this->service, $client, $logger))->run();

        self::assertSame([LogLevel::CRITICAL, 'Cannot send the answer: Connection reset'], [$logger->records[0][0], $logger->records[0][1]]);
    }

    /**
     * With the gateway's client. In a separate process: MiniPaviCli::start() reads the body of
     * the request (empty here, so not JSON), and the answer is written to the output
     */
    #[RunInSeparateProcess]
    public function testAnswersAnUnreadableRequestOfTheGatewayAsAnError(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'MiniPAVI';
        $_SERVER['HTTP_HOST'] = 'services';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';

        ob_start();
        try {
            (new ServiceRunner($this->service, new MiniPaviClient(new RequestUrl())))->run();
        } finally {
            $output = (string) ob_get_clean();
        }

        $answer = json_decode($output, true);
        self::assertIsArray($answer);
        self::assertSame(base64_encode(VdtHelper::writeLine0(Messages::defaults()->get(FrameworkMessage::Error))->toString()), $answer['content'] ?? null);
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $answer['context'] ?? null);
        self::assertSame('http://services/Svc/index.php', $answer['next'] ?? null);
        self::assertSame(LogLevel::ERROR, $this->logger->records[0][0] ?? null);
    }
}
