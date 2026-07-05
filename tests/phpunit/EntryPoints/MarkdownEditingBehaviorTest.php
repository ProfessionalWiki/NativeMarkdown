<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\Content\TextContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;

/**
 * Markdown pages inherit diff, undo and edit-conflict merging from TextContent.
 * These tests pin that the inherited behavior works for our content model,
 * producing MarkdownContent (not plain wikitext) on the way out.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent
 */
class MarkdownEditingBehaviorTest extends MediaWikiIntegrationTestCase {

	private function handler(): TextContentHandler {
		$handler = $this->getServiceContainer()->getContentHandlerFactory()->getContentHandler( 'markdown' );
		$this->assertInstanceOf( TextContentHandler::class, $handler );

		return $handler;
	}

	public function testThreeWayMergeCombinesNonConflictingEdits(): void {
		$merged = $this->handler()->merge3(
			new MarkdownContent( "# Title\n\nfirst line\nsecond line\nthird line\n" ),
			new MarkdownContent( "# Title EDITED\n\nfirst line\nsecond line\nthird line\n" ),
			new MarkdownContent( "# Title\n\nfirst line\nsecond line\nthird line CHANGED\n" )
		);

		$this->assertInstanceOf( MarkdownContent::class, $merged );
		$this->assertStringContainsString( '# Title EDITED', $merged->getText() );
		$this->assertStringContainsString( 'third line CHANGED', $merged->getText() );
	}

	public function testConflictingEditsFailToMerge(): void {
		$conflict = $this->handler()->merge3(
			new MarkdownContent( "the original single line\n" ),
			new MarkdownContent( "my rewrite of the line\n" ),
			new MarkdownContent( "your rewrite of the line\n" )
		);

		$this->assertFalse( $conflict );
	}

	public function testUndoOfLatestRevisionRestoresPreviousContent(): void {
		$original = new MarkdownContent( "before the edit\n" );
		$edited = new MarkdownContent( "after the edit\n" );

		$undone = $this->handler()->getUndoContent(
			$edited,
			$edited,
			$original,
			true
		);

		$this->assertInstanceOf( MarkdownContent::class, $undone );
		$this->assertSame( "before the edit\n", $undone->getText() );
	}

	public function testDiffShowsAddedAndRemovedLines(): void {
		$diff = $this->handler()
			->getSlotDiffRenderer( RequestContext::getMain() )
			->getDiff(
				new MarkdownContent( "shared context\nRemovedSentinel\n" ),
				new MarkdownContent( "shared context\nAddedSentinel\n" )
			);

		$this->assertStringContainsString( 'RemovedSentinel', $diff );
		$this->assertStringContainsString( 'AddedSentinel', $diff );
		$this->assertStringContainsString( 'diff-deletedline', $diff );
		$this->assertStringContainsString( 'diff-addedline', $diff );
	}

	public function testMarkdownContentModelSupportsRedirects(): void {
		$this->assertTrue( $this->handler()->supportsRedirects() );
	}

}
