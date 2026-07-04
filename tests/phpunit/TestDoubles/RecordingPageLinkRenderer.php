<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * Records the order of port calls so tests can assert existence preloading
 * happens, with which titles, and before any link is rendered.
 */
final class RecordingPageLinkRenderer implements PageLinkRenderer {

	/**
	 * @var string[] Call log entries like "preload:Some Page,Other Page" and "render:Some Page"
	 */
	public array $calls = [];

	public function preloadExistence( array $titles ): void {
		$this->calls[] = 'preload:' . implode(
			',',
			array_map( static fn ( WikiTitle $title ) => $title->prefixedText, $titles )
		);
	}

	public function renderLink( WikiTitle $title, string $label ): string {
		$this->calls[] = 'render:' . $title->textWithFragment();

		return '<a>' . htmlspecialchars( $label, ENT_QUOTES ) . '</a>';
	}

}
