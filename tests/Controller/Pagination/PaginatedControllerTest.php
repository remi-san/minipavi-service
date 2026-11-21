<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Controller\Pagination;

use MiniPaviService\Controller\Pagination\OpeningPage;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Pagination\PaginatedController;
use MiniPaviService\Controller\Parameters\NoParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Core\ContextSerializer;
use MiniPaviService\Core\RouterBuilder;
use MiniPaviService\Core\Service;
use MiniPaviService\Core\Sessions;
use MiniPaviService\Gateway\GatewayRequest;
use MiniPaviService\Message\MessagesXmlParser;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Page;
use MiniPaviService\Page\Parser\PageXmlParser;
use MiniPaviService\Testing\ServiceTester;
use MiniPaviService\Tests\Support\CallbackController;
use MiniPaviService\Tests\Support\ListItems;
use MiniPaviService\Vdt\Screen\Screen;
use MiniPaviService\Vdt\Vdt;
use PHPUnit\Framework\Attributes\DataProvider;
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
        ) => $request->functionKey() === FunctionKey::Send
            ? new ChangePage('list')
            : new DisplayPage(new Vdt('MENU'))));
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
        $page = (new PageXmlParser((new MessagesXmlParser())->defaults()))->parse(
            str_replace(
                '<foreach',
                replace: '<message name="page.last">Fin de la liste</message><foreach',
                subject: self::PAGE,
            ),
            __DIR__,
        );
        $this->routes->addRoute('list', new PaginatedController($page, new ListItems(['a', 'b', 'c'])));

        $router = $this->routes->build();
        $serializer = new ContextSerializer($router);
        (new ServiceTester(
            new Service(new Sessions($serializer, $router), (new MessagesXmlParser())->defaults()),
            $serializer,
        ))
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

        // The whole page again: the response alone draws it
        $redrawn = Screen::of($tester->response()->vdt);
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

    public function testListStaysOnThePageWithItsMessage(): void
    {
        $this
            ->tester(['a', 'b', 'c'])
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Guide)
            ->assertLine0('Aide pour Léa')
            ->assertScreenContains('Page 2/2')
            ->press(FunctionKey::Refresh)
            ->assertScreenContains('Page 2/2');
    }

    public function testListKeptToBeShownAgainShowsItsPageOnTheReturn(): void
    {
        $tester = $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Correction)
            ->assertConnectsTo(ListItems::HELP)
            // Back from the other service, on whatever key: the page it was at, drawn again
            ->press(FunctionKey::Send);

        $shown = Screen::of($tester->response()->vdt);
        self::assertStringContainsString('Page 2/3', $shown->text());
        self::assertStringStartsWith('c', $shown->line(2));
        // Read on the page, as any page shown: Suite turns it
        $tester->assertRoute('list')->press(FunctionKey::Next)->assertScreenContains('Page 3/3');
    }

    public function testListKeptToReadItsInputAgainReadsItWithoutDrawingThePage(): void
    {
        $tester = $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Cancel)
            ->assertLine0('Annulé')
            ->press(FunctionKey::Send);

        // Nothing drawn: the page is still on the screen
        self::assertSame('', $tester->response()->vdt->toString());
        self::assertEquals(new PageParameters(1), $tester->context()->parameters());
        $tester->assertScreenContains('Page 2/3')->press(FunctionKey::Next)->assertScreenContains('Page 3/3');
    }

    public function testListOpensAtThePageGiven(): void
    {
        $this->menuOpensTheListAt(new PageParameters(1));

        $this->tester(['a', 'b', 'c', 'd', 'e'])->assertLineContains(2, text: 'c')->assertScreenContains('Page 2/3');
    }

    public function testListCanAlwaysOpenAtTheFirstPage(): void
    {
        $this->menuOpensTheListAt(new PageParameters(1));

        $this
            ->tester(['a', 'b', 'c', 'd', 'e'], OpeningPage::First)
            ->assertLineContains(2, text: 'a')
            ->assertScreenContains('Page 1/3')
            // Then it turns its pages as any list
            ->press(FunctionKey::Next)
            ->assertScreenContains('Page 2/3');
    }

    /**
     * @return iterable<string, array{OpeningPage}>
     */
    public static function openingPages(): iterable
    {
        yield 'the page given' => [OpeningPage::Given];
        yield 'the first page' => [OpeningPage::First];
    }

    #[DataProvider('openingPages')]
    public function testListLeftOpensAgainAtTheFirstPage(OpeningPage $opening): void
    {
        $this
            ->tester(['a', 'b', 'c', 'd', 'e'], $opening)
            ->press(FunctionKey::Next)
            ->assertScreenContains('Page 2/3')
            ->press(FunctionKey::Summary)
            ->assertRoute('menu')
            ->press(FunctionKey::Send)
            ->assertRoute('list')
            ->assertScreenContains('Page 1/3');
    }

    public function testPageKeptBeyondTheLastOneShowsTheLastOne(): void
    {
        // At the third page of five items, when the list gets shorter: three items
        $context = $this
            ->tester(['a', 'b', 'c', 'd', 'e'])
            ->press(FunctionKey::Next)
            ->press(FunctionKey::Next)
            ->assertScreenContains('Page 3/3')
            ->response()
            ->context;
        $this->routes->addRoute('list', new PaginatedController($this->page(), new ListItems(['a', 'b', 'c'])));

        $router = $this->routes->build();
        $response = (new Service(
            new Sessions(new ContextSerializer($router), $router),
            (new MessagesXmlParser())->defaults(),
        ))->handle(new GatewayRequest(FunctionKey::Refresh, [], $context));

        $screen = Screen::of($response->vdt ?? self::fail('No response'));
        self::assertStringContainsString('Page 2/2', $screen->text());
        self::assertStringStartsWith('c', $screen->line(2));
    }

    public function testEmptyListHasOnePage(): void
    {
        $this->tester([])->assertScreenContains('Page 1/1')->press(FunctionKey::Next)->assertLine0('Dernière page');
    }

    /**
     * Session at a list of items, reached from the menu: its controller is built with the
     * opening page given, or without one (the controller's default)
     *
     * @param list<string> $items
     */
    private function tester(array $items, OpeningPage ...$opening): ServiceTester
    {
        $this->routes->addRoute('list', new PaginatedController($this->page(), new ListItems($items), ...$opening));

        $router = $this->routes->build();

        $serializer = new ContextSerializer($router);

        return (new ServiceTester(
            new Service(new Sessions($serializer, $router), (new MessagesXmlParser())->defaults()),
            $serializer,
        ))
            ->connect()
            ->press(FunctionKey::Send);
    }

    // The menu's Envoi goes to the list with these parameters
    private function menuOpensTheListAt(PageParameters $parameters): void
    {
        $this->routes->addRoute('menu', new CallbackController(NoParameters::class, static fn(
            Request $request,
        ) => $request->functionKey() === FunctionKey::Send
            ? new ChangePage('list', $parameters)
            : new DisplayPage(new Vdt('MENU'))));
    }

    // Its messages are the service's, with welcome
    private function page(): Page
    {
        return (new PageXmlParser((new MessagesXmlParser())
            ->defaults()
            ->with(['welcome' => 'Aide pour {{name}}'])))->parse(self::PAGE, __DIR__);
    }
}
