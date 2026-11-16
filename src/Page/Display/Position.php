<?php

namespace MiniPaviService\Page\Display;

/**
 * Bounds of the screen, shared by the elements
 */
final class Position {

	public const ROWS = 24;
	public const COLS = 40;

	/**
	 * Throws if row (0 to 24, 0 = status line) or col (1 to 40) is off the screen
	 */
	public static function check(int $row, int $col): void {
		if ($row < 0 || $row > self::ROWS)
			throw new \InvalidArgumentException("Row $row is off the screen (0 to ".self::ROWS.")");
		if ($col < 1 || $col > self::COLS)
			throw new \InvalidArgumentException("Column $col is off the screen (1 to ".self::COLS.")");
	}
}
