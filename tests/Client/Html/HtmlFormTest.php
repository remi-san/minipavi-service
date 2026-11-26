<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Client\Html;

use MiniPaviService\Client\Html\HtmlForm;
use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\HttpRequests;
use MiniPaviService\Tests\Support\MemoryFiles;
use MiniPaviService\Vdt\Screen\HtmlRenderer;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The page's HTML as it is written (what the browser makes of it: HtmlClientTest); the
 * screen's own HTML is HtmlRendererTest's
 */
final class HtmlFormTest extends TestCase
{
    // The page's style, after the screen's
    private const STYLE = <<<'CSS'
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #1c1c1c; }
        .page { font: var(--font); }
        .field { --underline: #888; padding: 0; border: 0;
            background: repeating-linear-gradient(90deg, var(--underline) 0 1px, transparent 1px 2px) bottom / 100% 1px no-repeat;
            color: #fff; caret-color: #fff; font: inherit; letter-spacing: 0; outline: none; }
        .field:focus { --underline: #fff; }
        .keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 10px; }
        .keys button { padding: 6px 0; border: 0; border-radius: 3px; background: #6b4b32; color: #fff; font: inherit; cursor: pointer; }
        .keys button:disabled { opacity: .35; cursor: default; }
        CSS;

    public function testDrawsThePageWithItsFieldsAndKeys(): void
    {
        $renderer = self::renderer();
        $command = new InputCommand(
            new InputField(
                new Span(new Position(2, 3), 4),
                lines: 2,
                validWith: [FunctionKey::Send, FunctionKey::Next],
            ),
            ['a"b'],
        );

        $html = self::form()
            ->render(new Vdt("X\x0CY"), context: '{"route":"home"}', command: $command, title: 'Svc <1>');

        self::assertSame(
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Svc &lt;1&gt;</title>'
            . '<style>'
            . $renderer->style()
            . self::STYLE
            . '</style></head>'
            . '<body><form method="post" class="page">'
            // The screen from its last clear screen, with an input per line of the field over it
            . $renderer->render(
                Screen::of(new Vdt("\x0CY")),
                '<input class="field" name="content[]" maxlength="4" value="a&quot;b" style="'
                . $renderer->place(new Position(row: 2, col: 3), length: 4)
                . '" autofocus autocomplete="off">'
                . '<input class="field" name="content[]" maxlength="4" value="" style="'
                . $renderer->place(new Position(row: 3, col: 3), length: 4)
                . '" autocomplete="off">',
            )
            // Posted back as they are
            . '<input type="hidden" name="context" value="{&quot;route&quot;:&quot;home&quot;}">'
            . '<input type="hidden" name="command" value="{&quot;row&quot;:2,&quot;col&quot;:3,&quot;length&quot;:4,'
            . '&quot;lines&quot;:2,&quot;validWith&quot;:[&quot;ENVOI&quot;,&quot;SUITE&quot;],&quot;cursor&quot;:true,'
            . '&quot;spaceChar&quot;:&quot;.&quot;,&quot;prefill&quot;:[&quot;a\&quot;b&quot;]}">'
            . '<input type="hidden" name="screen" value="'
            . base64_encode("\x0CY")
            . '">'
            // Envoi first, the key Enter presses; shown in the Minitel's layout (their order)
            . '<div class="keys">'
            . '<button type="submit" name="key" value="ENVOI" style="order: 7">Envoi</button>'
            . '<button type="submit" name="key" value="SOMMAIRE" style="order: 0" disabled>Sommaire</button>'
            . '<button type="submit" name="key" value="ANNULATION" style="order: 1" disabled>Annulation</button>'
            . '<button type="submit" name="key" value="RETOUR" style="order: 2" disabled>Retour</button>'
            . '<button type="submit" name="key" value="REPETITION" style="order: 3" disabled>Répétition</button>'
            . '<button type="submit" name="key" value="GUIDE" style="order: 4" disabled>Guide</button>'
            . '<button type="submit" name="key" value="CORRECTION" style="order: 5" disabled>Correction</button>'
            . '<button type="submit" name="key" value="SUITE" style="order: 6">Suite</button>'
            . '</div>'
            . '</form></body></html>',
            $html,
        );
    }

    /**
     * @return iterable<string, array{array<mixed>, list<string>}>
     */
    public static function postedLines(): iterable
    {
        yield 'in their order' => [['ab', 'cd'], ['ab', 'cd']];
        // As a browser could post them (content[3]=ab&content[1]=cd): the first line first
        yield 'with keys' => [[3 => 'ab', 1 => 'cd'], ['ab', 'cd']];
        // content[1][]=cd: not a line typed
        yield 'not text' => [['ab', ['cd']], ['ab', '']];
    }

    /**
     * @param array<mixed> $content
     * @param list<string> $lines
     */
    #[DataProvider('postedLines')]
    public function testReadsTheLinesTyped(array $content, array $lines): void
    {
        $posted = self::form()
            ->read(HttpRequests::posting(
                'Mozilla/5.0',
                ['web' => 'true'],
                [
                    'key' => 'ENVOI',
                    'content' => $content,
                ],
            ));

        self::assertSame($lines, $posted?->content);
    }

    private static function form(): HtmlForm
    {
        return new HtmlForm(self::renderer(), (new MessagesXmlParser())->defaults());
    }

    private static function renderer(): HtmlRenderer
    {
        return new HtmlRenderer('/font.ttf', new MemoryFiles(['/font.ttf' => 'FONT']));
    }
}
