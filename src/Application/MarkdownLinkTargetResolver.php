<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Decides whether the target of a standard markdown `[label](target)` link names
 * a wiki page rather than a URL to emit verbatim, and if so resolves it to a
 * title. Mirrors how wikitext tells an internal target from an external one: not
 * an external-protocol URL (so `Help:X` is a title but `mailto:x` is not), not a
 * script scheme, and not a fragment or absolute/relative path reference.
 *
 * Shared by the resolve pass (which registers and renders the link) and the
 * inline parser that recognises space-containing targets, so both agree on which
 * targets are wiki links.
 */
final class MarkdownLinkTargetResolver {

	public function __construct(
		private readonly WikiTitleParser $titleParser,
		private readonly ExternalUrlDetector $externalUrlDetector
	) {
	}

	public function resolve( string $target ): ?WikiTitle {
		if ( !$this->pointsToWikiPage( $target ) ) {
			return null;
		}

		return $this->titleParser->parse( $target );
	}

	private function pointsToWikiPage( string $target ): bool {
		return $target !== ''
			&& !str_starts_with( $target, '#' )
			&& !str_starts_with( $target, '/' )
			&& !$this->externalUrlDetector->isExternalUrl( $target )
			&& !$this->hasScriptScheme( $target );
	}

	/**
	 * A script scheme is kept out of links (deferred to league, which strips it).
	 * The match is anchored to the start, so a title merely containing the word
	 * (`Metadata:X`, `Big data: ...`) is not mistaken for a URL, and `file:` is
	 * intentionally absent so the core `File:` namespace resolves normally. The one
	 * edge is a wiki that defines a `Data:` namespace, whose pages are then
	 * deferred; that scheme/namespace clash is inherent (wikitext has it too), and
	 * any target that does resolve renders a safe local href regardless.
	 */
	private function hasScriptScheme( string $target ): bool {
		return preg_match( '/^\s*(?:javascript|vbscript|data):/i', $target ) === 1;
	}

}
