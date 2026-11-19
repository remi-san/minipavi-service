<?php

declare(strict_types=1);

namespace MiniPaviService\Controller\Pagination;

use MiniPaviService\Controller\ControllerState;
use MiniPaviService\Controller\Request;
use MiniPaviService\Controller\Response;
use MiniPaviService\Controller\Response\RunCommand;
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
        return RunCommand::readInput(
            $vdt,
            $this->page->inputCommand(),
            parameters: new PageParameters($this->paginator->index()),
        );
    }

    /**
     * The next page, or this one with FrameworkMessage::LastPage on line 0 on the last
     */
    public function next(): Response
    {
        $next = $this->paginator->next();

        return $next === null
            ? $this->stay(Vdt::line0($this->message(FrameworkMessage::LastPage)))
            : $this->at($next)->display();
    }

    /**
     * The previous page, or this one with FrameworkMessage::FirstPage on line 0 on the first
     */
    public function previous(): Response
    {
        $previous = $this->paginator->previous();

        return $previous === null
            ? $this->stay(Vdt::line0($this->message(FrameworkMessage::FirstPage)))
            : $this->at($previous)->display();
    }

    /**
     * Text of a message for the page: its own (<message>), or the service's
     *
     * @param array<string, mixed> $variables values of its {{placeholders}} (Messages::get())
     */
    public function message(Message $message, array $variables = []): string
    {
        return $this->page->message($message, $variables);
    }

    /**
     * The same list and request, at another page
     */
    private function at(Paginator $paginator): self
    {
        return new self($this->page, $this->list, $this->request, $paginator);
    }
}
