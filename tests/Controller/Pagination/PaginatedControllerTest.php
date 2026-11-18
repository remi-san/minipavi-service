<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller\Pagination;

use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Message\Messages;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Page;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Testing\ServiceTester;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ListController;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\TestCase;

final class PaginatedControllerTest extends TestCase
{
    private const PAGE = <<<'XML'
        <page type="template">
          <foreach items="items" as="item" row="2" step="1"><text>{{item}}</text></foreach>
          <text row="22">{{firstItem}}-{{lastItem}} of {{itemCount}}, {{perPage}} per page</text>
          <text row="23">Page {{page}}/{{pageCount}}</text>
        </page>
        XML;

    private RouterBuilder $routes;

    protected function setUp(): void
    {
        $this->routes = new RouterBuilder('menu');
        $this->routes->addRoute('menu', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => $request->key() === 'ENVOI' ? Response::goTo('list') : Response::display(new Vdt('MENU'))));
    }

    public function testShowsTheFirstPageWithItsNumber(): void
    {
        $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->assertRoute('list')
            ->assertLineContains(2, text: 'a')
            ->assertLineContains(3, text: 'b')
            ->assertScreenContains('Page 1/3')
            ->assertLineContains(22, text: '1-2 of 5, 2 per page');
    }

    public function testSuiteAndRetourChangePage(): void
    {
        $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)
            ->assertLineContains(2, text: 'c')
            ->assertScreenContains('Page 2/3')
            ->assertScreenNotContains('a ')
            ->press(FunctionKey::Next)
            ->assertLineContains(2, text: 'e')
            ->assertScreenContains('Page 3/3')
            ->assertLineContains(22, text: '5-5 of 5, 2 per page')
            ->press(FunctionKey::Previous)
            ->assertLineContains(2, text: 'c')
            ->assertScreenContains('Page 2/3');
    }

    public function testEndsStayWithAMessageOnLine0(): void
    {
        $this
            ->tester(['a', 'b', 'c'])
            ->press(FunctionKey::Previous)
            ->assertLine0('Première page')
            ->assertScreenContains('Page 1/2')
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Next)
            ->assertLine0('Dernière page')
            ->assertScreenContains('Page 2/2')
            // Still on the last page
            ->press(FunctionKey::Previous)
            ->assertScreenContains('Page 1/2');
    }

    public function testPageRedefinesTheEndMessages(): void
    {
        $page = (new PageXmlParser(Messages::defaults()))->parse(
            str_replace(
                '<foreach',
                replace: '<message name="page.last">Fin de la liste</message><foreach',
                subject: self::PAGE,
            ),
            __DIR__,
        );
        $this->routes->addRoute('list', new ListController($page, ['a', 'b', 'c']));

        $router = $this->routes->build();
        (new ServiceTester(new Service($router, Messages::defaults(), new ContextSerializer($router))))
            ->connect()
            ->press(FunctionKey::Send)
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Next)
            ->assertLine0('Fin de la liste')
            ->press(FunctionKey::Previous)
            ->press(FunctionKey::Previous)
            ->assertLine0('Première page');
    }

    public function testRepetitionRedrawsTheCurrentPage(): void
    {
        $tester = $this->tester(['a', 'b', 'c'])->press(FunctionKey::Next)->press(FunctionKey::Refresh);

        // The whole page again: the answer alone draws it
        $redrawn = $tester->answer()->vdt->screen();
        self::assertStringContainsString('Page 2/2', $redrawn->text());
        self::assertStringStartsWith('c', $redrawn->line(2));
        $tester->assertScreenContains('Page 2/2')->assertLineContains(2, text: 'c');
        self::assertEquals(new PageParameters(1), $tester->context()->parameters());
    }

    public function testOtherKeysAreTheControllers(): void
    {
        $this
            ->tester(['a', 'b', 'c'])
            ->press(FunctionKey::Send)
            ->assertScreenContains('Page 2/2')
            ->press(FunctionKey::Summary)
            ->assertRoute('menu');
    }

    public function testPageKeptBeyondTheLastOneShowsTheLastOne(): void
    {
        // At the third page of five items, when the list gets shorter: three items
        $context = $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Next)
            ->assertScreenContains('Page 3/3')
            ->answer()
            ->context;
        $this->routes->addRoute('list', new ListController($this->page(), ['a', 'b', 'c']));

        $router = $this->routes->build();
        $answer = (new Service($router, Messages::defaults(), new ContextSerializer($router)))->handle(
            new GatewayRequest(FunctionKey::Refresh, [], $context),
        );

        $screen = $answer?->vdt->screen() ?? self::fail('No answer');
        self::assertStringContainsString('Page 2/2', $screen->text());
        self::assertStringStartsWith('c', $screen->line(2));
    }

    public function testEmptyListHasOnePage(): void
    {
        $this->tester([])->assertScreenContains('Page 1/1')->press(FunctionKey::Next)->assertLine0('Dernière page');
    }

    /**
     * Session at the first page of a list of items, reached from the menu
     *
     * @param list<string> $items
     */
    private function tester(array $items): ServiceTester
    {
        $this->routes->addRoute('list', new ListController($this->page(), $items));

        $router = $this->routes->build();

        return (new ServiceTester(new Service($router, Messages::defaults(), new ContextSerializer($router))))
            ->connect()
            ->press(FunctionKey::Send);
    }

    private function page(): Page
    {
        return (new PageXmlParser(Messages::defaults()))->parse(self::PAGE, __DIR__);
    }
}
