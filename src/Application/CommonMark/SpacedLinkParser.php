<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Delimiter\Bracket;
use League\CommonMark\Environment\EnvironmentAwareInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownLinkTargetResolver;

/**
 * Lets `[label](Target With Spaces)` link to a wiki page. CommonMark ends an
 * unbracketed link destination at the first space, so league's own link parser
 * rejects a spaced target; this fills exactly that gap, and only when the target
 * resolves to a wiki page (otherwise it defers to league, which renders the text
 * literally as before). A spaceless target is always left to league.
 *
 * Registered on `]` above league's CloseBracketParser: on a non-match it restores
 * the cursor and returns false so that parser still handles the normal cases
 * (reference links, images, spaceless inline links). On a match it builds the
 * same Link node league would, reusing league's delimiter machinery so the label
 * keeps its inline formatting; the produced node then flows through the ordinary
 * resolve/render path, which registers the link and styles it red/blue.
 */
final class SpacedLinkParser implements InlineParserInterface, EnvironmentAwareInterface {

	/**
	 * Caps the destination scan. This parser runs on every `]`, so an unbounded
	 * scan would let `[](` repeated turn the whole document into O(n^2) work; the
	 * bound keeps it linear. Set well above any valid title (and title fragment),
	 * which MediaWiki limits to 255 bytes, so no real target is ever cut off.
	 */
	private const MAX_TARGET_LENGTH = 1024;

	/**
	 * Set right after construction via setEnvironment(); only read inside parse(),
	 * which league never calls before wiring the environment.
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private EnvironmentInterface $environment;

	public function __construct(
		private readonly MarkdownLinkTargetResolver $linkTargetResolver
	) {
	}

	public function setEnvironment( EnvironmentInterface $environment ): void {
		$this->environment = $environment;
	}

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( ']' );
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$opener = $inlineContext->getDelimiterStack()->getLastBracket();

		if ( $opener === null || $opener->isImage() || !$opener->isActive() ) {
			return false;
		}

		$cursor = $inlineContext->getCursor();
		$previousState = $cursor->saveState();
		$cursor->advanceBy( 1 );

		$target = $this->tryReadSpacedTarget( $cursor );

		if ( $target === null ) {
			$cursor->restoreState( $previousState );
			return false;
		}

		$title = $this->linkTargetResolver->resolve( $target );

		if ( $title === null ) {
			$cursor->restoreState( $previousState );
			return false;
		}

		$link = new Link( $target );
		// Hand the resolved title to the resolve pass so it is not parsed again.
		$link->data->set( MarkdownLinkRenderer::INTERNAL_TITLE_KEY, $title );
		$this->wrapLabelInLink( $opener, $link, $inlineContext );

		return true;
	}

	/**
	 * Reads a space-containing `(target)`, leaving the cursor past the closing `)`.
	 * Returns null (our signal to defer to league) when this is not a spaced inline
	 * link: a non-parenthesised close, or a spaceless target league already handles.
	 */
	private function tryReadSpacedTarget( Cursor $cursor ): ?string {
		if ( $cursor->getCurrentCharacter() !== '(' ) {
			return null;
		}

		$cursor->advanceBy( 1 );
		$cursor->advanceToNextNonSpaceOrNewline();

		$target = $this->readBalancedTarget( $cursor );

		if ( $target === null ) {
			return null;
		}

		// A `Dest "tooltip"` link is ordinary CommonMark league parses correctly, so
		// leave it be. Parenthesised titles stay part of the target, so a
		// disambiguation name like `Mercury (planet)` still links to that page.
		if ( $this->hasQuotedTitle( $target ) ) {
			return null;
		}

		// A spaceless target is one league already handles, so leave those to it.
		if ( !str_contains( $target, ' ' ) ) {
			return null;
		}

		return $target;
	}

	private function hasQuotedTitle( string $target ): bool {
		return preg_match( '/^\S+\s+(?:"[^"]*"|\'[^\']*\')$/', $target ) === 1;
	}

	/**
	 * Collects characters up to the `)` that closes the destination, allowing
	 * balanced inner parens (so a `Foo (disambiguation)` title works). Returns null
	 * if the parens never balance, a newline or a bracket arrives first, or the
	 * length cap is hit -- none of which can be part of a wiki-page target anyway.
	 */
	private function readBalancedTarget( Cursor $cursor ): ?string {
		$target = '';
		$depth = 0;

		while ( !$cursor->isAtEnd() ) {
			$character = (string)$cursor->getCurrentCharacter();

			if ( $character === ')' && $depth === 0 ) {
				$cursor->advanceBy( 1 );
				return trim( $target );
			}

			// `[`/`]` can never be in a title, so stop rather than scan on -- this
			// also stops a run of `[](` from making the whole document O(n^2).
			if ( $this->endsTarget( $character ) || strlen( $target ) >= self::MAX_TARGET_LENGTH ) {
				return null;
			}

			$depth += match ( $character ) {
				'(' => 1,
				')' => -1,
				default => 0,
			};
			$target .= $character;
			$cursor->advanceBy( 1 );
		}

		return null;
	}

	private function endsTarget( string $character ): bool {
		return $character === "\n" || $character === '[' || $character === ']';
	}

	/**
	 * Turns the bracket opener and the inline nodes parsed as its label into a Link,
	 * following league's CloseBracketParser: move the label nodes into the link,
	 * process the emphasis delimiters inside it, and tidy the delimiter stack.
	 */
	private function wrapLabelInLink( Bracket $opener, Link $link, InlineParserContext $inlineContext ): void {
		$opener->getNode()->replaceWith( $link );

		for ( $label = $link->next(); $label !== null; $label = $link->next() ) {
			if ( $label instanceof Link ) {
				// CommonMark forbids links within links; unwrap the inner one.
				foreach ( $label->children() as $child ) {
					$label->insertBefore( $child );
				}
				$label->detach();
			} else {
				$link->appendChild( $label );
			}
		}

		$delimiterStack = $inlineContext->getDelimiterStack();
		$stackBottom = $opener->getPosition();
		$delimiterStack->processDelimiters( $stackBottom, $this->environment->getDelimiterProcessors() );
		$delimiterStack->removeBracket();
		$delimiterStack->removeAll( $stackBottom );
		$delimiterStack->deactivateLinkOpeners();
	}

}
