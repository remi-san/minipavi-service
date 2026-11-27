<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Page\Parser;

use MiniPaviService\InvalidValueException;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Tests\Support\FailingProperties;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\PrivateProperties;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The errors of a template, found when the page is parsed or displayed, with their line
 */
final class TemplateErrorsTest extends TestCase
{
    private ParsedPages $pages;

    protected function setUp(): void
    {
        $this->pages = new ParsedPages();
    }

    public function testTemplateErrorsGiveTheLine(): void
    {
        $page = $this->pages->parse(
            "<page type=\"template\">\n<text row=\"{{row}}\">x</text></page>",
            name: 'list.xml',
        );

        try {
            $page->display(new Terminal(), ['row' => 30]);
        } catch (TemplateException $e) {
            self::assertSame('list.xml line 2: <text>: Row 30 is off the screen (0 to 24)', $e->getMessage());
            // For the log: where the value was refused
            self::assertInstanceOf(InvalidValueException::class, $e->getPrevious());

            return;
        }
        self::fail('A row off the screen was displayed');
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function templateErrorsFoundWhenDisplayed(): iterable
    {
        yield 'a loop on an unknown variable' => [
            '<foreach items="games" as="game" row="1"><text>{{game}}</text></foreach>',
            [],
            'list.xml line 2: <foreach>: unknown variable {{games}}',
        ];
        yield 'a loop on a value that is not a list' => [
            '<foreach items="games" as="game" row="1"><text>{{game}}</text></foreach>',
            ['games' => 'Harbour Pirates'],
            'list.xml line 2: <foreach>: {{games}} is not a list (string)',
        ];
        yield 'a condition on an unknown variable' => [
            '<if test="!user.name"><text row="1">Anonymous</text></if>',
            ['user' => []],
            'list.xml line 2: <if>: unknown variable {{user.name}}: no "name" in array',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('templateErrorsFoundWhenDisplayed')]
    public function testTemplateErrorOfAVariableGivesTheLine(string $xml, array $data, string $error): void
    {
        $page = $this->pages->parse("<page type=\"template\">\n{$xml}</page>", name: 'list.xml');

        try {
            $page->display(new Terminal(), $data);
        } catch (TemplateException $e) {
            self::assertSame($error, $e->getMessage());

            return;
        }
        self::fail('The page was displayed');
    }

    public function testPrivatePropertyIsAnUnknownVariableOfTheTemplate(): void
    {
        $page = $this->pages->parse(
            "<page type=\"template\">\n<text row=\"1\">{{user.city}}</text></page>",
            name: 'user.xml',
        );

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('user.xml line 2: <text>: unknown variable {{user.city}}: no "city" in '
        . PrivateProperties::class);

        $page->display(new Terminal(), ['user' => new PrivateProperties()]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templatesReadingAVariable(): iterable
    {
        yield 'a placeholder' => ['<text row="1">{{user.name}}</text>'];
        yield 'a condition' => ['<if test="user.name"><text row="1">x</text></if>'];
        yield 'a loop' => ['<foreach items="user.name" as="n" row="1"><text row="0">x</text></foreach>'];
    }

    #[DataProvider('templatesReadingAVariable')]
    public function testBugOfAVariableIsntAnErrorOfTheTemplate(string $xml): void
    {
        $page = $this->pages->parse("<page type=\"template\">{$xml}</page>");

        // Not a TemplateException: the variable's own code failed
        $this->expectExceptionObject(new \InvalidArgumentException('a bug reading name'));

        $page->display(new Terminal(), ['user' => new FailingProperties()]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templateErrorsFoundWhenParsing(): iterable
    {
        yield 'missing image' => [
            "<page type=\"template\">\n<image>images/missing.vdt</image></page>",
            'f.xml line 2: <image>: Videotex file not found: ' . ParsedPages::FOLDER . '/images/missing.vdt',
        ];
        yield 'missing background, in an <if>' => [
            "<page type=\"template\">\n<if test=\"x\">\n<background>missing.vdt</background></if></page>",
            'f.xml line 3: <background>: Videotex file not found: ' . ParsedPages::FOLDER . '/missing.vdt',
        ];
        yield 'missing image, in a <foreach>' => [
            "<page type=\"template\">\n<foreach items=\"xs\" as=\"x\">\n<image row=\"0\">images/missing.vdt</image></foreach></page>",
            'f.xml line 3: <image>: Videotex file not found: ' . ParsedPages::FOLDER . '/images/missing.vdt',
        ];
        yield 'number with something after it' => [
            '<page type="static"><text row="4a">x</text></page>',
            'f.xml line 1: <text>: row="4a" is not a number',
        ];
        yield 'number with something before it' => [
            '<page type="static"><text col="a4">x</text></page>',
            'f.xml line 1: <text>: col="a4" is not a number',
        ];
        yield 'two numbers' => [
            '<page type="static"><input length="4 5"/></page>',
            'f.xml line 1: <input>: length="4 5" is not a number',
        ];
        yield 'unknown color' => [
            "<page type=\"template\">\n<text color=\"purple\">x</text></page>",
            'f.xml line 2: <text>: unknown color "purple" (black, red, green, yellow, blue, magenta, cyan, white)',
        ];
    }

    /**
     * Elements without placeholders are checked as in a static page
     */
    #[DataProvider('templateErrorsFoundWhenParsing')]
    public function testTemplateElementWithoutPlaceholderIsCheckedWhenParsing(string $xml, string $error): void
    {
        self::assertSame($error, $this->pages->error($xml)->getMessage());
    }
}
