<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Port for rendering an embedded wiki file as HTML, including
 * the missing-file case (upload link).
 */
interface FileEmbedRenderer {

	/**
	 * Warms up file information for the given titles in one batch, so
	 * subsequent renderEmbed() calls do not each hit the database.
	 *
	 * @param WikiTitle[] $titles
	 */
	public function preloadFiles( array $titles ): void;

	/**
	 * @return string Safe HTML
	 */
	public function renderEmbed( FileEmbed $embed ): string;

}
