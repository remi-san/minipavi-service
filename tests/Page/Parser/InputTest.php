<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\Gateway\Command\InputCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Tests\Support\ExhaustedBacktrackLimit;
use MiniPaviService\Tests\Support\ParsedPages;
use PHPUnit\Framework\TestCase;

/**
 * The input command of a page's <input>
 */
final class InputTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testInputCommandReadsTheInputField(): void
    {
        $oneLine = $this->pages->parse(
            '<page type="static"><input row="20" col="5" length="10" lines="1" validwith="envoi suite"/></page>',
        );
        $twoLines = $this->pages->parse('<page type="static"><input/></page>');

        self::assertEquals(
            new InputCommand(
                new InputField(new Span(new Position(20, 5), 10), validWith: [FunctionKey::Send, FunctionKey::Next]),
                ['abc'],
            ),
            $oneLine->inputCommand(['abc']),
        );
        // By default: two lines from row 13, the whole width, validated with Envoi
        self::assertEquals(
            new InputCommand(
                new InputField(new Span(new Position(13, 1), 40), lines: 2, validWith: [FunctionKey::Send]),
            ),
            $twoLines->inputCommand(),
        );
        self::assertNull($this->pages->parse('<page type="static"/>')->inputCommand());
    }

    public function testInputCommandPrefillsTheLinesInOrder(): void
    {
        $page = $this->pages->parse('<page type="static"><input/></page>');

        self::assertSame(['a', 'b'], $page->inputCommand(['first' => 'a', 'second' => 'b'])?->prefill);
    }

    public function testAFailureToSplitTheValidwithKeysIsAnError(): void
    {
        $limit = ExhaustedBacktrackLimit::exhaust();
        try {
            $error = $this->pages->error('<page type="static"><input validwith="envoi suite"/></page>');
        } finally {
            $limit->restore();
        }

        self::assertSame('f.xml line 1: <input>: validwith: Backtrack limit exhausted', $error->getMessage());
    }
}
