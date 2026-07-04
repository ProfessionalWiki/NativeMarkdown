<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\MarkdownContent
 * @covers \ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension
 */
class MarkdownContentTest extends MediaWikiIntegrationTestCase {

	public function testSearchIndexTextHasWordsWithoutMarkdownMarkup(): void {
		$content = new MarkdownContent( "# Release Notes\n\nSome **important** changes." );

		$text = $content->getTextForSearchIndex();

		$this->assertStringContainsString( 'Release Notes', $text );
		$this->assertStringContainsString( 'important', $text );
		$this->assertStringNotContainsString( '#', $text );
		$this->assertStringNotContainsString( '*', $text );
	}

	public function testSearchIndexTextExcludesFrontMatterAndCategories(): void {
		$content = new MarkdownContent(
			"---\ninternal_id: SECRET-42\n---\n\nBody text.\n\n[[Category:Backstage]]"
		);

		$text = $content->getTextForSearchIndex();

		$this->assertStringContainsString( 'Body text', $text );
		$this->assertStringNotContainsString( 'SECRET-42', $text );
		$this->assertStringNotContainsString( 'Backstage', $text );
	}

	public function testSearchIndexTextUsesWikiLinkLabel(): void {
		$content = new MarkdownContent( 'See [[Installation Guide|how to install]] first.' );

		$text = $content->getTextForSearchIndex();

		$this->assertStringContainsString( 'how to install', $text );
		$this->assertStringNotContainsString( '[[', $text );
	}

}
