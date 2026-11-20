<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Pagination\PageParameters;
use MiniPaviService\Controller\Pagination\PaginatedList;
use MiniPaviService\Controller\Pagination\Paginator;
use MiniPaviService\Controller\Pagination\ShownPage;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\ChangePage;
use MiniPaviService\Controller\Response\DisplayPage;
use MiniPaviService\Controller\Response\RunCommand;
use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Vdt\Vdt;

/**
 * List of items, shown $perPage at a time: Envoi turns the page, Sommaire goes to the menu,
 * Guide stays on the page with its message welcome on line 0, Correction connects to the help
 * service (HELP), the page kept to be shown again on the return (ControllerState::Display),
 * and Annulation shows "Annulé" on line 0, the page kept to read its input again on the next
 * key (ControllerState::InitInput)
 */
final readonly class ListItems implements PaginatedList
{
    public const HELP = 'https://example.org/help';

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
            FunctionKey::Summary => new ChangePage('menu'),
            FunctionKey::Send => $page->next(),
            FunctionKey::Guide => $page->stay(Vdt::line0($page->message(TestMessage::Welcome, ['name' => 'Léa']))),
            FunctionKey::Correction => RunCommand::connectTo(
                self::HELP,
                state: ControllerState::Display,
                parameters: new PageParameters($page->paginator->index()),
            ),
            FunctionKey::Cancel => new DisplayPage(
                Vdt::line0('Annulé'),
                ControllerState::InitInput,
                new PageParameters($page->paginator->index()),
            ),
            default => $page->stay(),
        };
    }
}
