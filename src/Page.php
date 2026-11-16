<?php

namespace MiniPaviService;

/**
 * A screen of the service: what it shows on the Minitel, and how it reads what
 * the user types on it.
 */
interface Page {

	public function display(array $data = []): string;

	public function readInput(array $prefill = []): ?array;
}
