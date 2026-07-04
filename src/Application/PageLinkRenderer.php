<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Port for rendering an internal page link as HTML, including
 * existence styling (blue versus red links).
 */
interface PageLinkRenderer {

	/**
	 * Warms up existence information for the given local pages in one batch,
	 * so subsequent renderLink() calls do not each hit the database.
	 *
	 * @param WikiTitle[] $titles
	 */
	public function preloadExistence( array $titles ): void;

	/**
	 * @param string $label Plain text label. Implementations are responsible for escaping.
	 * @return string Safe HTML
	 */
	public function renderLink( WikiTitle $title, string $label ): string;

}
