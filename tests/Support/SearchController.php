<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Vdt\Vdt;

/**
 * Controller with states of its own (SearchState): shows its state and page
 *
 * @implements Controller<SearchState, PageParameters>
 */
final class SearchController implements Controller
{
    public function initialState(): SearchState
    {
        return SearchState::Init;
    }

    public function parametersType(): string
    {
        return PageParameters::class;
    }

    public function run(Request $request): Response
    {
        return Response::display(Vdt::text($request->state()->value . ' ' . $request->parameters()->page), SearchState::ReadQuery);
    }
}
