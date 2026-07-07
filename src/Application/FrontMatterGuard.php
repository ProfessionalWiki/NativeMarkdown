<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Decides whether a document's leading YAML front matter is safe to hand to a
 * YAML parser.
 *
 * A YAML alias bomb ("billion laughs") is a few hundred bytes of aliased YAML
 * that expands exponentially. Parsing stays cheap, but serializing the result
 * (as ParserOutput does when caching it) materializes the full expansion and
 * exhausts memory uncatchably. YAML anchors and aliases are the only mechanism
 * that produces this blow-up, and page metadata never legitimately needs them.
 *
 * The guard inspects only the raw block, never a parsed structure, so its cost
 * is bounded by the input size. It rejects a block that uses more anchors and
 * aliases than any real metadata would, or that is implausibly large. Rejected
 * front matter is treated as absent.
 */
final class FrontMatterGuard {

	/**
	 * league's FrontMatterParser delimiter grammar. Matching it isolates exactly
	 * the bytes league would otherwise feed to the YAML parser.
	 */
	private const FRONT_MATTER_BLOCK = '/^---\R.*?\R---\R/s';

	/**
	 * A YAML anchor (&name) or alias (*name) marker at the start of a node: line
	 * start, or right after whitespace, a flow indicator, or a colon. The colon
	 * matters because Symfony YAML honors an alias placed directly against a
	 * quoted key's colon in a JSON-style flow mapping ({"k":*a}); omitting it lets
	 * such a bomb slip through counted as zero aliases. Requiring a trailing name
	 * character keeps plain scalars such as "R&D" or "a * b" from counting.
	 */
	private const ANCHOR_ALIAS_MARKER = '/(?:^|[\s\[{,:])[&*][^\s\[\]{},]/m';

	/**
	 * Reaching millions of elements takes dozens of aliases (each one multiplies
	 * the element count), so rejecting above a handful stops every bomb with a
	 * wide margin while still tolerating a document that reuses one anchor a few
	 * times.
	 */
	private const MAX_ANCHOR_ALIAS_MARKERS = 8;

	/**
	 * Real metadata is tiny; this generous ceiling only bounds pathological
	 * blocks, capping the parser's work even for anchor-free input.
	 */
	private const MAX_BLOCK_BYTES = 64 * 1024;

	/**
	 * The leading front-matter block that must be discarded as unsafe to parse,
	 * or null when the document has no front matter or it is safe. Returning the
	 * exact matched block lets the caller strip precisely those bytes, so nothing
	 * of a rejected block can leak into the rendered body.
	 */
	public function rejectedBlock( string $markdown ): ?string {
		$block = $this->frontMatterBlock( $markdown );

		if ( $block !== null && $this->isUnsafe( $block ) ) {
			return $block;
		}

		return null;
	}

	private function frontMatterBlock( string $markdown ): ?string {
		if ( preg_match( self::FRONT_MATTER_BLOCK, $markdown, $matches ) === 1 ) {
			return $matches[0];
		}

		return null;
	}

	private function isUnsafe( string $block ): bool {
		return strlen( $block ) > self::MAX_BLOCK_BYTES
			|| $this->anchorAliasMarkerCount( $block ) > self::MAX_ANCHOR_ALIAS_MARKERS;
	}

	private function anchorAliasMarkerCount( string $block ): int {
		return (int)preg_match_all( self::ANCHOR_ALIAS_MARKER, $block );
	}

}
