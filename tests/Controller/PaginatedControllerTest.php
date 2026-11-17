<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\NoParameters;
use MiniPaviService\Controller\PageParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Core\Router;
use MiniPaviService\Core\Service;
use MiniPaviService\Message\Messages;
use MiniPaviService\Page;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Testing\ServiceTester;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ListController;
use MiniPaviService\Vdt\VdtHelper;
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

    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->addRoute('menu', new CallbackController(NoParameters::class, fn(Request $request) => $request->key() === 'ENVOI'
            ? Response::goTo('list')
            : Response::display('MENU')));
        $this->router->setDefaultRoute('menu');
    }

    public function testShowsTheFirstPageWithItsNumber(): void
    {
        $this->tester(['a', 'b', 'c', 'd', 'e'])
            ->assertRoute('list')->assertLineContains(2, 'a')->assertLineContains(3, 'b')->assertScreenContains('Page 1/3')
            ->assertLineContains(22, '1-2 of 5, 2 per page');
    }

    public function testSuiteAndRetourChangePage(): void
    {
        $this->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)->assertLineContains(2, 'c')->assertScreenContains('Page 2/3')->assertScreenNotContains('a ')
            ->press(FunctionKey::Next)->assertLineContains(2, 'e')->assertScreenContains('Page 3/3')
            ->assertLineContains(22, '5-5 of 5, 2 per page')
            ->press(FunctionKey::Previous)->assertLineContains(2, 'c')->assertScreenContains('Page 2/3');
    }

    public function testEndsStayWithAMessageOnLine0(): void
    {
        $this->tester(['a', 'b', 'c'])
            ->press(FunctionKey::Previous)->assertLine0('Première page')->assertScreenContains('Page 1/2')
            ->press(FunctionKey::Next)->press(FunctionKey::Next)->assertLine0('Dernière page')->assertScreenContains('Page 2/2')
            // Still on the last page
            ->press(FunctionKey::Previous)->assertScreenContains('Page 1/2');
    }

    public function testPageRedefinesTheEndMessages(): void
    {
        $page = (new PageXmlParser(Messages::defaults()))->parse(str_replace('<foreach', '<message name="page.last">Fin de la liste</message><foreach', self::PAGE), __DIR__);
        $this->router->addRoute('list', new ListController($page, ['a', 'b', 'c']));

        (new ServiceTester(new Service($this->router, Messages::defaults())))->connect()->press(FunctionKey::Send)
            ->press(FunctionKey::Next)->press(FunctionKey::Next)->assertLine0('Fin de la liste')
            ->press(FunctionKey::Previous)->press(FunctionKey::Previous)->assertLine0('Première page');
    }

    public function testRepetitionRedrawsTheCurrentPage(): void
    {
        $tester = $this->tester(['a', 'b', 'c'])->press(FunctionKey::Next)->press(FunctionKey::Refresh);

        // The whole page again, from a clear screen
        $clear = VdtHelper::clearScreen();
        self::assertSame($clear, substr($tester->answer()->vdt, 0, \strlen($clear)));
        $tester->assertScreenContains('Page 2/2')->assertLineContains(2, 'c');
        self::assertEquals(new PageParameters(1), $tester->context()->parameters());
    }

    public function testOtherKeysAreTheControllers(): void
    {
        $this->tester(['a', 'b', 'c'])
            ->press(FunctionKey::Send)->assertScreenContains('Page 2/2')
            ->press(FunctionKey::Summary)->assertRoute('menu');
    }

    public function testPageKeptBeyondTheLastOneShowsTheLastOne(): void
    {
        // E.g. the list got shorter since the page was kept
        $controller = new ListController($this->page(), ['a', 'b', 'c']);

        $response = $controller->run(new Request(ControllerState::Display, new PageParameters(9)));

        self::assertEquals(new PageParameters(1), $response->parameters());
    }

    public function testEmptyListHasOnePage(): void
    {
        $this->tester([])->assertScreenContains('Page 1/1')
            ->press(FunctionKey::Next)->assertLine0('Dernière page');
    }

    /**
     * Session at the first page of a list of items, reached from the menu
     *
     * @param list<string> $items
     */
    private function tester(array $items): ServiceTester
    {
        $this->router->addRoute('list', new ListController($this->page(), $items));

        return (new ServiceTester(new Service($this->router, Messages::defaults())))->connect()->press(FunctionKey::Send);
    }

    private function page(): Page
    {
        return (new PageXmlParser(Messages::defaults()))->parse(self::PAGE, __DIR__);
    }
}
