<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Page;
use MiniPaviService\Vdt\VdtHelper;

/**
 * Controller of a screen showing items one page at a time, its page index kept in the context
 * (PageParameters): Suite and Retour change page, with a message on line 0 at the ends
 * (FrameworkMessage::LastPage, FirstPage, which the page can redefine), and Répétition
 * redraws the page.
 *
 * A controller gives the number of items (itemCount()) and of items per page
 * (itemsPerPage()), the data of a page (pageData(), from its Paginator: offset() and limit()
 * to retrieve its items, or slice()), and handles the other keys in onOtherKey(), with
 * display(), stay() and turn(). The page's template gets pageData() and the paginator's
 * variables (Paginator::toArray(): {{page}}, {{pageCount}}, {{itemCount}}...).
 *
 * @extends AbstractController<PageParameters>
 */
abstract class PaginatedController extends AbstractController
{
    /**
     * @param Page $page shown, with its messages ($this->page->message())
     */
    public function __construct(
        protected readonly Page $page,
    ) {}

    final public function parametersType(): string
    {
        return PageParameters::class;
    }

    /**
     * Number of items, on all the pages
     */
    abstract protected function itemCount(): int;

    /**
     * Number of items per page (at least 1)
     */
    abstract protected function itemsPerPage(): int;

    /**
     * Data of the template showing a page, besides the paginator's variables
     *
     * @return array<string, mixed>
     */
    abstract protected function pageData(Paginator $paginator): array;

    /**
     * Handles a key other than Suite, Retour and Répétition (e.g. Envoi, Sommaire), on a page
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    abstract protected function onOtherKey(Request $request, Paginator $paginator): Response;

    final protected function onInit(Request $request): Response
    {
        return $this->display($request, $this->paginator(0));
    }

    final protected function onDisplay(Request $request): Response
    {
        return $this->display($request, $this->paginatorOf($request));
    }

    final protected function onInitInput(Request $request): Response
    {
        return $this->stay($this->paginatorOf($request));
    }

    final protected function onReadInput(Request $request): Response
    {
        return match ($request->functionKey()) {
            FunctionKey::Next => $this->turn($request, 1),
            FunctionKey::Previous => $this->turn($request, -1),
            FunctionKey::Refresh => $this->display($request, $this->paginatorOf($request)),
            default => $this->onOtherKey($request, $this->paginatorOf($request)),
        };
    }

    /**
     * Shows a page, and reads the next input on it
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    final protected function display(Request $request, Paginator $paginator): Response
    {
        $data = $paginator->toArray() + $this->pageData($paginator);

        return $this->stay($paginator, $this->page->display($request->terminal(), $data));
    }

    /**
     * Stays on a page: sends vdt (e.g. a message on line 0), and reads the next input on it
     */
    final protected function stay(Paginator $paginator, string $vdt = ''): Response
    {
        return Response::readInput($vdt, $this->page->inputCommand(), parameters: new PageParameters($paginator->index()));
    }

    /**
     * Turns by pages (1: the next one, -1: the previous one), or stays at the end with its
     * message on line 0
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    final protected function turn(Request $request, int $by): Response
    {
        $paginator = $this->paginatorOf($request);
        $target = $paginator->index() + $by;

        if ($target < 0) {
            return $this->stay($paginator, VdtHelper::writeLine0($this->page->message(FrameworkMessage::FirstPage)));
        }
        if ($target >= $paginator->pageCount()) {
            return $this->stay($paginator, VdtHelper::writeLine0($this->page->message(FrameworkMessage::LastPage)));
        }

        return $this->display($request, $paginator->at($target));
    }

    /**
     * Page the request is on
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    private function paginatorOf(Request $request): Paginator
    {
        return $this->paginator($request->parameters()->page);
    }

    private function paginator(int $index): Paginator
    {
        return new Paginator($this->itemCount(), $this->itemsPerPage(), $index);
    }
}
