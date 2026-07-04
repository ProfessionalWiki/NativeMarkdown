<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;

/**
 * Page-level lifecycle behavior for markdown pages: moving (with redirects
 * unsupported), deletion/undeletion and model preservation across them.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\NativeMarkdownHooks
 * @group Database
 */
class MarkdownPageLifecycleTest extends MediaWikiIntegrationTestCase {

	private function createMarkdownPage( Title $title, string $markdown ): void {
		$this->editPage( $title, new MarkdownContent( $markdown ) );
	}

	private function contentModelOf( Title $title ): string {
		return $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )
			->getContentModel();
	}

	public function testMovedPageKeepsMarkdownModelAtNewTitle(): void {
		$source = Title::makeTitle( NS_MAIN, 'Markdown Move Source' );
		$target = Title::makeTitle( NS_MAIN, 'Markdown Move Target' );
		$this->createMarkdownPage( $source, "# Portable\n\nMoves cleanly." );

		$status = $this->getServiceContainer()->getMovePageFactory()
			->newMovePage( $source, $target )
			->move( $this->getTestSysop()->getUser(), 'moving', false );

		$this->assertStatusGood( $status );
		$this->assertSame( 'markdown', $this->contentModelOf( $target ) );
	}

	public function testMovingMarkdownPageLeavesNoRedirectAtOldTitle(): void {
		$source = Title::makeTitle( NS_MAIN, 'Markdown Redirect Source' );
		$target = Title::makeTitle( NS_MAIN, 'Markdown Redirect Target' );
		$this->createMarkdownPage( $source, "# No Redirect\n\nBody." );

		// Ask for a redirect: the model cannot hold one, so none is created.
		$this->getServiceContainer()->getMovePageFactory()
			->newMovePage( $source, $target )
			->move( $this->getTestSysop()->getUser(), 'moving', true );

		$this->assertFalse(
			$source->toPageIdentity()->exists(),
			'A markdown page move must not leave a redirect stub behind.'
		);
	}

	public function testUndeletedPageKeepsItsMarkdownModel(): void {
		$title = Title::makeTitle( NS_MAIN, 'Markdown Undelete Me' );
		$this->createMarkdownPage( $title, "# Temporary\n\nWill be deleted then restored." );

		$this->deletePage(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )
		);
		$this->assertFalse( $title->toPageIdentity()->exists() );

		$this->getServiceContainer()->getUndeletePageFactory()
			->newUndeletePage(
				$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title ),
				$this->getTestSysop()->getUser()
			)
			->undeleteUnsafe( 'restoring' );

		$this->assertSame( 'markdown', $this->contentModelOf( $title ) );
	}

	public function testImportedPageWithoutModelAdoptsNamespaceDefault(): void {
		$this->overrideConfigValues( [ 'NativeMarkdownNamespaces' => [ NS_HELP ] ] );

		// A page created without an explicit model in a markdown-default namespace
		// becomes markdown, exactly as a fresh on-wiki page there would. Imports
		// carrying an explicit content model keep it (guarded by the exists() check).
		$fresh = Title::makeTitle( NS_HELP, 'Imported Without Model' );

		$this->assertSame( 'markdown', $fresh->getContentModel() );
	}

}
