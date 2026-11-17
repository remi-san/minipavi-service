<?php

declare(strict_types=1);

namespace MiniPaviService\Controller;

use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Page;
use MiniPaviService\Vdt\VdtHelper;

/**
 * Controller of a screen shown one page at a time, its page index kept in the context
 * (PageParameters): Suite and Retour change page, with a message on line 0 at the ends
 * (lastPageMessage(), firstPageMessage()), and Répétition redraws the page. A controller
 * gives pageCount() and the data of a page (pageData()), and handles the other keys in
 * onOtherKey(), with display(), stay() and turn().
 *
 * The page's template gets pageData(), with {{page}} (the number of the page, from 1) and
 * {{pageCount}}.
 *
 * @extends AbstractController<PageParameters>
 */
abstract class PaginatedController extends AbstractController
{
    public function __construct(
        private readonly Page $page,
    ) {}

    final public function parametersType(): string
    {
        return PageParameters::class;
    }

    /**
     * Shown on line 0 for Retour on the first page
     */
    protected function firstPageMessage(): string
    {
        return 'Première page';
    }

    /**
     * Shown on line 0 for Suite on the last page
     */
    protected function lastPageMessage(): string
    {
        return 'Dernière page';
    }

    /**
     * Number of pages (at least 1 is shown)
     */
    abstract protected function pageCount(): int;

    /**
     * Data of the template showing page (from 0)
     *
     * @return array<string, mixed>
     */
    abstract protected function pageData(int $page): array;

    /**
     * Handles a key other than Suite, Retour and Répétition (e.g. Envoi, Sommaire) on page
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    abstract protected function onOtherKey(Request $request, int $page): Response;

    final protected function onInit(Request $request): Response
    {
        return $this->display($request, 0);
    }

    final protected function onDisplay(Request $request): Response
    {
        return $this->display($request, $this->currentPage($request));
    }

    final protected function onInitInput(Request $request): Response
    {
        return $this->stay($this->currentPage($request));
    }

    final protected function onReadInput(Request $request): Response
    {
        return match ($request->functionKey()) {
            FunctionKey::Next => $this->turn($request, 1),
            FunctionKey::Previous => $this->turn($request, -1),
            FunctionKey::Refresh => $this->display($request, $this->currentPage($request)),
            default => $this->onOtherKey($request, $this->currentPage($request)),
        };
    }

    /**
     * Shows page (from 0, kept within the pages), and reads the next input on it
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    final protected function display(Request $request, int $page): Response
    {
        $page = $this->within($page);
        $data = ['page' => $page + 1, 'pageCount' => $this->pages()] + $this->pageData($page);

        return $this->stay($page, $this->page->display($request->terminal(), $data));
    }

    /**
     * Stays on page: sends vdt (e.g. a message on line 0), and reads the next input on it
     */
    final protected function stay(int $page, string $vdt = ''): Response
    {
        return Response::readInput($vdt, $this->page->inputCommand(), parameters: new PageParameters($this->within($page)));
    }

    /**
     * Turns by pages (1: the next one, -1: the previous one), or stays at the end with its
     * message on line 0
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    final protected function turn(Request $request, int $by): Response
    {
        $page = $this->currentPage($request);
        $target = $page + $by;

        if ($target < 0) {
            return $this->stay($page, VdtHelper::writeLine0($this->firstPageMessage()));
        }
        if ($target >= $this->pages()) {
            return $this->stay($page, VdtHelper::writeLine0($this->lastPageMessage()));
        }

        return $this->display($request, $target);
    }

    /**
     * Page the request is on, kept within the pages (their count may have changed since)
     *
     * @param Request<ControllerState, PageParameters> $request
     */
    private function currentPage(Request $request): int
    {
        return $this->within($request->parameters()->page);
    }

    private function within(int $page): int
    {
        return max(0, min($page, $this->pages() - 1));
    }

    private function pages(): int
    {
        return max(1, $this->pageCount());
    }
}
