<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Starts a block-level template call when a line begins with `{{` (an infobox
 * or navbox on its own line). A call that closes on the same line with trailing
 * text is left for the inline parser; `{{{` argument syntax is left literal.
 * The call may span multiple lines, including blank ones, until the braces
 * balance; TemplateCallBlockParser accumulates those lines.
 */
final class TemplateCallBlockStartParser implements BlockStartParserInterface {

	public function tryStart( Cursor $cursor, MarkdownParserStateInterface $parserState ): ?BlockStart {
		if ( $cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '{' ) {
			return BlockStart::none();
		}

		$line = $cursor->getRemainder();

		if ( preg_match( '/^[ \t]*\{\{(?!\{)/', $line ) !== 1 ) {
			return BlockStart::none();
		}

		if ( $this->closesInlineOnFirstLine( $line ) ) {
			return BlockStart::none();
		}

		return BlockStart::of( new TemplateCallBlockParser() )->at( $cursor );
	}

	/**
	 * True when the braces balance within this line and non-space text follows
	 * the closing `}}`, meaning the call belongs inline rather than as a block.
	 */
	private function closesInlineOnFirstLine( string $line ): bool {
		$fromBraces = ltrim( $line, " \t" );
		$call = TemplateBraces::matchLeadingCall( $fromBraces );

		return $call !== null && trim( substr( $fromBraces, strlen( $call ) ) ) !== '';
	}

}
