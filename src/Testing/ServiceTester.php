<?php

declare(strict_types=1);

namespace MiniPaviService\Testing;

use MiniPaviService\Core\Context;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\CallMode;
use MiniPaviService\Gateway\ClientState;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Assert;

/**
 * Scripts a session of a user against a service, without the gateway, for functional tests
 * (PHPUnit): it runs the service on requests built in memory, plays the answers on a
 * simulated Screen, and gives the context of each answer back with the next request, as the
 * gateway does.
 *
 *     $tester = new ServiceTester($service);
 *     $tester->connect()->assertScreenContains('OCTOBLUE')
 *         ->type('2')->press(FunctionKey::Send)->assertRoute('games')
 *         ->press(FunctionKey::Next)->assertLine0('Dernière page');
 *
 * Assertions fail with the screen as text, to see what the user would.
 */
final class ServiceTester
{
    // PHPUnit's assertions take no named arguments: their messages are constants
    private const DISCONNECTION_ANSWERED = 'A disconnection is answered';

    // Videotex the session received, since the connection: the screen is all of it, played
    private Vdt $vdt;

    private ?GatewayResponse $answer = null;

    /** @var list<string> text typed for the next key, one string per line */
    private array $typed = [];

    public function __construct(
        private Service $service,
        private Terminal $terminal = new Terminal(),
    ) {
        $this->vdt = new Vdt();
    }

    /**
     * Connects to the service, as a new session: an empty screen, no context
     */
    public function connect(): self
    {
        $this->vdt = new Vdt();
        $this->answer = null;

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
        $request = new GatewayRequest($key, $this->typed, $this->answer()->context, $this->terminal);
        $this->typed = [];

        return $this->send($request);
    }

    /**
     * Disconnects: the service answers nothing (Service::handle() gives null)
     */
    public function disconnect(): self
    {
        $answer = $this->service->handle(
            new GatewayRequest(ConnectionCode::Disconnect, [], $this->answer->context ?? '', $this->terminal),
        );
        Assert::assertNull($answer, self::DISCONNECTION_ANSWERED);

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
     * Asserts the status line (row 0) contains text, e.g. a message of VdtHelper::writeLine0()
     */
    public function assertLine0(string $text): self
    {
        return $this->assertLineContains(0, $text);
    }

    /**
     * Asserts the route the user is at, after the last answer (the one their next key goes to)
     */
    public function assertRoute(string $route): self
    {
        Assert::assertSame($route, $this->context()->route(), $this->failure('Not at the expected route'));

        return $this;
    }

    /**
     * Asserts the user is sent to another service, at url (Response::connectTo())
     */
    public function assertConnectsTo(string $url): self
    {
        Assert::assertSame($url, $this->answer()->next, $this->failure('Not sent to the expected service'));
        Assert::assertSame(
            CallMode::Connect,
            $this->answer()->directCall,
            $this->failure('Not connected at once to the other service'),
        );

        return $this;
    }

    /**
     * Screen the user sees: every answer since the connection, played
     */
    public function screen(): Screen
    {
        return $this->vdt->screen();
    }

    /**
     * Last answer of the service
     */
    public function answer(): GatewayResponse
    {
        return $this->answer ?? throw new \LogicException('No answer yet: connect() first');
    }

    /**
     * Context of the last answer, as the service will restore it
     */
    public function context(): Context
    {
        return $this->service->restore($this->answer()->context);
    }

    /**
     * @param GatewayRequest<ClientState> $request
     */
    private function send(GatewayRequest $request): self
    {
        $this->answer = $this->service->handle($request) ?? throw new \LogicException(
            'No answer to ' . $request->fctn(),
        );
        $this->vdt = $this->vdt->append($this->answer->vdt);

        return $this;
    }

    /**
     * Message of a failed assertion, with the screen
     */
    private function failure(string $message): string
    {
        $lines = explode("\n", $this->screen()->text());

        return (
            "{$message}. Screen:\n"
            . implode("\n", array_map(
                static fn(int $row, string $line) => sprintf('%2d|%s|', $row, $line),
                array_keys($lines),
                $lines,
            ))
        );
    }
}
