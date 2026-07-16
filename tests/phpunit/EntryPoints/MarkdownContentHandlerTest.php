<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;
use ProfessionalWiki\NativeMarkdown\Tests\FrontMatterBombs;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent
 * @covers \ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension
 * @group Database
 */
class MarkdownContentHandlerTest extends MediaWikiIntegrationTestCase {

	private function getParserOutput( string $markdown ): ParserOutput {
		return $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new MarkdownContent( $markdown ),
			PageReferenceValue::localReference( NS_MAIN, 'NativeMarkdownTestPage' )
		);
	}

	public function testRendersMarkdownAsHtml(): void {
		$output = $this->getParserOutput( "# Hello\n\nSome **bold** text" );

		$this->assertStringContainsString( '<h1 id="Hello">Hello</h1>', $output->getRawText() );
		$this->assertStringContainsString( '<strong>bold</strong>', $output->getRawText() );
	}

	public function testRegistersInternalLinks(): void {
		$output = $this->getParserOutput( 'See [[Linked Page]] and [[Another One|label]]' );

		$this->assertArrayHasKey( 'Linked_Page', $output->getLinks()[NS_MAIN] );
		$this->assertArrayHasKey( 'Another_One', $output->getLinks()[NS_MAIN] );
	}

	public function testRendersMissingPageAsRedLink(): void {
		$output = $this->getParserOutput( '[[Surely This Page Is Missing]]' );

		$this->assertStringContainsString( 'class="new"', $output->getRawText() );
	}

	public function testBatchedExistenceLookupKeepsBlueAndRedLinksApart(): void {
		// Only needs the target to exist; editPage works whatever the default model is.
		$this->editPage( 'Batch Existing Page', 'It exists.' );

		$output = $this->getParserOutput( 'Blue [[Batch Existing Page]] and red [[Batch Missing Page]]' );

		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*Batch_Existing_Page[^"]*" title="Batch Existing Page">/',
			$output->getRawText()
		);
		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*Batch_Missing_Page[^"]*" class="new"/',
			$output->getRawText()
		);
	}

	public function testRegistersCategoryWithoutRenderingIt(): void {
		$output = $this->getParserOutput( "Text\n\n[[Category:Spike]]" );

		$this->assertSame( [ 'Spike' ], $output->getCategoryNames() );
		$this->assertStringNotContainsString( 'Spike', $output->getRawText() );
	}

	public function testColonPrefixedCategoryIsVisibleLinkNotAssignment(): void {
		$output = $this->getParserOutput( '[[:Category:Spike]]' );

		$this->assertSame( [], $output->getCategoryNames() );
		$this->assertStringContainsString( 'Category:Spike', $output->getRawText() );
	}

	public function testSetsTocDataWithMediaWikiStyleAnchors(): void {
		$output = $this->getParserOutput(
			"# One\n\n## Sub Section Here\n\ntext\n\n## Another Sub\n\n### Deep One\n\ntext"
		);

		$sections = $output->getTOCData()->getSections();

		$this->assertCount( 4, $sections );
		$this->assertSame( 'Sub_Section_Here', $sections[1]->anchor );
		$this->assertSame( 'Sub Section Here', $sections[1]->line );
		$this->assertSame( 2, $sections[1]->hLevel );
	}

	public function testFrontMatterIsStoredAsExtensionData(): void {
		$output = $this->getParserOutput( "---\nstatus: draft\n---\n\nBody" );

		$this->assertSame(
			[ 'status' => 'draft' ],
			$output->getExtensionData( 'nativemarkdown-front-matter' )
		);
	}

	public function testAliasBombFrontMatterIsNotStoredAsExtensionData(): void {
		// Bounded stand-in for a YAML alias bomb: the guard rejects it before it
		// can be parsed, so nothing reaches the serialized parser cache.
		$output = $this->getParserOutput( FrontMatterBombs::aliasBombBlock() . "\nBody" );

		$this->assertNull( $output->getExtensionData( 'nativemarkdown-front-matter' ) );
		$this->assertStringContainsString( 'Body', $output->getRawText() );
	}

	public function testNoSectionEditLinksAppearInHtml(): void {
		$output = $this->getParserOutput( "## One\n\n## Two\n\n## Three\n\n## Four" );

		$this->assertStringNotContainsString( 'mw-editsection', $output->getRawText() );
	}

	public function testOutputGetsParserOutputWrapperClass(): void {
		$output = $this->getParserOutput( 'Some text' );

		$this->assertSame( 'mw-parser-output', $output->getWrapperDivClass() );
	}

	public function testExternalLinksAreRegistered(): void {
		$output = $this->getParserOutput( 'A [link](https://example.com/registered) here' );

		$this->assertArrayHasKey( 'https://example.com/registered', $output->getExternalLinks() );
	}

	public function testSpecialPageLinkRendersWithoutBeingRecorded(): void {
		$output = $this->getParserOutput( 'Go to [[Special:RecentChanges]]' );

		$this->assertSame( [], $output->getLinks() );
		$this->assertStringContainsString( 'Special:RecentChanges', $output->getRawText() );
		$this->assertStringContainsString( '<a href=', $output->getRawText() );
	}

	public function testNamespacePrefixedLinkRegistersInThatNamespace(): void {
		$output = $this->getParserOutput( 'See [[Help:Phase One Matrix]]' );

		$this->assertArrayHasKey( 'Phase_One_Matrix', $output->getLinks()[NS_HELP] ?? [] );
	}

	public function testLowercaseNamespacePrefixIsNormalized(): void {
		$output = $this->getParserOutput( "Text\n\n[[category:Lowercase Prefix Cat]]" );

		$this->assertSame( [ 'Lowercase_Prefix_Cat' ], $output->getCategoryNames() );
		$this->assertStringNotContainsString( 'Lowercase', $output->getRawText() );
	}

	public function testMissingFileEmbedRegistersImageAndRendersRedLink(): void {
		// A missing file renders an upload red link only where uploads are enabled;
		// vanilla MediaWiki defaults them off, so pin it for a deterministic assertion.
		$this->overrideConfigValue( MainConfigNames::EnableUploads, true );

		$output = $this->getParserOutput( 'Look: [[File:Surely Missing File.png|200px|alt=Nothing]]' );

		$this->assertArrayHasKey( 'Surely_Missing_File.png', $output->getImages() );
		$this->assertSame( [], $output->getLinks() );
		$this->assertStringContainsString( 'class="new"', $output->getRawText() );
		$this->assertStringContainsString( 'typeof="mw:Error mw:File"', $output->getRawText() );
	}

	public function testColonPrefixedFileIsPageLinkNotImage(): void {
		$output = $this->getParserOutput( 'See [[:File:Some Linked File.png]]' );

		$this->assertArrayHasKey( 'Some_Linked_File.png', $output->getLinks()[NS_FILE] ?? [] );
		$this->assertSame( [], $output->getImages() );
	}

	public function testSamePageAnchorRendersAsFragmentLink(): void {
		$output = $this->getParserOutput( 'See [[#History]] below' );

		$this->assertSame( [], $output->getLinks() );
		$this->assertStringContainsString( 'href="#History"', $output->getRawText() );
		$this->assertStringContainsString( '>#History</a>', $output->getRawText() );
	}

	public function testParserCacheIsSupported(): void {
		$handler = $this->getServiceContainer()->getContentHandlerFactory()->getContentHandler( 'markdown' );

		$this->assertTrue( $handler->isParserCacheSupported() );
	}

	public function testWikiLinkLabelHtmlIsEscapedByRealLinkRenderer(): void {
		$output = $this->getParserOutput( '[[Some Target|<script>alert(1)</script>]]' );

		$this->assertStringNotContainsString( '<script>', $output->getRawText() );
		$this->assertStringContainsString( '&lt;script&gt;', $output->getRawText() );
	}

	public function testFileEmbedAltHtmlIsEscapedByRealFileRenderer(): void {
		$output = $this->getParserOutput( '[[File:Surely Missing Xss.png|alt=<script>alert(1)</script>]]' );

		$this->assertStringNotContainsString( '<script>', $output->getRawText() );
	}

	public function testRawHtmlBlockIsEscapedEndToEnd(): void {
		$output = $this->getParserOutput( "<script>alert(document.cookie)</script>\n\nbody text" );

		$this->assertStringNotContainsString( '<script>', $output->getRawText() );
		$this->assertStringContainsString( 'body text', $output->getRawText() );
	}

	public function testInterwikiLinkIsRegisteredAsInterwiki(): void {
		$this->configureWikipediaInterwiki();

		$output = $this->getParserOutput( 'See [[wikipedia:Berlin]] for details' );

		$this->assertArrayHasKey( 'Berlin', $output->getInterwikiLinks()['wikipedia'] ?? [] );
		$this->assertSame( [], $output->getLinks() );
	}

	public function testInterwikiFragmentOnlyLinkKeepsPrefixInLabel(): void {
		$this->configureWikipediaInterwiki();

		$output = $this->getParserOutput( 'See [[wikipedia:#History]] for details' );

		$this->assertStringContainsString( '>wikipedia:#History</a>', $output->getRawText() );
	}

	public function testMarkdownPageLoadsContentStyles(): void {
		$output = $this->getParserOutput( 'Just some prose, without a code block in sight.' );

		$this->assertContains( 'ext.nativeMarkdown.content', $output->getModuleStyles() );
	}

	public function testFencedCodeBlockWithLanguageIsSyntaxHighlighted(): void {
		$this->skipIfSyntaxHighlightMissing();

		$output = $this->getParserOutput( "```python\nprint('hi')\n```" );

		$this->assertStringContainsString( 'mw-highlight-lang-python', $output->getRawText() );
		$this->assertContains( 'ext.pygments', $output->getModuleStyles() );
		$this->assertContains( 'ext.pygments.view', $output->getModules() );
	}

	public function testFencedCodeBlockWithoutLanguageIsNotHighlighted(): void {
		$this->skipIfSyntaxHighlightMissing();

		$output = $this->getParserOutput( "```\nplain code\n```" );

		$this->assertStringContainsString( '<pre><code>plain code', $output->getRawText() );
		$this->assertNotContains( 'ext.pygments', $output->getModuleStyles() );
	}

	private function skipIfSyntaxHighlightMissing(): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' ) ) {
			$this->markTestSkipped( 'Extension:SyntaxHighlight is not installed' );
		}
	}

	private function configureWikipediaInterwiki(): void {
		$globalScope = 2;
		$this->overrideConfigValues( [
			MainConfigNames::InterwikiScopes => $globalScope,
			MainConfigNames::InterwikiCache => ClassicInterwikiLookup::buildCdbHash(
				[ [ 'iw_prefix' => 'wikipedia', 'iw_url' => 'https://en.wikipedia.org/wiki/$1', 'iw_local' => 0 ] ],
				$globalScope
			),
		] );
	}

}
