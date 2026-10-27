<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;
use ProfessionalWiki\NativeMarkdown\Tests\RecordedLinks;

/**
 * End-to-end template transclusion on markdown pages, exercised through the
 * real content-render path and the MediaWiki parser.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContentHandler
 * @covers \ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiTemplateExpander
 * @covers \ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension
 * @group Database
 */
class MarkdownTransclusionTest extends MediaWikiIntegrationTestCase {

	private const PAGE = 'NativeMarkdownTransclusionPage';

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'NativeMarkdownWikitextExpansion', true );
	}

	private function getParserOutput( string $markdown, bool $generateHtml = true ): ParserOutput {
		return $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new MarkdownContent( $markdown ),
			PageReferenceValue::localReference( NS_MAIN, self::PAGE ),
			null,
			null,
			[ 'generate-html' => $generateHtml ]
		);
	}

	public function testTranscludedTemplateRendersInPageHtml(): void {
		$this->editPage( 'Template:Greeting', 'Hello from the template' );

		$output = $this->getParserOutput( 'Intro {{Greeting}} outro' );

		$this->assertStringContainsString( 'Hello from the template', $output->getContentHolderText() );
	}

	public function testBlockTemplateRendersTableFromWikitext(): void {
		$this->editPage( 'Template:Infobox', "{| class=\"wikitable\"\n| Cell content\n|}" );

		$output = $this->getParserOutput( '{{Infobox}}' );

		$this->assertStringContainsString( '<table', $output->getContentHolderText() );
		$this->assertStringContainsString( 'Cell content', $output->getContentHolderText() );
	}

	public function testTransclusionRecordsTemplateDependency(): void {
		$this->editPage( 'Template:Greeting', 'Hello' );

		$output = $this->getParserOutput( '{{Greeting}}' );

		$this->assertContainsEquals( new TitleValue( NS_TEMPLATE, 'Greeting' ), RecordedLinks::templates( $output ) );
	}

	public function testTemplateDependencyIsRecordedEvenWithoutHtml(): void {
		$this->editPage( 'Template:Greeting', 'Hello' );

		$output = $this->getParserOutput( '{{Greeting}}', generateHtml: false );

		$this->assertContainsEquals( new TitleValue( NS_TEMPLATE, 'Greeting' ), RecordedLinks::templates( $output ) );
	}

	public function testCategoryAddedByTemplateIsRegisteredOnThePage(): void {
		$this->editPage( 'Template:Categoriser', '[[Category:From Template]]' );

		$output = $this->getParserOutput( 'Body {{Categoriser}}' );

		$this->assertContains( 'From_Template', $output->getCategoryNames() );
	}

	public function testTemplateArgumentHtmlIsSanitized(): void {
		$this->editPage( 'Template:Echo', '{{{1}}}' );

		$output = $this->getParserOutput( '{{Echo|<script>alert(1)</script>}}' );

		$this->assertStringNotContainsString( '<script>alert', $output->getContentHolderText() );
	}

	public function testTemplateHeadingsDoNotAppearInThePageTableOfContents(): void {
		$this->editPage( 'Template:Sections', "== Template Heading ==\nbody" );

		$output = $this->getParserOutput( "# Real Heading\n\n{{Sections}}" );

		$this->assertNotContains( 'Template_Heading', $this->tocAnchors( $output ) );
	}

	public function testTemplateHeadingsAreAbsentFromToCWhenThePageHasNoHeadings(): void {
		$this->editPage( 'Template:Sections', "== Template Heading ==\nbody" );

		$output = $this->getParserOutput( "Intro paragraph, no headings of its own.\n\n{{Sections}}" );

		$this->assertNotContains( 'Template_Heading', $this->tocAnchors( $output ) );
	}

	/**
	 * @return string[]
	 */
	private function tocAnchors( ParserOutput $output ): array {
		return array_map(
			static fn ( $section ) => $section->anchor,
			$output->getTOCData()?->getSections() ?? []
		);
	}

	public function testTemplateCallIsLiteralWhenTransclusionDisabled(): void {
		$this->overrideConfigValue( 'NativeMarkdownWikitextExpansion', false );
		$this->editPage( 'Template:Greeting', 'Hello from the template' );

		$output = $this->getParserOutput( '{{Greeting}}' );

		$this->assertStringContainsString( '{{Greeting}}', $output->getContentHolderText() );
		$this->assertStringNotContainsString( 'Hello from the template', $output->getContentHolderText() );
		$this->assertSame( [], RecordedLinks::templates( $output ) );
	}

}
