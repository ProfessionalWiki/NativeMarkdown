<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;
use RuntimeException;

/**
 * Simulates infrastructure failure (e.g. a database error) in the link adapter.
 */
final class ThrowingPageLinkRenderer implements PageLinkRenderer {

	public function preloadExistence( array $titles ): void {
		throw new RuntimeException( 'Database gone away' );
	}

	public function renderLink( WikiTitle $title, string $label ): string {
		throw new RuntimeException( 'Database gone away' );
	}

	public function renderLinkWithHtmlLabel( WikiTitle $title, string $labelHtml ): string {
		throw new RuntimeException( 'Database gone away' );
	}

}
