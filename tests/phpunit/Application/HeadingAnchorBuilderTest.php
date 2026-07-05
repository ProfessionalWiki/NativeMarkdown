<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\HeadingAnchorBuilder;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\HeadingAnchorBuilder
 */
class HeadingAnchorBuilderTest extends TestCase {

	public function testSpacesBecomeUnderscores(): void {
		$this->assertSame(
			'My_Section_Name',
			( new HeadingAnchorBuilder() )->buildAnchor( 'My Section Name' )
		);
	}

	public function testSurroundingWhitespaceIsTrimmedAndInnerRunsCollapse(): void {
		$this->assertSame(
			'a_b',
			( new HeadingAnchorBuilder() )->buildAnchor( "  a \t  b  " )
		);
	}

	public function testDuplicateAnchorsGetNumericSuffixesCaseInsensitively(): void {
		$builder = new HeadingAnchorBuilder();

		$this->assertSame( 'Same', $builder->buildAnchor( 'Same' ) );
		$this->assertSame( 'same_2', $builder->buildAnchor( 'same' ) );
		$this->assertSame( 'Same_3', $builder->buildAnchor( 'Same' ) );
	}

	public function testEmptyTextGivesEmptyAnchor(): void {
		$this->assertSame( '', ( new HeadingAnchorBuilder() )->buildAnchor( '   ' ) );
	}

	public function testUnderscoreRunsCollapseLikeCoreSectionAnchors(): void {
		$this->assertSame(
			'a_b',
			( new HeadingAnchorBuilder() )->buildAnchor( 'a _ b' )
		);
	}

	public function testLongAnchorIsCappedAtCoreLimitOf1024Characters(): void {
		$anchor = ( new HeadingAnchorBuilder() )->buildAnchor( str_repeat( 'a', 2000 ) );

		$this->assertSame( str_repeat( 'a', 1024 ), $anchor );
	}

	public function testMultibyteAnchorIsCappedByCharacterNotByte(): void {
		$anchor = ( new HeadingAnchorBuilder() )->buildAnchor( str_repeat( 'é', 2000 ) );

		$this->assertSame( 1024, mb_strlen( $anchor ) );
	}

}
