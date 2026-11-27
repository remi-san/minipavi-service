<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

use MiniPaviService\Controller\AbstractController;
use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Parameters\ParametersType;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Page;

/**
 * Controller of a screen showing the items of a PaginatedList one page at a time, its page
 * index kept in the context (PageParameters): Suite and Retour change page, with a message on
 * line 0 at the ends (FrameworkMessage::LastPage, FirstPage, which the page can redefine), and
 * Répétition redraws the page. The list handles the other keys, on the ShownPage.
 *
 * A ChangePage to it with PageParameters opens it at that page, or at the first one with
 * OpeningPage::First; without parameters, at the first page.
 *
 * The page's template gets the list's pageData() and the paginator's variables
 * (Paginator::toArray(): {{page}}, {{pageCount}}, {{itemCount}}...).
 *
 * @extends AbstractController<PageParameters>
 */
final class PaginatedController extends AbstractController
{
    /**
     * @param Page $page shown, with its messages
     * @param OpeningPage $opening page it opens at, when a ChangePage switches to it
     */
    public function __construct(
        private readonly Page $page,
        private readonly PaginatedList $list,
        private readonly OpeningPage $opening = OpeningPage::Given,
    ) {}

    public function parametersType(): ParametersType
    {
        return ParametersType::of(PageParameters::class);
    }

    protected function onInit(Request $request): Response
    {
        return $this->shown($request, $this->opening->index($request->parameters))->display();
    }

    protected function onDisplay(Request $request): Response
    {
        return $this->shown($request, $request->parameters->page)->display();
    }

    protected function onInitInput(Request $request): Response
    {
        return $this->shown($request, $request->parameters->page)->stay();
    }

    protected function onReadInput(Request $request): Response
    {
        $page = $this->shown($request, $request->parameters->page);

        return match ($request->functionKey()) {
            FunctionKey::Next => $page->next(),
            FunctionKey::Previous => $page->previous(),
            FunctionKey::Refresh => $page->display(),
            default => $this->list->onOtherKey($request, $page),
        };
    }

    /**
     * Page at index, for the request
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    private function shown(Request $request, int $index): ShownPage
    {
        return new ShownPage(
            $this->page,
            $this->list,
            $request,
            new Paginator($this->list->itemCount(), $this->list->itemsPerPage(), $index),
        );
    }
}
