<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Decides if a new page should default to the markdown content model,
 * based on the wiki's activation configuration.
 */
final class MarkdownDefaultPolicy {

	/**
	 * @param int[] $namespaces
	 */
	public function __construct(
		private readonly array $namespaces,
		private readonly bool $everywhere,
		private readonly bool $suffixDetection
	) {
	}

	/**
	 * Defaults apply to page creation only: existing pages (including imports
	 * and undeletions of pages that already have revisions) keep their model.
	 */
	public function appliesTo( int $namespace, bool $isContentNamespace, string $titleText, bool $pageExists ): bool {
		if ( $pageExists || $this->isCodePageTitle( $titleText ) ) {
			return false;
		}

		return in_array( $namespace, $this->namespaces, true )
			|| ( $this->everywhere && $isContentNamespace )
			|| ( $this->suffixDetection && str_ends_with( $titleText, '.md' ) );
	}

	/**
	 * Titles core treats as CSS/JS/JSON pages (site and user code pages) must
	 * keep their code content model; see MainSlotRoleHandler::getDefaultModel().
	 */
	private function isCodePageTitle( string $titleText ): bool {
		return preg_match( '/\.(css|js|json)$/u', $titleText ) === 1;
	}

}
