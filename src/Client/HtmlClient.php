<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Vdt;
use MiniPaviService\Vdt\VdtHelper;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client of a browser instead of the gateway: shows the service's screen as an HTML page
 * (HtmlRenderer), and lets the user navigate it as on a Minitel. The page is a form posting
 * back to its URL: the answer's input fields over the screen, the function keys under it
 * (those the input doesn't accept disabled), and, hidden, the context of the answer, its
 * input command, and the screen so far (an answer drawing part of the screen is drawn over
 * it). A key leaving the service (an answer whose next URL is another one) is blocked: the
 * screen stays, with FrameworkMessage::WebLeave on line 0.
 *
 * The first request (no key posted) is a connection, as the gateway's: at the default route.
 *
 * @implements Client<HtmlPageState>
 */
final readonly class HtmlClient implements Client
{
    // Names of the form's fields
    private const KEY = 'key';
    private const CONTENT = 'content';
    private const CONTEXT = 'context';
    private const COMMAND = 'command';
    private const SCREEN = 'screen';

    /**
     * @param Messages $messages the service's: the labels of the keys, the error leaving it
     */
    public function __construct(
        private HtmlRenderer $renderer,
        private Messages $messages,
    ) {}

    /**
     * The key posted, with the lines typed and the context of the last answer; without one, a
     * connection
     */
    public function request(ServerRequestInterface $request, string $serviceUrl): GatewayRequest
    {
        $form = self::form($request);
        $key = FunctionKey::tryFrom(self::text($form, self::KEY));
        if ($key !== null) {
            $content = \is_array($form[self::CONTENT] ?? null) ? $form[self::CONTENT] : [];
            // What the page showed, posted back with the key
            $state = new HtmlPageState(
                Vdt::fromString((string) base64_decode(self::text($form, self::SCREEN), true)),
                self::command(json_decode(self::text($form, self::COMMAND), true)),
            );

            return new GatewayRequest(
                $key->value,
                array_values(array_map(static fn(mixed $line) => \is_string($line) ? $line : '', $content)),
                self::text($form, self::CONTEXT),
                serviceUrl: $serviceUrl,
                clientState: $state,
            );
        }

        return new GatewayRequest(
            GatewayRequest::FCTN_CONNECT,
            serviceUrl: $serviceUrl,
            clientState: $this->initialState(),
        );
    }

    /**
     * No page yet: an empty screen, no input
     */
    public function initialState(): HtmlPageState
    {
        return new HtmlPageState();
    }

    /**
     * The page of the answer: its screen drawn over the screen so far, its input fields and the
     * function keys; or, for an answer leaving the service, the page as it was, with the error
     * on line 0
     */
    public function response(GatewayRequest $request, GatewayResponse $answer): ResponseInterface
    {
        $page = $request->clientState;
        $screen = $page->screen;

        if ($answer->next !== null && $answer->next !== $request->serviceUrl) {
            // Blocked: the user stays where they were
            $screen = $screen->append(VdtHelper::writeLine0($this->messages->get(FrameworkMessage::WebLeave)));
            $context = $request->context;
            $command = $page->command;
        } else {
            $screen = $screen->append($answer->vdt);
            $context = $answer->context;
            $command = self::command($answer->command);
        }
        $screen = $screen->fromLastClear();

        $hidden =
            self::hidden(self::CONTEXT, $context)
            // In MiniPaviCli's structure, as the answer's, to be read back the same way
            . self::hidden(self::COMMAND, $command === null ? '' : (string) json_encode(['COMMAND' => $command]))
            . self::hidden(self::SCREEN, base64_encode($screen->toString()));

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $this->renderer->render(
                $screen->screen(),
                $request->serviceUrl,
                self::fields($command),
                $hidden . $this->keys($command),
            ),
        );
    }

    /**
     * Inputs of the command's field, over the screen: one for a text field (InputTxt), one per
     * line for a message (InputMsg), prefilled as the command says
     *
     * @param ?array{name: string, param: array<string, mixed>} $command
     */
    private static function fields(?array $command): string
    {
        if ($command === null) {
            return '';
        }
        $param = $command['param'];
        $col = self::int($param, 'x', 1);
        $row = self::int($param, 'y', 1);
        $multiline = $command['name'] === 'InputMsg';
        $width = $multiline ? self::int($param, 'w', 1) : self::int($param, 'l', 1);
        $lines = $multiline ? self::int($param, 'h', 1) : 1;
        $prefill = $param['prefill'] ?? '';
        $prefill = \is_array($prefill) ? array_values($prefill) : [$prefill];

        $fields = '';
        for ($line = 0; $line < $lines; $line++) {
            $value = $prefill[$line] ?? '';
            $fields .=
                '<input class="field" name="'
                . self::CONTENT
                . '[]" maxlength="'
                . $width
                . '"'
                . ' value="'
                . HtmlRenderer::escape(\is_scalar($value) ? (string) $value : '')
                . '"'
                . ' style="'
                . HtmlRenderer::at($col, $row + $line, $width)
                . '"'
                . ($line === 0 ? ' autofocus' : '')
                . ' autocomplete="off">';
        }

        return $fields;
    }

    /**
     * The function keys, in the Minitel's 2 × 4 layout (FunctionKey's order): those the
     * command doesn't accept are disabled. Envoi comes first in the form, as the key Enter
     * presses in a field.
     *
     * @param ?array{name: string, param: array<string, mixed>} $command
     */
    private function keys(?array $command): string
    {
        $validWith = $command === null ? null : self::int($command['param'], 'validwith', 0);
        $keys = FunctionKey::cases();
        $buttons = [];
        foreach ($keys as $position => $key) {
            $enabled = $validWith === null || ($validWith & $key->mask()) !== 0;
            $button =
                '<button type="submit" name="'
                . self::KEY
                . '" value="'
                . $key->value
                . '" style="order: '
                . $position
                . '"'
                . ($enabled ? '' : ' disabled')
                . '>'
                . HtmlRenderer::escape($this->messages->get(FrameworkMessage::keyLabel($key)))
                . '</button>';
            $buttons[$key === FunctionKey::Send ? 0 : $position + 1] = $button;
        }
        ksort($buttons);

        return '<div class="keys">' . implode('', $buttons) . '</div>';
    }

    /**
     * The command of an answer (MiniPaviCli's structure: COMMAND, its name and param), null if
     * it isn't an input
     *
     * @return ?array{name: string, param: array<string, mixed>}
     */
    private static function command(mixed $command): ?array
    {
        $command = \is_array($command) ? $command['COMMAND'] ?? null : null;
        if (
            !\is_array($command)
            || !\in_array($command['name'] ?? null, ['InputTxt', 'InputMsg'], true)
            || !\is_array($command['param'] ?? null)
        ) {
            return null;
        }

        $param = [];
        foreach ($command['param'] as $name => $value) {
            if (!\is_string($name)) {
                continue;
            }

            $param[$name] = $value;
        }

        return ['name' => $command['name'], 'param' => $param];
    }

    /**
     * @return array<mixed>
     */
    private static function form(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return \is_array($body) ? $body : [];
    }

    /**
     * @param array<mixed> $values
     */
    private static function text(array $values, string $name): string
    {
        $value = $values[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function int(array $values, string $name, int $default): int
    {
        $value = $values[$name] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    private static function hidden(string $name, string $value): string
    {
        return '<input type="hidden" name="' . $name . '" value="' . HtmlRenderer::escape($value) . '">';
    }
}
