<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

/**
 * Page a PaginatedController opens at, when a ChangePage switches to it (its first run):
 * the one its PageParameters give, or always the first one. Either way, without parameters
 * (their defaults, as when the user comes back to a list left before) it is the first page.
 */
enum OpeningPage
{
    // The page of the PageParameters a ChangePage gives (the first one by default)
    case Given;

    // The first page, whatever the PageParameters a ChangePage gives
    case First;

    /**
     * Index of the page to open at (from 0, kept within the pages by Paginator), for the
     * parameters the controller starts with
     */
    public function index(PageParameters $parameters): int
    {
        return match ($this) {
            self::Given => $parameters->page,
            // That of the default parameters
            self::First => (new PageParameters())->page,
        };
    }
}
