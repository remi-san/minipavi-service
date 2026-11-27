<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\Keyboard;
use MiniPaviService\Minitel\LocalEcho;
use MiniPaviService\Minitel\Scroll;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\ScreenRows;
use MiniPaviService\Tests\Support\TestMessage;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

/**
 * The settings of a page: what its display sets up first, and its messages
 */
final class PageSettingsTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testDisplayClearsTheScreenFirst(): void
    {
        $before = "\x1F\x45\x41previous screen";
        $vdt = $this->pages->parse('<page type="static"><text row="1">x</text></page>')->display(new Terminal());

        self::assertSame(
            [' 1|x' . str_repeat(' ', times: 39) . '|'],
            ScreenRows::drawn((new Vdt($before))->append($vdt)),
        );
    }

    public function testDisplaySetsUpTheMinitelFirst(): void
    {
        $page = $this->pages->parse(
            '<page type="static" keyboard="lowercase" echo="off" scroll="off"><text row="1">x</text></page>',
        );

        self::assertStringStartsWith(
            Keyboard::Lowercase->sequence() . LocalEcho::Off->sequence() . Scroll::Off->sequence()
                . Vdt::clearScreen()->toString(),
            $page->display(new Terminal())->toString(),
        );
        $template = $this->pages->parse(
            '<page type="template" keyboard="uppercase" echo="on" scroll="on"><text row="1">{{x}}</text></page>',
        );
        self::assertStringStartsWith(
            Keyboard::Uppercase->sequence() . LocalEcho::On->sequence() . Scroll::On->sequence()
                . Vdt::clearScreen()->toString(),
            $template->display(new Terminal(), ['x' => 'y'])->toString(),
        );
    }

    public function testWithoutSetupTheMinitelIsLeftAsItIs(): void
    {
        $clear = Vdt::clearScreen()->toString();
        self::assertSame($clear, substr(
            $this->pages->parse('<page type="static"/>')->display(new Terminal())->toString(),
            offset: 0,
            length: \strlen($clear),
        ));
        // Only what is given
        self::assertStringStartsWith(
            Scroll::Off->sequence() . Vdt::clearScreen()->toString(),
            $this->pages->parse('<page type="static" scroll="off"/>')->display(new Terminal())->toString(),
        );
    }

    public function testPageRedefinesMessagesOfTheService(): void
    {
        $service = (new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bonjour {{name}}']);
        $page = (new PageXmlParser($service))->parse(<<<'XML'
            <page type="static">
              <message name="welcome">Salut {{name}}</message>
              <message name="endkey">Fin</message>
              <endkey/>
            </page>
            XML, ParsedPages::FOLDER);

        self::assertSame('Salut Léa', $page->message(TestMessage::Welcome, ['name' => 'Léa']));
        self::assertSame('Dernière page', $page->message(FrameworkMessage::LastPage));
        // Its elements too
        self::assertStringContainsString(' Fin ', $page->display(new Terminal())->toString());
        self::assertStringNotContainsString('Connexion/fin', $page->display(new Terminal())->toString());
    }

    public function testMessagesAfterTheElementsAreRedefinedToo(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bonjour'])))->parse(<<<'XML'
            <page type="static">
              <text row="1">x</text>
              <message name="welcome">Salut</message>
            </page>
            XML, ParsedPages::FOLDER);

        self::assertSame('Salut', $page->message(TestMessage::Welcome));
    }

    public function testPageWithoutMessagesHasTheServices(): void
    {
        $page = (new PageXmlParser((new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Bonjour'])))->parse('<page type="template"/>', ParsedPages::FOLDER);

        self::assertSame('Bonjour', $page->message(TestMessage::Welcome));
    }
}
