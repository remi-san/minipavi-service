<?php

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;

/**
 * Videotex content sent as is, e.g. a page made with a Videotex editor
 * (<background> in the page XML format). Put it first to draw over it.
 */
final readonly class Background implements Element {

	public function __construct(
		public string $vdt,
	) {
	}

	public function getContent(MiniPaviClient $client): string {
		return $this->vdt;
	}

	/*****************
	*** Background read from a .vdt file
	******************/

	public static function fromFile(string $file): self {
		$vdt = @file_get_contents($file);
		if ($vdt === false)
			throw new \InvalidArgumentException("Videotex file not found: $file");
		return new self($vdt);
	}
}
