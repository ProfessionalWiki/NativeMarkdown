<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Persistence;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\Persistence\SyntaxHighlightCodeHighlighter;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Persistence\SyntaxHighlightCodeHighlighter
 */
class SyntaxHighlightCodeHighlighterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' ) ) {
			$this->markTestSkipped( 'Extension:SyntaxHighlight is not installed' );
		}
	}

	private function highlight( string $code, string $language ): ?string {
		return ( new SyntaxHighlightCodeHighlighter() )->highlight( $code, $language );
	}

	public function testKnownLanguageIsHighlightedIntoPygmentsMarkup(): void {
		$html = $this->highlight( 'print("hi")', 'python' );

		$this->assertNotNull( $html );
		$this->assertStringContainsString( 'mw-highlight-lang-python', $html );
		$this->assertStringContainsString( '<span', $html );
	}

	public function testUnknownLanguageIsNotHighlighted(): void {
		$this->assertNull( $this->highlight( 'print("hi")', 'definitelynotalanguage' ) );
	}

	public function testEmptyCodeIsNotHighlighted(): void {
		$this->assertNull( $this->highlight( '', 'python' ) );
	}

}
