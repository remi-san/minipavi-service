<?php

namespace MiniPaviService\Page\Template;

/**
 * Part of a template: a display element, or an instruction (<foreach>, <if>)
 */
interface Node {

	/*****************
	*** Display elements of this part for the variables of the scope
	*** Throws TemplateException if it cannot be displayed
	******************/

	/** @return \MiniPaviService\Page\Display\Element[] */
	public function elements(Scope $scope): array;
}
