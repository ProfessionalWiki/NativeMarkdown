<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Inline parser for `[[Target]]`, `[[Target|Label]]` and `[[:Target]]` syntax.
 */
final class WikiLinkParser implements InlineParserInterface {

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( '[[' );
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$cursor = $inlineContext->getCursor();
		$remainder = $cursor->getRemainder();

		$closing = strpos( $remainder, ']]', 2 );

		if ( $closing === false ) {
			return false;
		}

		$inner = substr( $remainder, 2, $closing - 2 );

		if ( !$this->isUsableLinkText( $inner ) ) {
			return false;
		}

		// advanceBy() counts characters, not bytes, so byte offsets from strpos() must be converted.
		$cursor->advanceBy( mb_strlen( substr( $remainder, 0, $closing + 2 ), 'UTF-8' ) );
		$inlineContext->getContainer()->appendChild( $this->newNode( $inner ) );

		return true;
	}

	private function isUsableLinkText( string $inner ): bool {
		return trim( $inner, ': ' ) !== ''
			&& !str_contains( $inner, "\n" )
			&& !str_contains( $inner, '[[' );
	}

	private function newNode( string $inner ): WikiLinkNode {
		$hasLeadingColon = str_starts_with( $inner, ':' );
		$linkText = $hasLeadingColon ? substr( $inner, 1 ) : $inner;

		[ $target, $label ] = $this->splitLabel( $linkText );

		return new WikiLinkNode(
			target: $target,
			label: $label,
			hasLeadingColon: $hasLeadingColon,
			rawSource: '[[' . $inner . ']]'
		);
	}

	/**
	 * @return array{0: string, 1: string|null}
	 */
	private function splitLabel( string $linkText ): array {
		$parts = explode( '|', $linkText, 2 );

		return [ trim( $parts[0] ), $parts[1] ?? null ];
	}

}
