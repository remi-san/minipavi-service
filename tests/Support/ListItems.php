<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\Pagination\PaginatedList;
use MiniPaviService\Controller\Pagination\Paginator;
use MiniPaviService\Controller\Pagination\ShownPage;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Minitel\FunctionKey;

/**
 * List of items, shown $perPage at a time: Envoi turns the page, Sommaire goes to the menu
 */
final readonly class ListItems implements PaginatedList
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        private array $items,
        private int $perPage = 2,
    ) {}

    public function itemCount(): int
    {
        return \count($this->items);
    }

    public function itemsPerPage(): int
    {
        return $this->perPage;
    }

    public function pageData(Paginator $paginator): array
    {
        return ['items' => $paginator->slice($this->items)];
    }

    public function onOtherKey(Request $request, ShownPage $page): Response
    {
        return match ($request->functionKey()) {
            FunctionKey::Summary => Response::goTo('menu'),
            FunctionKey::Send => $page->turn(1),
            default => $page->stay(),
        };
    }
}
