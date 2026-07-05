<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * A section created by a markdown heading, used to build the table of contents.
 */
final class Section {

	public function __construct(
		public readonly int $level,
		public readonly string $text,
		public readonly string $anchor
	) {
	}

}
