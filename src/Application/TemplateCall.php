<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * A `{{...}}` call found on a markdown page, carrying the raw wikitext to expand
 * (braces included) and whether it stands as its own block or sits inline in text.
 * The wikitext is opaque here: only the TemplateExpander port interprets it.
 */
final class TemplateCall {

	public function __construct(
		public readonly string $wikitext,
		public readonly bool $isBlock
	) {
	}

}
