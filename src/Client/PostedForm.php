<?php

declare(strict_types=1);

namespace MiniPaviService\Client;

use MiniPaviService\Minitel\FunctionKey;

/**
 * What the page of HtmlClient posted back (HtmlForm::read()): the key pressed, the lines typed,
 * the context of the answer it showed, and that page as it was
 */
final readonly class PostedForm
{
    /**
     * @param list<string> $content lines typed, one per input of the field
     * @param string $context context of the answer the page showed, as it was given
     */
    public function __construct(
        public FunctionKey $key,
        public array $content,
        public string $context,
        public HtmlPageState $page,
    ) {}
}
