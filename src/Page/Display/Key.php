<?php

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;
use MiniPaviService\Vdt\VdtHelper;

/**
 * Function key badge, e.g. " Envoi " in reverse video (<key> in the page XML format)
 */
final readonly class Key implements Element {

	public function __construct(
		public string $label,
		public int $row,
		public int $col = 1,
		public Color $color = Color::Green,
		public ?Color $bg = null,
	) {
		Position::check($row,$col);
	}

	/*****************
	*** bg: background around the badge, e.g. to draw it on a colored bar; it applies
	*** from the badge's first character, a space
	******************/

	public function getContent(MiniPaviClient $client): string {
		return VdtHelper::setPos($this->col,$this->row).($this->bg?->background() ?? '').self::badge($client,$this->label,$this->color);
	}

	/*****************
	*** Badge of a key, at the cursor position (also used by EndKey)
	******************/

	public static function badge(MiniPaviClient $client, string $label, Color $color): string {
		return VDT_TXTWHITE.VDT_STARTUNDERLINE.' '.$color->text().VDT_FDINV.VdtHelper::toG2(' '.$label.' ').VDT_TXTWHITE.VDT_FDNORM.VDT_STOPUNDERLINE.' ';
	}
}
