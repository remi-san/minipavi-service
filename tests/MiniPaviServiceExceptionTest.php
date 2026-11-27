<?php

declare(strict_types=1);

namespace MiniPaviService\Tests;

use MiniPaviService\Core\Context\ContextSerializer;
use MiniPaviService\Core\Context\Signing\ForgedContextException;
use MiniPaviService\Core\Context\Signing\HmacContextSigner;
use MiniPaviService\Core\Context\UnreadableContextException;
use MiniPaviService\Core\Routing\RouterBuilder;
use MiniPaviService\Core\Routing\UnknownRouteException;
use MiniPaviService\InvalidValueException;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessagesException;
use MiniPaviService\MiniPaviServiceException;
use MiniPaviService\Minitel\Position;
use MiniPaviService\Minitel\Terminal;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Page\Template\TemplateException;
use MiniPaviService\Tests\Support\ParsedPages;
use MiniPaviService\Tests\Support\StepController;
use MiniPaviService\Xml\XmlDocument;
use MiniPaviService\Xml\XmlException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A service catches every error of the framework as a MiniPaviServiceException, each keeping
 * its own type and its SPL parent
 */
final class MiniPaviServiceExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(): mixed, class-string<\Throwable>, class-string<\Throwable>}>
     */
    public static function errors(): iterable
    {
        yield 'a value the framework can\'t take' => [
            static fn() => new Position(30, 1),
            InvalidValueException::class,
            \InvalidArgumentException::class,
        ];
        yield 'an XML file that can\'t be read' => [
            static fn() => XmlDocument::load('<page', 'page.xml'),
            XmlException::class,
            \RuntimeException::class,
        ];
        yield 'a page that can\'t be read' => [
            static fn() => (new ParsedPages())->parse('<page'),
            PageXmlException::class,
            \RuntimeException::class,
        ];
        yield 'a template that can\'t be displayed' => [
            static fn() => (new ParsedPages())
                ->parse('<page type="template"><text row="{{row}}">x</text></page>')
                ->display(new Terminal(), ['row' => 30]),
            TemplateException::class,
            \RuntimeException::class,
        ];
        yield 'a message that isn\'t right' => [
            static fn() => new Messages(['welcome' => 'Bonjour {{1}}']),
            MessagesException::class,
            \RuntimeException::class,
        ];
        yield 'an unknown route' => [
            static fn() => (new RouterBuilder('home'))
                ->addRoute('home', new StepController())
                ->build()
                ->route('gone'),
            UnknownRouteException::class,
            \Exception::class,
        ];
        yield 'a forged context' => [
            static fn() => (new HmacContextSigner('secret'))->verify('{"route":"home"}'),
            ForgedContextException::class,
            \RuntimeException::class,
        ];
        yield 'a context that can\'t be read' => [
            static fn() => (new ContextSerializer(
                (new RouterBuilder('home'))
                    ->addRoute('home', new StepController())
                    ->build(),
            ))->unserialize('not a context'),
            UnreadableContextException::class,
            \RuntimeException::class,
        ];
    }

    /**
     * @param \Closure(): mixed $fail
     * @param class-string<\Throwable> $type
     * @param class-string<\Throwable> $parent
     */
    #[DataProvider('errors')]
    public function testEveryErrorOfTheFrameworkIsCaughtAsOne(\Closure $fail, string $type, string $parent): void
    {
        try {
            $fail();
        } catch (MiniPaviServiceException $e) {
            self::assertSame($type, $e::class);
            self::assertInstanceOf($parent, $e);

            return;
        }
        self::fail("No {$type}");
    }
}
