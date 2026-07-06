<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Inline parser for `{{...}}` template calls that sit within a line of text.
 * Captures the balanced brace span, handing nested calls and arguments to the
 * expander untouched. Own-line block calls are handled by
 * TemplateCallBlockStartParser; multi-line spans and `{{{...}}}` argument
 * syntax fall through to literal text.
 */
final class TemplateCallParser implements InlineParserInterface {

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( '{{' );
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$cursor = $inlineContext->getCursor();

		// A preceding brace means we are inside a `{{{...}}}` run: leave it literal.
		if ( $cursor->peek( -1 ) === '{' ) {
			return false;
		}

		$wikitext = TemplateBraces::matchLeadingCall( $cursor->getRemainder() );

		if ( $wikitext === null ) {
			return false;
		}

		// advanceBy() counts characters, not bytes, so byte offsets must be
		// converted, as in WikiLinkParser.
		$cursor->advanceBy( mb_strlen( $wikitext, 'UTF-8' ) );
		$inlineContext->getContainer()->appendChild( new TemplateCallNode( $wikitext ) );

		return true;
	}

}
