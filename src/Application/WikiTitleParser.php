<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Port for turning the target text of a wikilink into a validated title.
 */
interface WikiTitleParser {

	/**
	 * @return WikiTitle|null Null when the text is not a valid wiki page title
	 */
	public function parse( string $titleText ): ?WikiTitle;

}
