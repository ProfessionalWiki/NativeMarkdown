<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use MediaWiki\Parser\Sanitizer;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\Application\HeadingAnchorBuilder;

/**
 * Our heading anchors must equal the id core's wikitext parser gives the same
 * heading, so a page's table of contents and its `[[#Heading]]` links resolve
 * exactly as they do for wikitext pages. Core builds that id by collapsing
 * section whitespace and then escaping it, so we compare against that
 * authoritative function composition rather than re-deriving expected values.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\Application\HeadingAnchorBuilder
 */
class HeadingAnchorParityTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider headingTextProvider
	 */
	public function testAnchorMatchesCoreWikitextHeadingId( string $headingText ): void {
		$this->assertSame(
			Sanitizer::escapeIdForAttribute( Sanitizer::normalizeSectionNameWhitespace( $headingText ) ),
			( new HeadingAnchorBuilder() )->buildAnchor( $headingText )
		);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function headingTextProvider(): iterable {
		yield 'plain words' => [ 'Simple Heading' ];
		yield 'punctuation' => [ 'Hello, World!' ];
		yield 'percent' => [ '50% Off Sale' ];
		yield 'symbols' => [ 'C++ and C#' ];
		yield 'slash' => [ 'A/B testing' ];
		yield 'collapsing spaces' => [ 'Spaces   collapsed' ];
		yield 'accented' => [ 'café RÉSUMÉ' ];
		yield 'arabic rtl' => [ 'مرحبا بالعالم' ];
		yield 'emoji' => [ 'Hello 🌍 World' ];
		yield 'question mark' => [ 'Question?' ];
		yield 'ampersand' => [ 'Ampersand & Co' ];
		yield 'quotes' => [ 'Quote "here"' ];
		yield 'dots' => [ 'Dots...end' ];
		yield 'angle brackets' => [ 'Math plain text' ];
		yield 'many spaces' => [ 'a  b   c' ];
		yield 'long heading' => [ str_repeat( 'word ', 300 ) ];
	}

}
