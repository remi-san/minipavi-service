<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Http;

use MiniPaviService\Controller\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\MiniPaviClient;
use MiniPaviService\Http\EmulatorConfig;
use MiniPaviService\Http\RequestUrl;
use MiniPaviService\Http\ServiceRunner;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\MemoryLogger;
use MiniPaviService\Vdt\VdtHelper;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class ServiceRunnerTest extends TestCase
{
    /**
     * In a separate process: if the service ran, MiniPaviCli::start() would exit
     */
    #[RunInSeparateProcess]
    public function testSendsBrowsersToTheEmulatorAndReturns(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/Svc/';
        $logger = new MemoryLogger();
        $runner = new ServiceRunner(
            new Service(new Router(), Messages::defaults()),
            new MiniPaviClient(new RequestUrl()),
            new EmulatorConfig('http://emulator', 'ws://gateway'),
            new RequestUrl(),
            $logger,
        );

        $runner->run();

        $url = 'http://emulator/index.php?gw=' . urlencode('ws://gateway/?url=' . urlencode('http://example.org/Svc/'));
        self::assertSame([[LogLevel::INFO, 'Browser sent to the emulator', ['url' => $url]]], $logger->records);
    }

    /**
     * In a separate process: MiniPaviCli::start() reads the body of the request (empty here,
     * so not JSON), and the answer is written to the output
     */
    #[RunInSeparateProcess]
    public function testAnswersAnUnreadableRequestOfTheGatewayAsAnError(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'MiniPAVI';
        $_SERVER['HTTP_HOST'] = 'services';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
        $router = new Router();
        $router->addRoute('home', new CallbackController(NoParameters::class, fn(Request $request) => Response::display('home')));
        $router->setDefaultRoute('home');
        $logger = new MemoryLogger();

        ob_start();
        try {
            (new ServiceRunner(new Service($router, Messages::defaults(), $logger), new MiniPaviClient(new RequestUrl()), new EmulatorConfig('http://emulator', 'ws://gateway'), new RequestUrl()))->run();
        } finally {
            $output = (string) ob_get_clean();
        }

        $answer = json_decode($output, true);
        self::assertIsArray($answer);
        self::assertSame(base64_encode(VdtHelper::writeLine0(Messages::defaults()->get(FrameworkMessage::Error))), $answer['content'] ?? null);
        self::assertSame('{"route":"home","states":{},"parameters":{}}', $answer['context'] ?? null);
        self::assertSame('http://services/Svc/index.php', $answer['next'] ?? null);
        self::assertSame(LogLevel::ERROR, $logger->records[0][0] ?? null);
    }
}
