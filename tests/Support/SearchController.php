<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Parameters\ParametersType;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\DisplayPage;
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

    public function parametersType(): ParametersType
    {
        return ParametersType::of(PageParameters::class);
    }

    public function run(Request $request): Response
    {
        return new DisplayPage(
            Vdt::text($request->state->value . ' ' . $request->parameters->page),
            SearchState::ReadQuery,
        );
    }
}
