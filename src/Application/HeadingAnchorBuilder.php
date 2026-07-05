<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Builds MediaWiki-style heading anchors: spaces become underscores and
 * duplicates get numeric suffixes. One instance tracks anchors for one document.
 */
final class HeadingAnchorBuilder {

	// Core caps ids at 1024 characters; see Sanitizer::escapeIdForAttribute().
	private const MAX_ANCHOR_LENGTH = 1024;

	/**
	 * @var array<string, true> Lowercased anchors already handed out
	 */
	private array $usedAnchors = [];

	public function buildAnchor( string $headingText ): string {
		// Core folds space and underscore runs together; see Sanitizer::normalizeSectionNameWhitespace().
		$anchor = str_replace( ' ', '_', trim( preg_replace( '/[\s_]+/u', ' ', $headingText ) ?? '' ) );

		if ( $anchor === '' ) {
			return '';
		}

		return $this->deduplicate( mb_substr( $anchor, 0, self::MAX_ANCHOR_LENGTH ) );
	}

	private function deduplicate( string $anchor ): string {
		$candidate = $anchor;
		$suffix = 1;

		while ( array_key_exists( strtolower( $candidate ), $this->usedAnchors ) ) {
			$suffix++;
			$candidate = $anchor . '_' . $suffix;
		}

		$this->usedAnchors[strtolower( $candidate )] = true;

		return $candidate;
	}

}
