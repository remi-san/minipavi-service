<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Message\FrameworkMessage;
use MiniPaviService\Message\Message;
use MiniPaviService\Page;
use MiniPaviService\Vdt\Vdt;

/**
 * Page of a PaginatedList the user is on, and the responses staying on it or turning it;
 * built by PaginatedController
 */
final readonly class ShownPage
{
    /**
     * @param Request<ControllerState, PageParameters> $request
     */
    public function __construct(
        private Page $page,
        private PaginatedList $list,
        private Request $request,
        public Paginator $paginator,
    ) {}

    /**
     * Shows the page, and reads the next input on it
     */
    public function display(): Response
    {
        $data = $this->paginator->toArray() + $this->list->pageData($this->paginator);

        return $this->stay($this->page->display($this->request->terminal(), $data));
    }

    /**
     * Stays on the page: sends vdt (e.g. a message on line 0), and reads the next input on it
     */
    public function stay(Vdt $vdt = new Vdt()): Response
    {
        return Response::readInput(
            $vdt,
            $this->page->inputCommand(),
            parameters: new PageParameters($this->paginator->index()),
        );
    }

    /**
     * Turns by pages (1: the next one, -1: the previous one), or stays at the end with its
     * message on line 0 (FrameworkMessage::FirstPage, LastPage)
     */
    public function turn(int $by): Response
    {
        $target = $this->paginator->index() + $by;

        if ($target < 0) {
            return $this->stay(Vdt::line0($this->message(FrameworkMessage::FirstPage)));
        }
        if ($target >= $this->paginator->pageCount()) {
            return $this->stay(Vdt::line0($this->message(FrameworkMessage::LastPage)));
        }

        return (new self($this->page, $this->list, $this->request, $this->paginator->at($target)))->display();
    }

    /**
     * Text of a message for the page: its own (<message>), or the service's
     *
     * @param array<string, string|int> $variables values of its {{placeholders}}
     */
    public function message(Message $message, array $variables = []): string
    {
        return $this->page->message($message, $variables);
    }
}
