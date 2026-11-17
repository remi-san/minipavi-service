<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\PaginatedController;
use MiniPaviService\Controller\Paginator;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Core\FunctionKey;
use MiniPaviService\Page;

/**
 * Paginated list of items, shown $perPage at a time: Sommaire goes to the menu
 */
final class ListController extends PaginatedController
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        Page $page,
        private readonly array $items,
        private readonly int $perPage = 2,
    ) {
        parent::__construct($page);
    }

    protected function itemCount(): int
    {
        return \count($this->items);
    }

    protected function itemsPerPage(): int
    {
        return $this->perPage;
    }

    protected function pageData(Paginator $paginator): array
    {
        return ['items' => $paginator->slice($this->items)];
    }

    protected function onOtherKey(Request $request, Paginator $paginator): Response
    {
        return match ($request->functionKey()) {
            FunctionKey::Summary => Response::goTo('menu'),
            FunctionKey::Send => $this->turn($request, 1),
            default => $this->stay($paginator),
        };
    }
}
