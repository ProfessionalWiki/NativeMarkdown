<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Reads and writes the MediaWiki redirect syntax (`#REDIRECT [[Target]]`) on a
 * markdown page. The `redirect` magic word is localized, so its synonyms are
 * injected; the first synonym is the content language's preferred spelling.
 *
 * This mirrors how the wikitext content handler treats redirects, so a page
 * move (which writes this syntax) leaves a working redirect and hand-written
 * redirects behave the same across content models.
 */
final class RedirectSyntax {

	/**
	 * @param string[] $magicWordSynonyms Redirect magic word spellings, preferred one first
	 */
	public function __construct(
		private readonly array $magicWordSynonyms
	) {
	}

	/**
	 * The raw target text of the redirect, or null when the page is not a
	 * redirect. Turning that text into a validated title is the caller's job,
	 * since redirect-target validation is a wiki concern.
	 */
	public function extractTargetText( string $pageText ): ?string {
		if ( $this->magicWordSynonyms === [] ) {
			return null;
		}

		$afterMagicWord = preg_replace( $this->magicWordRegex(), '', ltrim( $pageText ), 1, $count );

		if ( $count === 0 || $afterMagicWord === null ) {
			return null;
		}

		// Mirrors the first-link extraction of WikitextContentHandler: a leading
		// colon and a piped label are both allowed and dropped.
		if ( preg_match( '!^\s*:?\s*\[{2}(.*?)(?:\|.*?)?\]{2}\s*!', $afterMagicWord, $matches ) !== 1 ) {
			return null;
		}

		return $this->decodeTarget( $matches[1] );
	}

	private function decodeTarget( string $target ): string {
		if ( str_contains( $target, '%' ) ) {
			return rawurldecode( ltrim( $target, ':' ) );
		}

		return $target;
	}

	/**
	 * Builds the redirect line pointing at the given target text. The caller
	 * supplies any leading colon needed to escape category or interlanguage
	 * targets, matching the wikitext handler.
	 */
	public function buildRedirectText( string $target ): string {
		return $this->preferredSynonym() . ' [[' . $target . ']]';
	}

	/**
	 * The content language's preferred spelling of the redirect magic word.
	 * MediaWiki always defines at least the English `#REDIRECT`, the fallback
	 * used should the injected synonym list ever be empty.
	 */
	private function preferredSynonym(): string {
		return $this->magicWordSynonyms[0] ?? '#REDIRECT';
	}

	/**
	 * The redirect magic word is always case-insensitive, so this matches any
	 * synonym at the very start of the page. Longest synonyms come first, as in
	 * MediaWiki's own magic word matching, so one is never a prefix of another.
	 */
	private function magicWordRegex(): string {
		$synonyms = $this->magicWordSynonyms;
		usort( $synonyms, static fn ( string $a, string $b ) => strlen( $b ) <=> strlen( $a ) );

		$alternatives = implode( '|', array_map(
			static fn ( string $synonym ) => preg_quote( $synonym, '/' ),
			$synonyms
		) );

		return '/^(?:' . $alternatives . ')/iu';
	}

}
