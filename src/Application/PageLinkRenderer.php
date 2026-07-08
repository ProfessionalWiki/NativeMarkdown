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

	/**
	 * Like renderLink(), but for a label that is already safe HTML rather than
	 * plain text. Markdown `[label](Target)` links use this because their label
	 * may contain inline formatting (bold, code, ...) the plain-text path escapes.
	 *
	 * @param string $labelHtml Safe HTML for the link label.
	 * @return string Safe HTML
	 */
	public function renderLinkWithHtmlLabel( WikiTitle $title, string $labelHtml ): string;

}
