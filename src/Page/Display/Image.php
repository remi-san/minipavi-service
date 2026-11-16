<?php

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Vdt\Vdt;

/**
 * Videotex picture (e.g. made with a Videotex editor) drawn with its top-left cell, row 1
 * column 1 of the file, at row and col (<image> in the page XML format). What goes past the
 * edges of the screen is cut, and the picture doesn't clear the screen: it is drawn over
 * what is already there.
 */
final readonly class Image implements Element {

	// The picture, moved to its place
	private string $vdt;

	public function __construct(
		public Vdt $image,
		public int $row = 1,
		public int $col = 1,
	) {
		Position::check($row,$col);
		if ($row < 1)
			throw new \InvalidArgumentException('An image starts at row 1 at least');
		$this->vdt = $image->withoutClear()->offset($col-1,$row-1)->toString();
	}

	public function getContent(MiniPaviClient $client): string {
		return $this->vdt;
	}
}
