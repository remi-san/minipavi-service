<?php

namespace MiniPaviService\Page\Template;

use MiniPaviService\Page\Parser\{ElementFactory, PageXmlException};

/**
 * Display element of a template (<text>, <line>, <key>, <endkey>, <background>, <image>),
 * whose text and attributes may contain {{placeholders}}
 */
final readonly class ElementNode implements Node {

	/**
	 * @param array<string, string> $attributes name => value, as in the XML file
	 * @param string $location file and line, for error messages
	 */
	public function __construct(
		private ElementFactory $factory,
		private string $tag,
		private array $attributes,
		private string $text,
		private string $location,
	) {
	}

	public function elements(Scope $scope): array {
		try {
			$attributes = array_map(fn(string $value) => $scope->substitute($value),$this->attributes);
			// Inside a <foreach>, row is relative to the current item (0 = its first row)
			if ($scope->rowOffset !== null && $this->tag != 'background')
				$attributes['row'] = (string)($scope->rowOffset+$this->factory->int($attributes,'row',0));
			return array($this->factory->element($this->tag,$attributes,$scope->substitute($this->text)));
		} catch (PageXmlException|\InvalidArgumentException $e) {
			throw new TemplateException("$this->location: <$this->tag>: ".$e->getMessage(),0,$e);
		}
	}
}
