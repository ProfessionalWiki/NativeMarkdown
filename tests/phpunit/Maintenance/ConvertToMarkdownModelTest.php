<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Maintenance;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;
use ProfessionalWiki\NativeMarkdown\Maintenance\ConvertToMarkdownModel;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Maintenance\ConvertToMarkdownModel
 * @group Database
 */
class ConvertToMarkdownModelTest extends MaintenanceBaseTestCase {

	private const WIKITEXT_BODY = "A page with '''bold''' text and a [[Some Link]].";

	protected function getMaintenanceClass() {
		return ConvertToMarkdownModel::class;
	}

	protected function setUp(): void {
		parent::setUp();

		// Neutralize the activation config so fixture models come only from the explicit content
		// objects below. The selectors still work, proving the script ignores live wiki config.
		$this->overrideConfigValues( [
			'NativeMarkdownEverywhere' => false,
			'NativeMarkdownSuffixDetection' => false,
			'NativeMarkdownNamespaces' => [],
		] );
	}

	public function testMdSuffixModeConvertsMainNamespaceSuffixedPage(): void {
		$plain = Title::makeTitle( NS_MAIN, 'Plain Wikitext' );
		$suffixed = Title::makeTitle( NS_MAIN, 'Guide.md' );
		$this->createWikitextPage( $plain );
		$this->createWikitextPage( $suffixed );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $suffixed ) );
		$this->assertSame( self::WIKITEXT_BODY, $this->currentTextOf( $suffixed ), 'The text must be unchanged.' );
		$this->assertSame( 2, $this->revisionCountOf( $suffixed ), 'Conversion must add exactly one revision.' );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $plain ), 'The non-.md page is untouched.' );
	}

	public function testMdSuffixModeConvertsTalkNamespaceSuffixedPage(): void {
		$title = Title::makeTitle( NS_TALK, 'Discussion.md' );
		$this->createWikitextPage( $title );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $title ) );
	}

	public function testMdSuffixModeSkipsTemplateAndMediaWikiSuffixedPages(): void {
		$included = Title::makeTitle( NS_MAIN, 'Included.md' );
		$template = Title::makeTitle( NS_TEMPLATE, 'Widget.md' );
		$interface = Title::makeTitle( NS_MEDIAWIKI, 'Notice.md' );
		$this->createWikitextPage( $included );
		$this->createWikitextPage( $template );
		$this->createWikitextPage( $interface );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $included ), 'A main-namespace .md page still converts.' );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $template ) );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $interface ) );
	}

	public function testMdSuffixModeIgnoresNonSuffixedPage(): void {
		$title = Title::makeTitle( NS_MAIN, 'No Suffix Here' );
		$this->createWikitextPage( $title );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $title ) );
		$this->assertSame( 1, $this->revisionCountOf( $title ), 'No revision must be added.' );
	}

	public function testAlreadyMarkdownPageIsLeftUntouched(): void {
		$title = Title::makeTitle( NS_MAIN, 'Native.md' );
		$this->createMarkdownPage( $title );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $title ) );
		$this->assertSame( 1, $this->revisionCountOf( $title ), 'A page already on markdown gets no new revision.' );
	}

	public function testConvertsPageWhoseStoredContentModelIsNull(): void {
		$title = Title::makeTitle( NS_MAIN, 'Null Model.md' );
		$this->createWikitextPage( $title );
		$this->nullOutStoredContentModel( $title );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $title ) );
	}

	public function testNamespaceModeConvertsAllWikitextPagesInNamespace(): void {
		$plain = Title::makeTitle( NS_HELP, 'Getting Started' );
		$suffixed = Title::makeTitle( NS_HELP, 'Notes.md' );
		$elsewhere = Title::makeTitle( NS_MAIN, 'Unrelated' );
		$this->createWikitextPage( $plain );
		$this->createWikitextPage( $suffixed );
		$this->createWikitextPage( $elsewhere );

		$this->runConversion( [ 'namespace' => NS_HELP ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $plain ), 'A non-.md page in the namespace converts.' );
		$this->assertSame( 'markdown', $this->currentModelOf( $suffixed ) );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $elsewhere ), 'Other namespaces are untouched.' );
	}

	public function testNamespaceModeConvertsTemplatePageGivenExplicitTemplateNamespace(): void {
		$title = Title::makeTitle( NS_TEMPLATE, 'Infobox' );
		$this->createWikitextPage( $title );

		$this->runConversion( [ 'namespace' => NS_TEMPLATE ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $title ) );
	}

	public function testNamespaceModeSkipsCodePageTitles(): void {
		$title = Title::makeTitle( NS_HELP, 'Gadget.js' );
		$this->createWikitextPage( $title );

		$this->runConversion( [ 'namespace' => NS_HELP ] );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $title ) );
	}

	public function testCombinedModeConvertsOnlySuffixedPagesInSelectedNamespace(): void {
		$suffixedInside = Title::makeTitle( NS_HELP, 'Reference.md' );
		$plainInside = Title::makeTitle( NS_HELP, 'Overview' );
		$suffixedOutside = Title::makeTitle( NS_MAIN, 'Outside.md' );
		$this->createWikitextPage( $suffixedInside );
		$this->createWikitextPage( $plainInside );
		$this->createWikitextPage( $suffixedOutside );

		$this->runConversion( [ 'md-suffix' => 1, 'namespace' => NS_HELP ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $suffixedInside ) );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $plainInside ), 'Non-.md pages in the namespace stay.' );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $suffixedOutside ), '.md pages outside the namespace stay.' );
	}

	public function testCombinedModeConvertsSuffixedTemplatePageGivenTemplateNamespace(): void {
		$suffixed = Title::makeTitle( NS_TEMPLATE, 'Card.md' );
		$plain = Title::makeTitle( NS_TEMPLATE, 'Plain' );
		$this->createWikitextPage( $suffixed );
		$this->createWikitextPage( $plain );

		$this->runConversion( [ 'md-suffix' => 1, 'namespace' => NS_TEMPLATE ] );

		$this->assertSame( 'markdown', $this->currentModelOf( $suffixed ) );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $plain ) );
	}

	public function testRedirectPagesAreSkipped(): void {
		$title = Title::makeTitle( NS_MAIN, 'Redirect Source.md' );
		$this->editPage(
			$title,
			new WikitextContent( '#REDIRECT [[Redirect Target]]' ),
			'',
			NS_MAIN,
			$this->getTestSysop()->getUser()
		);

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $title ) );
		$this->assertTrue( $this->isStoredAsRedirect( $title ), 'The page must remain a redirect.' );
	}

	public function testDryRunListsCandidatesWithoutConverting(): void {
		$title = Title::makeTitle( NS_MAIN, 'Preview.md' );
		$this->createWikitextPage( $title );

		$this->expectOutputRegex( '/' . preg_quote( $title->getPrefixedText(), '/' ) . '.*would be converted/s' );

		$this->runConversion( [ 'md-suffix' => 1, 'dry-run' => 1 ] );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $this->currentModelOf( $title ), 'A dry run must change nothing.' );
		$this->assertSame( 1, $this->revisionCountOf( $title ) );
	}

	public function testFatalErrorWhenNoSelectorProvided(): void {
		$this->expectCallToFatalError();

		$this->maintenance->execute();
	}

	public function testFatalErrorWhenNamespaceIsNotNumeric(): void {
		$this->expectCallToFatalError();
		$this->maintenance->setOption( 'namespace', 'Help' );

		$this->maintenance->execute();
	}

	public function testConvertedRevisionIsAttributedToTheMaintenanceUser(): void {
		$title = Title::makeTitle( NS_MAIN, 'Attributed.md' );
		$this->createWikitextPage( $title );

		$this->runConversion( [ 'md-suffix' => 1 ] );

		$this->assertSame( User::MAINTENANCE_SCRIPT_USER, $this->latestAuthorOf( $title ) );
	}

	public function testBatchingConvertsEveryCandidate(): void {
		$first = Title::makeTitle( NS_MAIN, 'Batch One.md' );
		$second = Title::makeTitle( NS_MAIN, 'Batch Two.md' );
		$third = Title::makeTitle( NS_MAIN, 'Batch Three.md' );
		$this->createWikitextPage( $first );
		$this->createWikitextPage( $second );
		$this->createWikitextPage( $third );

		// Drive the real option so a batch size smaller than the candidate count is exercised.
		$this->maintenance->loadWithArgv( [ '--md-suffix', '--batch-size=1' ] );
		$this->maintenance->execute();

		$this->assertSame( 'markdown', $this->currentModelOf( $first ) );
		$this->assertSame( 'markdown', $this->currentModelOf( $second ) );
		$this->assertSame( 'markdown', $this->currentModelOf( $third ) );
	}

	private function createWikitextPage( Title $title, string $text = self::WIKITEXT_BODY ): void {
		$this->editPage( $title, new WikitextContent( $text ), '', NS_MAIN, $this->getTestSysop()->getUser() );
	}

	private function createMarkdownPage( Title $title ): void {
		$this->editPage( $title, new MarkdownContent( "# Heading\n\nBody." ), '', NS_MAIN, $this->getTestSysop()->getUser() );
	}

	/**
	 * @param array<string,mixed> $options
	 */
	private function runConversion( array $options ): void {
		foreach ( $options as $name => $value ) {
			$this->maintenance->setOption( $name, $value );
		}

		$this->maintenance->execute();
	}

	/**
	 * Mimics core storing NULL when a page's model equals its namespace default, so the resolved
	 * model (not the raw column) is what decides candidacy.
	 */
	private function nullOutStoredContentModel( Title $title ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_content_model' => null ] )
			->where( [ 'page_id' => $title->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function currentModelOf( Title $title ): string {
		return $this->latestRevision( $title )->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getModel();
	}

	private function currentTextOf( Title $title ): string {
		return $this->latestRevision( $title )->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getContent()->serialize();
	}

	private function latestAuthorOf( Title $title ): string {
		return $this->latestRevision( $title )->getUser( RevisionRecord::RAW )?->getName() ?? '';
	}

	private function latestRevision( Title $title ): RevisionRecord {
		return $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
	}

	private function revisionCountOf( Title $title ): int {
		return (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'revision' )
			->where( [ 'rev_page' => $title->getId() ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	private function isStoredAsRedirect( Title $title ): bool {
		return (bool)$this->getDb()->newSelectQueryBuilder()
			->select( 'page_is_redirect' )
			->from( 'page' )
			->where( [ 'page_id' => $title->getId() ] )
			->caller( __METHOD__ )
			->fetchField();
	}

}
