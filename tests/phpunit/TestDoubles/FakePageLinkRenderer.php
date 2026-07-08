<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * Renders links in a deterministic shape so tests can assert exact pipeline output
 * without depending on MediaWiki's LinkRenderer HTML.
 */
final class FakePageLinkRenderer implements PageLinkRenderer {

	public function preloadExistence( array $titles ): void {
	}

	public function renderLink( WikiTitle $title, string $label ): string {
		return '<a href="' . htmlspecialchars( $this->hrefFor( $title ), ENT_QUOTES )
			. '">' . htmlspecialchars( $label, ENT_QUOTES ) . '</a>';
	}

	public function renderLinkWithHtmlLabel( WikiTitle $title, string $labelHtml ): string {
		return '<a href="' . htmlspecialchars( $this->hrefFor( $title ), ENT_QUOTES )
			. '">' . $labelHtml . '</a>';
	}

	private function hrefFor( WikiTitle $title ): string {
		if ( $title->isSamePageAnchor() ) {
			return '#' . $title->fragment;
		}

		return '/wiki/' . $title->textWithFragment();
	}

}
