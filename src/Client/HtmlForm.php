<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Gateway\InputCommand;
use MiniPaviService\Html\Html;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Vdt\ClearScreens;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The page of HtmlClient, a form, both ways: render() draws the page, the screen
 * (HtmlRenderer) with the input fields of the command over it, the function keys under it
 * (those the input doesn't accept disabled), and, hidden, the context, the command and the
 * screen so far; read() gives back what the page posted (PostedForm). The page, its style and
 * its fields are this class's alone; the command is posted back as InputCommandJson writes
 * it. Self-contained: inline CSS and font, no JavaScript.
 */
final readonly class HtmlForm
{
    // Names of the form's fields
    private const KEY = 'key';
    private const CONTENT = 'content';
    private const CONTEXT = 'context';
    private const COMMAND = 'command';
    private const SCREEN = 'screen';

    // The page around the screen (HtmlRenderer::style() styles the screen)
    private const STYLE = <<<'CSS'
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #1c1c1c; }
        .page { font: var(--font); }
        .field { position: absolute; height: var(--h); padding: 0; border: 0; border-bottom: 1px dotted #888; background: transparent;
            color: #fff; caret-color: #fff; font: inherit; letter-spacing: 0; outline: none; z-index: 2; }
        .field:focus { border-bottom-color: #fff; }
        .keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 10px; }
        .keys button { padding: 6px 0; border: 0; border-radius: 3px; background: #6b4b32; color: #fff; font: inherit; cursor: pointer; }
        .keys button:disabled { opacity: .35; cursor: default; }
        CSS;

    /**
     * @param Messages $messages the service's: the labels of the keys
     * @param InputCommandJson $commands how the command is posted back with the page
     */
    public function __construct(
        private HtmlRenderer $renderer,
        private Messages $messages,
        private InputCommandJson $commands = new InputCommandJson(),
    ) {}

    /**
     * The page showing the screen (from its last clear screen, what still shows), with the
     * input fields of the command, the keys, and, hidden, the context and command to post back
     *
     * @param string $title of the page, in the browser (e.g. the URL of the service)
     */
    public function render(Vdt $screen, string $context, ?InputCommand $command, string $title): string
    {
        $screen = (new ClearScreens())->fromLast($screen);
        $hidden =
            self::hidden(self::CONTEXT, $context)
            . self::hidden(self::COMMAND, $command === null ? '' : $this->commands->encode($command))
            . self::hidden(self::SCREEN, base64_encode($screen->toString()));

        return (
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>'
            . Html::escape($title)
            . '</title><style>'
            . $this->renderer->style()
            . self::STYLE
            . '</style></head>'
            . '<body><form method="post" class="page">'
            . $this->renderer->render(Screen::of($screen), $this->fields($command))
            . $hidden
            . $this->keys($command)
            . '</form></body></html>'
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
                $this->commands->decode(self::text($form, self::COMMAND)),
            ),
        );
    }

    /**
     * Inputs of the command's field, over the screen: one per line (one for a text field),
     * prefilled as the command says
     */
    private function fields(?InputCommand $command): string
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
                . Html::escape($command->prefill[$line] ?? '')
                . '"'
                . ' style="'
                . $this->renderer->place(
                    new Position($field->span->start->row + $line, $field->span->start->col),
                    $field->span->length,
                )
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
                . Html::escape($this->messages->get(FrameworkMessage::keyLabel($key)))
                . '</button>';
            $buttons[$key === FunctionKey::Send ? 0 : $position + 1] = $button;
        }
        ksort($buttons);

        return '<div class="keys">' . implode('', $buttons) . '</div>';
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

    private static function hidden(string $name, string $value): string
    {
        return '<input type="hidden" name="' . $name . '" value="' . Html::escape($value) . '">';
    }
}
