<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * Counts renderLink calls so tests can assert link HTML building was skipped.
 */
final class SpyPageLinkRenderer implements PageLinkRenderer {

	public int $renderedLinkCount = 0;

	public function preloadExistence( array $titles ): void {
	}

	public function renderLink( WikiTitle $title, string $label ): string {
		$this->renderedLinkCount++;

		return '<a>' . htmlspecialchars( $label, ENT_QUOTES ) . '</a>';
	}

}
