<?php

declare(strict_types=1);

namespace MiniPaviService\Testing;

use MiniPaviService\Core\Context\Context;
use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\Command\CallMode;
use MiniPaviService\Gateway\Command\ServiceCallCommand;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Minitel\ConnectionCode;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Assert;

/**
 * Scripts a session of a user against a service, without the gateway, for functional tests
 * (PHPUnit): it runs the service on requests built in memory, plays the responses on a
 * simulated Screen, and gives the context of each response back with the next request, as the
 * gateway does.
 *
 *     $tester = new ServiceTester($service, $serializer);
 *     $tester->connect()->assertScreenContains('BIENVENUE')
 *         ->type('2')->press(FunctionKey::Send)->assertRoute('games')
 *         ->press(FunctionKey::Next)->assertLine0('Dernière page');
 *
 * Assertions fail with the screen as text, to see what the user would.
 */
final class ServiceTester
{
    // PHPUnit's assertions take no named arguments: their messages are constants
    private const DISCONNECTION_RESPONDED = 'A disconnection got a response';

    // Videotex the session received, since the connection: the screen is all of it, played
    private Vdt $vdt;

    private ?GatewayResponse $response = null;

    /** @var list<string> text typed for the next key, one string per line */
    private array $typed = [];

    /**
     * @param ContextSerializer $serializer the service's, to read the context of its
     *                                      responses back (context())
     *
     * @throws \LogicException without PHPUnit, whose assertions it uses: the framework only
     *                         suggests it
     */
    public function __construct(
        private Service $service,
        private ContextSerializer $serializer,
        private Terminal $terminal = new Terminal(),
    ) {
        if (!class_exists(Assert::class)) {
            throw new \LogicException(
                'ServiceTester needs PHPUnit, for its assertions: composer require --dev phpunit/phpunit',
            );
        }
        $this->vdt = new Vdt();
    }

    /**
     * Connects to the service, as a new session: an empty screen, no context
     */
    public function connect(): self
    {
        $this->vdt = new Vdt();
        $this->response = null;

        return $this->send(new GatewayRequest(ConnectionCode::Connect, terminal: $this->terminal));
    }

    /**
     * Types text in the input field, for the next key: one string per line of the field
     */
    public function type(string ...$lines): self
    {
        $this->typed = array_values($lines);

        return $this;
    }

    /**
     * Presses a function key, with what was typed (type())
     */
    public function press(FunctionKey $key): self
    {
        $request = new GatewayRequest($key, $this->typed, $this->response()->context, $this->terminal);
        $this->typed = [];

        return $this->send($request);
    }

    /**
     * Disconnects: the service gives no response (Service::handle() gives null)
     */
    public function disconnect(): self
    {
        $response = $this->service->handle(
            new GatewayRequest(ConnectionCode::Disconnect, [], $this->response->context ?? '', $this->terminal),
        );
        Assert::assertNull($response, self::DISCONNECTION_RESPONDED);

        return $this;
    }

    public function assertScreenContains(string $text): self
    {
        Assert::assertStringContainsString(
            $text,
            $this->screen()->text(),
            $this->failure("The screen doesn't show \"{$text}\""),
        );

        return $this;
    }

    public function assertScreenNotContains(string $text): self
    {
        Assert::assertStringNotContainsString(
            $text,
            $this->screen()->text(),
            $this->failure("The screen shows \"{$text}\""),
        );

        return $this;
    }

    /**
     * Asserts the text of a row (Screen::line()) contains text
     */
    public function assertLineContains(int $row, string $text): self
    {
        Assert::assertStringContainsString(
            $text,
            $this->screen()->line($row),
            $this->failure("Row {$row} doesn't show \"{$text}\""),
        );

        return $this;
    }

    /**
     * Asserts the status line (row 0) contains text, e.g. a message of Vdt::line0()
     */
    public function assertLine0(string $text): self
    {
        return $this->assertLineContains(0, $text);
    }

    /**
     * Asserts the route the user is at, after the last response (the one their next key goes
     * to)
     */
    public function assertRoute(string $route): self
    {
        Assert::assertSame($route, $this->context()->route(), $this->failure('Not at the expected route'));

        return $this;
    }

    /**
     * Asserts the user is sent to another service, at url (RunCommand::connectTo())
     */
    public function assertConnectsTo(string $url): self
    {
        Assert::assertEquals(
            new ServiceCallCommand($url, CallMode::Connect),
            $this->response()->command,
            $this->failure('Not connected at once to the expected service'),
        );

        return $this;
    }

    /**
     * Screen the user sees: every response since the connection, played
     */
    public function screen(): Screen
    {
        return Screen::of($this->vdt);
    }

    /**
     * Last response of the service
     */
    public function response(): GatewayResponse
    {
        return $this->response ?? throw new \LogicException('No response yet: connect() first');
    }

    /**
     * Context of the last response, as the service will restore it
     * (ContextSerializer::unserialize())
     */
    public function context(): Context
    {
        return $this->serializer->unserialize($this->response()->context);
    }

    private function send(GatewayRequest $request): self
    {
        $this->response = $this->service->handle($request) ?? throw new \LogicException(
            'No response to ' . $request->fctn(),
        );
        $this->vdt = $this->vdt->append($this->response->vdt);

        return $this;
    }

    /**
     * Message of a failed assertion, with the screen
     */
    private function failure(string $message): string
    {
        return "{$message}. Screen:\n" . rtrim($this->screen()->preview(), characters: "\n");
    }
}
