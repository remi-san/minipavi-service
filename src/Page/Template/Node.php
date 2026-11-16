<?php

namespace MiniPaviService\Page\Template;

/**
 * Part of a template: a display element, or an instruction (<foreach>, <if>)
 */
interface Node {

	/**
	 * Display elements of this part for the variables of the scope.
	 *
	 * @return \MiniPaviService\Page\Display\Element[]
	 * @throws TemplateException if it cannot be displayed
	 */
	public function elements(Scope $scope): array;
}
