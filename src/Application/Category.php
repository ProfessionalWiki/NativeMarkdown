<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * A category assignment made by the page, such as via `[[Category:Example]]`.
 */
final class Category {

	public function __construct(
		public readonly WikiTitle $title,
		public readonly string $sortKey
	) {
	}

}
