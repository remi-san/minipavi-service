<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

use MiniPaviService\Controller\AbstractController;
use MiniPaviService\Controller\ControllerState;
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
 * The page's template gets the list's pageData() and the paginator's variables
 * (Paginator::toArray(): {{page}}, {{pageCount}}, {{itemCount}}...).
 *
 * @extends AbstractController<PageParameters>
 */
final class PaginatedController extends AbstractController
{
    /**
     * @param Page $page shown, with its messages
     */
    public function __construct(
        private readonly Page $page,
        private readonly PaginatedList $list,
    ) {}

    public function parametersType(): string
    {
        return PageParameters::class;
    }

    protected function onInit(Request $request): Response
    {
        return $this->shown($request, index: 0)->display();
    }

    protected function onDisplay(Request $request): Response
    {
        return $this->shown($request)->display();
    }

    protected function onInitInput(Request $request): Response
    {
        return $this->shown($request)->stay();
    }

    protected function onReadInput(Request $request): Response
    {
        $page = $this->shown($request);

        return match ($request->functionKey()) {
            FunctionKey::Next => $page->next(),
            FunctionKey::Previous => $page->previous(),
            FunctionKey::Refresh => $page->display(),
            default => $this->list->onOtherKey($request, $page),
        };
    }

    /**
     * Page the request is on, or at index
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    private function shown(Request $request, ?int $index = null): ShownPage
    {
        return new ShownPage(
            $this->page,
            $this->list,
            $request,
            new Paginator(
                $this->list->itemCount(),
                $this->list->itemsPerPage(),
                $index ?? $request->parameters()->page,
            ),
        );
    }
}
