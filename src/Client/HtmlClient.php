<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\Client;
use MiniPaviService\Gateway\ConnectionCode;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Gateway\GatewayResponse;
use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
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
                Vdt::fromString((string) base64_decode(self::text($form, self::SCREEN), strict: true)),
                self::command(self::text($form, self::COMMAND)),
            );

            return new GatewayRequest(
                $key,
                array_values(array_map(static fn(mixed $line) => \is_string($line) ? $line : '', $content)),
                self::text($form, self::CONTEXT),
                serviceUrl: $serviceUrl,
                clientState: $state,
            );
        }

        return new GatewayRequest(ConnectionCode::Connect, serviceUrl: $serviceUrl, clientState: $this->initialState());
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

        if ($answer->next !== null && $answer->next !== $request->serviceUrl) {
            // Blocked: the user stays where they were
            return $this->page(
                $page->screen->append(VdtHelper::writeLine0($this->messages->get(FrameworkMessage::WebLeave))),
                $request->context,
                $page->command,
                $request->serviceUrl,
            );
        }

        return $this->page(
            $page->screen->append($answer->vdt),
            $answer->context,
            $answer->command,
            $request->serviceUrl,
        );
    }

    /**
     * The page showing the screen so far, with the input fields of the command, the keys, and,
     * hidden, the context and command to post back to the service's URL
     *
     * @param ?array{name: string, param: array<string, mixed>} $command
     */
    /**
     * The page showing the screen so far, with the input fields of the command, the keys, and,
     * hidden, the context and command to post back to the service's URL
     */
    private function page(Vdt $screen, string $context, ?InputCommand $command, string $serviceUrl): ResponseInterface
    {
        $screen = $screen->fromLastClear();

        $hidden =
            self::hidden(self::CONTEXT, $context)
            . self::hidden(self::COMMAND, $command === null ? '' : self::encoded($command))
            . self::hidden(self::SCREEN, base64_encode($screen->toString()));

        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $this->renderer->render(
                $screen->screen(),
                $serviceUrl,
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
    /**
     * Inputs of the command's field, over the screen: one per line (one for a text field),
     * prefilled as the command says
     */
    private static function fields(?InputCommand $command): string
    {
        if ($command === null) {
            return '';
        }

        $field = $command->field;
        $fields = '';
        for ($line = 0; $line < $field->lines; $line++) {
            $fields .=
                '<input class="field" name="'
                . self::CONTENT
                . '[]" maxlength="'
                . $field->length
                . '"'
                . ' value="'
                . HtmlRenderer::escape($command->prefill[$line] ?? '')
                . '"'
                . ' style="'
                . HtmlRenderer::at($field->col, $field->row + $line, $field->length)
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
     */
    private function keys(?InputCommand $command): string
    {
        $keys = FunctionKey::cases();
        $buttons = [];
        foreach ($keys as $position => $key) {
            $enabled = $command === null || $command->field->isValidatedWith($key);
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
    /**
     * The command posted back with the page (encoded()), null if there is none or it can't be
     * read: what the browser posts may be anything
     */
    private static function command(string $json): ?InputCommand
    {
        $command = json_decode($json, associative: true);
        if (!\is_array($command)) {
            return null;
        }
        foreach (['row', 'col', 'length', 'lines'] as $name) {
            if (!\is_int($command[$name] ?? null)) {
                return null;
            }
        }
        $validWith = [];
        foreach (\is_array($command['validWith'] ?? null) ? $command['validWith'] : [] as $name) {
            $key = \is_string($name) ? FunctionKey::tryFrom($name) : null;
            if ($key === null) {
                continue;
            }
            $validWith[] = $key;
        }
        $prefill = array_values(array_map(
            static fn(mixed $line) => \is_string($line) ? $line : '',
            \is_array($command['prefill'] ?? null) ? $command['prefill'] : [],
        ));
        try {
            $field = new InputField(
                self::int($command, name: 'row', default: 1),
                self::int($command, name: 'col', default: 1),
                self::int($command, name: 'length', default: 1),
                self::int($command, name: 'lines', default: 1),
                $validWith,
                ($command['cursor'] ?? true) === true,
                self::text($command, name: 'spaceChar'),
            );
        } catch (\InvalidArgumentException) {
            // Off the screen
            return null;
        }

        return new InputCommand($field, $prefill);
    }

    /**
     * The command as JSON, posted back with the page, read by command()
     */
    private static function encoded(InputCommand $command): string
    {
        $field = $command->field;

        return (string) json_encode([
            'row' => $field->row,
            'col' => $field->col,
            'length' => $field->length,
            'lines' => $field->lines,
            'validWith' => array_map(static fn(FunctionKey $key) => $key->value, $field->validWith),
            'cursor' => $field->cursor,
            'spaceChar' => $field->spaceChar,
            'prefill' => $command->prefill,
        ]);
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
     * @param array<mixed> $values
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
