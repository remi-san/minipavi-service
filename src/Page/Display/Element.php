<?php

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;

/**
 * Something drawn on the screen by a Display. The screen is 40 columns x 24 rows,
 * row 0 being the status line.
 */
interface Element {

	/**
	 * Videotex content drawing the element; client gives the Videotex helpers, and the
	 * current request for elements depending on the user's terminal
	 */
	public function getContent(MiniPaviClient $client): string;
}
