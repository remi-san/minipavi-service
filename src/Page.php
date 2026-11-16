<?php

declare(strict_types=1);

namespace MiniPaviService;

/**
 * A screen of the service: what it shows on the Minitel, and how it reads what
 * the user types on it.
 */
interface Page
{
    /**
     * @param array<string, mixed> $data variables of a template
     */
    public function display(array $data = []): string;

    /**
     * @param string[] $prefill text of the input, one string per line
     * @return ?array<string, mixed> MiniPavi command reading the input, null without input
     */
    public function readInput(array $prefill = []): ?array;
}
