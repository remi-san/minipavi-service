<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Vdt;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The form of HtmlClient's page, both ways: render() draws the screen (HtmlRenderer), with the
 * input fields of the command over it, the function keys under it (those the input doesn't
 * accept disabled), and, hidden, the context, the command and the screen so far; read() gives
 * back what the page posted (PostedForm). Its fields, and how the command is posted back, are
 * this class's alone.
 */
final readonly class HtmlForm
{
    // Names of the form's fields
    private const KEY = 'key';
    private const CONTENT = 'content';
    private const CONTEXT = 'context';
    private const COMMAND = 'command';
    private const SCREEN = 'screen';

    /**
     * @param Messages $messages the service's: the labels of the keys
     */
    public function __construct(
        private HtmlRenderer $renderer,
        private Messages $messages,
    ) {}

    /**
     * The page showing the screen (from its last clear screen, what still shows), with the
     * input fields of the command, the keys, and, hidden, the context and command to post back
     *
     * @param string $title of the page, in the browser (e.g. the URL of the service)
     */
    public function render(Vdt $screen, string $context, ?InputCommand $command, string $title): string
    {
        $screen = $screen->fromLastClear();
        $hidden =
            self::hidden(self::CONTEXT, $context)
            . self::hidden(self::COMMAND, $command === null ? '' : self::encoded($command))
            . self::hidden(self::SCREEN, base64_encode($screen->toString()));

        return $this->renderer->render(
            $screen->screen(),
            $title,
            self::fields($command),
            $hidden . $this->keys($command),
        );
    }

    /**
     * What the page posted back with a key; null without a key (e.g. the first request)
     */
    public function read(ServerRequestInterface $request): ?PostedForm
    {
        $form = self::form($request);
        $key = FunctionKey::tryFrom(self::text($form, self::KEY));
        if ($key === null) {
            return null;
        }
        $content = \is_array($form[self::CONTENT] ?? null) ? $form[self::CONTENT] : [];

        return new PostedForm(
            $key,
            array_values(array_map(static fn(mixed $line) => \is_string($line) ? $line : '', $content)),
            self::text($form, self::CONTEXT),
            new HtmlPageState(
                new Vdt((string) base64_decode(self::text($form, self::SCREEN), strict: true)),
                self::command(self::text($form, self::COMMAND)),
            ),
        );
    }

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
                . $field->span->length
                . '"'
                . ' value="'
                . HtmlRenderer::escape($command->prefill[$line] ?? '')
                . '"'
                . ' style="'
                . HtmlRenderer::at($field->span->start->col, $field->span->start->row + $line, $field->span->length)
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
                new Span(
                    new Position(
                        self::int($command, name: 'row', default: 1),
                        self::int($command, name: 'col', default: 1),
                    ),
                    self::int($command, name: 'length', default: 1),
                ),
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
            'row' => $field->span->start->row,
            'col' => $field->span->start->col,
            'length' => $field->span->length,
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
