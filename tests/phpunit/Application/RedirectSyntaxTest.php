<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\RedirectSyntax;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\RedirectSyntax
 */
class RedirectSyntaxTest extends TestCase {

	private function englishSyntax(): RedirectSyntax {
		return new RedirectSyntax( magicWordSynonyms: [ '#REDIRECT' ] );
	}

	public function testExtractsTargetFollowingMagicWord(): void {
		$this->assertSame( 'Target Page', $this->englishSyntax()->extractTargetText( '#REDIRECT [[Target Page]]' ) );
	}

	public function testHasNoTargetWithoutMagicWord(): void {
		$this->assertNull( $this->englishSyntax()->extractTargetText( '[[Target Page]]' ) );
	}

	public function testHasNoTargetInPlainText(): void {
		$this->assertNull( $this->englishSyntax()->extractTargetText( 'Just some prose with a [link](https://example.com).' ) );
	}

	public function testMatchesMagicWordCaseInsensitively(): void {
		$this->assertSame( 'Target Page', $this->englishSyntax()->extractTargetText( '#redirect [[Target Page]]' ) );
	}

	public function testIgnoresLeadingWhitespaceBeforeMagicWord(): void {
		$this->assertSame( 'Target Page', $this->englishSyntax()->extractTargetText( "\n\n   #REDIRECT [[Target Page]]" ) );
	}

	public function testRecognizesAnyLocalizedSynonym(): void {
		$syntax = new RedirectSyntax( magicWordSynonyms: [ '#REDIRECT', '#WEITERLEITUNG' ] );

		$this->assertSame( 'Ziel', $syntax->extractTargetText( '#WEITERLEITUNG [[Ziel]]' ) );
	}

	public function testDropsPipedLabelFromTarget(): void {
		$this->assertSame( 'Target Page', $this->englishSyntax()->extractTargetText( '#REDIRECT [[Target Page|see over here]]' ) );
	}

	public function testKeepsLeadingColonEscapeInTarget(): void {
		$this->assertSame( ':Category:Guides', $this->englishSyntax()->extractTargetText( '#REDIRECT [[:Category:Guides]]' ) );
	}

	public function testUrlDecodesPercentEncodedTarget(): void {
		$this->assertSame( 'Foo Bar', $this->englishSyntax()->extractTargetText( '#REDIRECT [[Foo%20Bar]]' ) );
	}

	public function testKeepsSectionFragmentInTarget(): void {
		$this->assertSame( 'Target Page#History', $this->englishSyntax()->extractTargetText( '#REDIRECT [[Target Page#History]]' ) );
	}

	public function testHasNoTargetWhenNoLinkFollowsMagicWord(): void {
		$this->assertNull( $this->englishSyntax()->extractTargetText( '#REDIRECT nowhere in particular' ) );
	}

	public function testExtractsTrailingContentAfterRedirectLine(): void {
		$this->assertSame(
			'[[Category:Redirects]]',
			$this->englishSyntax()->extractTrailingContent( "#REDIRECT [[Target Page]]\n\n[[Category:Redirects]]" )
		);
	}

	public function testKeepsTrailingContentAfterPipedRedirect(): void {
		$this->assertSame(
			'Trailing prose.',
			$this->englishSyntax()->extractTrailingContent( "#REDIRECT [[Target Page|label]]\n\nTrailing prose." )
		);
	}

	public function testHasNoTrailingContentForBareRedirect(): void {
		$this->assertSame( '', $this->englishSyntax()->extractTrailingContent( '#REDIRECT [[Target Page]]' ) );
	}

	public function testHasNoTrailingContentWithoutMagicWord(): void {
		$this->assertSame( '', $this->englishSyntax()->extractTrailingContent( '[[Target Page]]' ) );
	}

	public function testBuildsRedirectUsingPreferredSynonym(): void {
		$syntax = new RedirectSyntax( magicWordSynonyms: [ '#WEITERLEITUNG', '#REDIRECT' ] );

		$this->assertSame( '#WEITERLEITUNG [[Target Page]]', $syntax->buildRedirectText( 'Target Page' ) );
	}

	public function testBuildsRedirectWithColonEscapedTarget(): void {
		$this->assertSame( '#REDIRECT [[:Category:Guides]]', $this->englishSyntax()->buildRedirectText( ':Category:Guides' ) );
	}

}
