<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Decides if a new page should default to the markdown content model,
 * based on the wiki's activation configuration.
 */
final class MarkdownDefaultPolicy {

	// Fixed core namespace IDs (Defines.php), stable across all MediaWiki installs.
	private const MEDIAWIKI_NAMESPACE = 8;
	private const TEMPLATE_NAMESPACE = 10;

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
	public function appliesTo( int $namespace, bool $isTalkNamespace, string $titleText, bool $pageExists ): bool {
		if ( $pageExists || $this->isCodePageTitle( $titleText ) ) {
			return false;
		}

		return in_array( $namespace, $this->namespaces, true )
			|| ( $this->everywhere && $this->isEverywhereNamespace( $namespace, $isTalkNamespace ) )
			|| ( $this->suffixDetection && str_ends_with( $titleText, '.md' ) && $this->markdownCanServeNamespace( $namespace ) );
	}

	/**
	 * "Everywhere" mode covers the whole prose wiki. It leaves discussion namespaces as
	 * wikitext: a blanket switch must not break the signatures and threading that Talk pages
	 * rely on. Namespaces Markdown cannot serve are left out too.
	 */
	private function isEverywhereNamespace( int $namespace, bool $isTalkNamespace ): bool {
		return !$isTalkNamespace && $this->markdownCanServeNamespace( $namespace );
	}

	/**
	 * Template and MediaWiki pages are consumed by the software as wikitext: transclusion
	 * with {{{parameters}}}, and interface messages. A Markdown page cannot fill either role,
	 * so even an explicit .md suffix does not default it to Markdown there. Only the namespace
	 * allow-list, a deliberate per-namespace choice, opts these in.
	 */
	private function markdownCanServeNamespace( int $namespace ): bool {
		return $namespace !== self::TEMPLATE_NAMESPACE
			&& $namespace !== self::MEDIAWIKI_NAMESPACE;
	}

	/**
	 * Titles core treats as CSS/JS/JSON pages (site and user code pages) must
	 * keep their code content model; see MainSlotRoleHandler::getDefaultModel().
	 */
	private function isCodePageTitle( string $titleText ): bool {
		return preg_match( '/\.(css|js|json)$/u', $titleText ) === 1;
	}

}
