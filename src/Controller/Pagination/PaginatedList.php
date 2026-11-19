<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;

/**
 * Items a PaginatedController shows one page at a time: how many there are, how many per
 * page, the data of a page, and what the keys other than Suite, Retour and Répétition do
 */
interface PaginatedList
{
    /**
     * Number of items, on all the pages
     */
    public function itemCount(): int;

    /**
     * Number of items per page (at least 1)
     */
    public function itemsPerPage(): int;

    /**
     * Data of the template showing a page, besides the paginator's variables
     * (Paginator::toArray(): {{page}}, {{pageCount}}, {{itemCount}}...)
     *
     * @return array<string, mixed>
     */
    public function pageData(Paginator $paginator): array;

    /**
     * Handles a key other than Suite, Retour and Répétition (e.g. Envoi, Sommaire), on the
     * page shown: stay on it, turn it, or any other response
     *
     * @param Request<ControllerState, PageParameters> $request
     *
     * @return Response<ControllerState>
     */
    public function onOtherKey(Request $request, ShownPage $page): Response;
}
