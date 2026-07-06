<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Persistence;

use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\Application\TemplateCall;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiTemplateExpander;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiTemplateExpander
 * @group Database
 */
class MediaWikiTemplateExpanderTest extends MediaWikiIntegrationTestCase {

	private function newExpander(): MediaWikiTemplateExpander {
		return new MediaWikiTemplateExpander(
			$this->getServiceContainer()->getParserFactory(),
			PageReferenceValue::localReference( NS_MAIN, 'ExpanderTestPage' ),
			ParserOptions::newFromAnon(),
			null
		);
	}

	public function testBlockCallReturnsTemplateHtml(): void {
		$this->editPage( 'Template:Box', 'Box body text' );

		$html = $this->newExpander()->expand( new TemplateCall( '{{Box}}', true ) );

		$this->assertStringContainsString( 'Box body text', $html );
	}

	public function testBlockCallReturnsTableHtmlWithoutParagraphWrapper(): void {
		$this->editPage( 'Template:Infobox', "{| class=\"wikitable\"\n| Cell\n|}" );

		$html = $this->newExpander()->expand( new TemplateCall( '{{Infobox}}', true ) );

		$this->assertStringContainsString( '<table', $html );
	}

	public function testInlineCallHasNoSurroundingParagraph(): void {
		$this->editPage( 'Template:Box', 'Box body text' );

		$html = $this->newExpander()->expand( new TemplateCall( '{{Box}}', false ) );

		$this->assertStringNotContainsString( '<p>', $html );
		$this->assertStringContainsString( 'Box body text', $html );
	}

	public function testMergeIntoRecordsTemplateDependency(): void {
		$this->editPage( 'Template:Box', 'Box body text' );
		$expander = $this->newExpander();
		$expander->expand( new TemplateCall( '{{Box}}', true ) );

		$output = new ParserOutput();
		$expander->mergeInto( $output );

		$this->assertArrayHasKey( 'Box', $output->getTemplates()[NS_TEMPLATE] ?? [] );
	}

	public function testMergeIntoRecordsCategoryAddedByTemplate(): void {
		$this->editPage( 'Template:Categoriser', '[[Category:Expanded Cat]]' );
		$expander = $this->newExpander();
		$expander->expand( new TemplateCall( '{{Categoriser}}', true ) );

		$output = new ParserOutput();
		$expander->mergeInto( $output );

		$this->assertContains( 'Expanded_Cat', $output->getCategoryNames() );
	}

	public function testMergeIntoRecordsEveryExpandedTemplate(): void {
		$this->editPage( 'Template:First', 'first' );
		$this->editPage( 'Template:Second', 'second' );
		$expander = $this->newExpander();
		$expander->expand( new TemplateCall( '{{First}}', true ) );
		$expander->expand( new TemplateCall( '{{Second}}', true ) );

		$output = new ParserOutput();
		$expander->mergeInto( $output );

		$templates = $output->getTemplates()[NS_TEMPLATE] ?? [];
		$this->assertArrayHasKey( 'First', $templates );
		$this->assertArrayHasKey( 'Second', $templates );
	}

}
