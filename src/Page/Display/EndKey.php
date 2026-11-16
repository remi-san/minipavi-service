<?php

namespace MiniPaviService\Page\Display;

use MiniPaviService\MiniPaviClient;

/**
 * "Connexion/fin" hint, telling how to come back to this screen (<endkey> in
 * the page XML format). ESP32/Minimit adapters need "[Maj] +" before the key:
 * shown on row 23 for them, on row 24 otherwise.
 */
final readonly class EndKey implements Element {

	public function __construct(
		public ?int $row = null,
		public Color $color = Color::Green,
	) {
		if ($row !== null)
			Position::check($row,1);
	}

	// The kind of client is known from the current request to the service
	public function getContent(MiniPaviClient $client): string {
		if ($client->versionMinitel() != '???' && $client->typeSocket() == 'websocket')
			return $client->setPos(9,$this->row ?? 23).VDT_TXTWHITE.'[Maj] +'.Key::badge($client,'Connexion/fin',$this->color);
		return $client->setPos(12,$this->row ?? 24).Key::badge($client,'Connexion/fin',$this->color);
	}
}
