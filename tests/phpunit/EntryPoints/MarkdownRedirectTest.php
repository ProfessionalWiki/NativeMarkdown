<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler;

/**
 * Redirect support for markdown pages: the `#REDIRECT [[Target]]` syntax is
 * detected, rendered as MediaWiki's redirect view, and reported to the rest of
 * the wiki (page moves, WhatLinksHere, the redirect table).
 *
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent
 * @covers \ProfessionalWiki\NativeMarkdown\Application\RedirectSyntax
 * @covers \ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension
 * @group Database
 */
class MarkdownRedirectTest extends MediaWikiIntegrationTestCase {

	private function handler(): MarkdownContentHandler {
		$handler = $this->getServiceContainer()->getContentHandlerFactory()->getContentHandler( 'markdown' );
		$this->assertInstanceOf( MarkdownContentHandler::class, $handler );

		return $handler;
	}

	private function getParserOutput( string $markdown ): ParserOutput {
		return $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new MarkdownContent( $markdown ),
			PageReferenceValue::localReference( NS_MAIN, 'NativeMarkdownRedirectPage' )
		);
	}

	public function testRedirectPageRendersRedirectViewToTarget(): void {
		$header = $this->getParserOutput( '#REDIRECT [[Redirect View Target]]' )->getRedirectHeader();

		$this->assertNotNull( $header );
		$this->assertStringContainsString( 'redirectMsg', $header );
		$this->assertStringContainsString( 'Redirect View Target', $header );
	}

	public function testRedirectPageRegistersTargetInPageLinks(): void {
		$output = $this->getParserOutput( '#REDIRECT [[Redirect Link Target]]' );

		$this->assertArrayHasKey( 'Redirect_Link_Target', $output->getLinks()[NS_MAIN] ?? [] );
	}

	public function testRedirectPageLoadsRedirectPageModuleStyles(): void {
		$output = $this->getParserOutput( '#REDIRECT [[Redirect Styles Target]]' );

		$this->assertContains( 'mediawiki.action.view.redirectPage', $output->getModuleStyles() );
	}

	public function testNonRedirectPageHasNoRedirectView(): void {
		$output = $this->getParserOutput( "# Just A Heading\n\nWith some body text." );

		$this->assertNull( $output->getRedirectHeader() );
	}

	public function testContentReportsRedirectTarget(): void {
		$content = new MarkdownContent( '#REDIRECT [[Reported Target]]' );

		$this->assertTrue( $content->isRedirect() );
		$this->assertSame( 'Reported Target', $content->getRedirectTarget()?->getPrefixedText() );
	}

	public function testNormalContentReportsNoRedirectTarget(): void {
		$content = new MarkdownContent( "# Not A Redirect\n\nJust [[Some Link]] in prose." );

		$this->assertFalse( $content->isRedirect() );
		$this->assertNull( $content->getRedirectTarget() );
	}

	public function testInvalidRedirectTargetIsNotARedirect(): void {
		$content = new MarkdownContent( '#REDIRECT [[Special:Userlogout]]' );

		$this->assertFalse( $content->isRedirect() );
	}

	public function testMakeRedirectContentRoundTripsToTarget(): void {
		$content = $this->handler()->makeRedirectContent( Title::makeTitle( NS_MAIN, 'Round Trip Target' ) );

		$this->assertInstanceOf( MarkdownContent::class, $content );
		$this->assertSame( 'Round Trip Target', $content->getRedirectTarget()?->getPrefixedText() );
	}

	public function testMakeRedirectContentColonEscapesCategoryTarget(): void {
		$content = $this->handler()->makeRedirectContent( Title::makeTitle( NS_CATEGORY, 'Escaped Cat' ) );

		$this->assertInstanceOf( MarkdownContent::class, $content );
		$this->assertStringContainsString( '[[:Category:Escaped Cat]]', $content->getText() );
	}

	public function testRedirectPageRegistersTrailingCategory(): void {
		$output = $this->getParserOutput( "#REDIRECT [[Rcat Target]]\n\n[[Category:Redirects From Acronyms]]" );

		$this->assertContains( 'Redirects_From_Acronyms', $output->getCategoryNames() );
	}

	public function testRedirectPageRegistersTrailingLink(): void {
		$output = $this->getParserOutput( "#REDIRECT [[Primary Target]]\n\nSee also [[Trailing Related Page]]." );

		$this->assertArrayHasKey( 'Trailing_Related_Page', $output->getLinks()[NS_MAIN] ?? [] );
	}

	public function testRedirectPageRendersTrailingContentBelowRedirectView(): void {
		$output = $this->getParserOutput( "#REDIRECT [[Bodied Target]]\n\nTrailing prose after the redirect line." );

		$this->assertStringContainsString( 'Trailing prose after the redirect line.', $output->getRawText() );
	}

	public function testRedirectPageWithTrailingContentStillRendersRedirectView(): void {
		$output = $this->getParserOutput( "#REDIRECT [[Header Target]]\n\n[[Category:Some Rcat]]" );

		$header = $output->getRedirectHeader();
		$this->assertNotNull( $header );
		$this->assertStringContainsString( 'Header Target', $header );
	}

	public function testBareRedirectPageRendersNoBodyContent(): void {
		$output = $this->getParserOutput( '#REDIRECT [[Bare Target]]' );

		$this->assertSame( '', $output->getRawText() );
	}

}
