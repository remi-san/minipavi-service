<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\PaginatedController;
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

    protected function pageCount(): int
    {
        return (int) ceil(\count($this->items) / $this->perPage);
    }

    protected function pageData(int $page): array
    {
        return ['items' => \array_slice($this->items, $page * $this->perPage, $this->perPage)];
    }

    protected function onOtherKey(Request $request, int $page): Response
    {
        return match ($request->functionKey()) {
            FunctionKey::Summary => Response::goTo('menu'),
            FunctionKey::Send => $this->turn($request, 1),
            default => $this->stay($page),
        };
    }
}
