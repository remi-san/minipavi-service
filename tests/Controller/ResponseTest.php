<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Context;
use MiniPaviService\Controller;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Controller\Response\RunExternalService;
use MiniPaviService\MiniPaviClient;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /** @var array<mixed> */
    private array $server;

    protected function setUp(): void
    {
        // The service's URL, sent to the gateway as the next one to call
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'services';
        $_SERVER['PHP_SELF'] = '/Svc/index.php';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testDisplaySendsContentAndReadsTheNextKey(): void
    {
        $response = Response::display('Hello');

        self::assertInstanceOf(DisplayPage::class, $response);
        self::assertSame(Controller::STATE_READ_INPUT, $response->state());
        self::assertSame([], $response->parameters());
        self::assertTrue($response->interrupts());
        $answer = $this->answer($response);
        self::assertSame('Hello', self::content($answer));
        self::assertSame('http://services/Svc/index.php', $answer['next']);
        self::assertSame('no', $answer['directcall']);
        self::assertArrayNotHasKey('COMMAND', $answer);
    }

    public function testReadInputSendsTheCommand(): void
    {
        $command = ['COMMAND' => ['name' => 'InputTxt', 'param' => ['x' => 1]]];
        $response = Response::readInput('Code ?', $command, parameters: ['page' => 2]);

        self::assertInstanceOf(RunCommand::class, $response);
        self::assertSame(Controller::STATE_READ_INPUT, $response->state());
        self::assertSame(['page' => 2], $response->parameters());
        $answer = $this->answer($response);
        self::assertSame('Code ?', self::content($answer));
        self::assertSame($command['COMMAND'], $answer['COMMAND']);
    }

    public function testReadInputWithoutCommandOnlySendsTheContent(): void
    {
        // Page::readInput() gives null for a page without <input>
        $answer = $this->answer(Response::readInput('Hello', null));

        self::assertSame('Hello', self::content($answer));
        self::assertArrayNotHasKey('COMMAND', $answer);
    }

    public function testGoToChangesTheRouteWithoutAnsweringTheGateway(): void
    {
        $response = Response::goTo('menu');
        $context = new Context('home');

        self::assertInstanceOf(ChangePage::class, $response);
        self::assertSame(Controller::STATE_INIT, $response->state());
        self::assertFalse($response->interrupts());
        self::assertSame('', $this->executedOutput($response, $context));
        self::assertSame('menu', $context->route());
    }

    public function testConnectToSendsTheUserToAnotherService(): void
    {
        $response = Response::connectTo('https://example.org/service', 'Cnx en cours');

        self::assertInstanceOf(RunExternalService::class, $response);
        self::assertSame(Controller::STATE_INIT, $response->state());
        $answer = $this->answer($response);
        self::assertSame('Cnx en cours', self::content($answer));
        self::assertSame('https://example.org/service', $answer['next']);
        self::assertSame(RunExternalService::CALL_MODE_CONNECT, $answer['directcall']);
    }

    public function testStateAndParametersCanBeGiven(): void
    {
        $response = Response::goTo('menu', Controller::STATE_DISPLAY, ['from' => 'home']);

        self::assertSame(Controller::STATE_DISPLAY, $response->state());
        self::assertSame(['from' => 'home'], $response->parameters());
    }

    /**
     * Answer sent to the gateway (JSON)
     *
     * @return array<mixed>
     */
    private function answer(Response $response): array
    {
        $answer = json_decode($this->executedOutput($response, new Context('home')), true);
        if (!\is_array($answer)) {
            self::fail('The answer is not a JSON object');
        }

        return $answer;
    }

    /**
     * Videotex content of an answer, sent in base64
     *
     * @param array<mixed> $answer
     */
    private static function content(array $answer): string
    {
        $content = $answer['content'] ?? null;
        if (!\is_string($content)) {
            self::fail('The answer has no content');
        }

        return base64_decode($content);
    }

    private function executedOutput(Response $response, Context $context): string
    {
        ob_start();
        try {
            $response->execute(new MiniPaviClient(), $context);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
