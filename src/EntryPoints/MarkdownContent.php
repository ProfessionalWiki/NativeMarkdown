<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\EntryPoints;

use MediaWiki\Content\TextContent;
use ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension;

/**
 * Page content in the markdown content model. The raw text is markdown,
 * which is also what gets returned by action=raw and the REST source endpoint.
 */
final class MarkdownContent extends TextContent {

	private ?string $searchText = null;

	public function __construct( string $text ) {
		parent::__construct( $text, NativeMarkdownExtension::CONTENT_MODEL );
	}

	/**
	 * The search index gets the rendered words rather than the raw markdown, so
	 * result snippets do not show markup characters or hidden front matter.
	 *
	 * Extraction parses the document, so the result is kept: an edit runs this
	 * through both the parser output path and the search update on the same
	 * Content instance (RevisionRecord caches slot content), and core search
	 * snippets call it repeatedly per result.
	 */
	public function getTextForSearchIndex(): string {
		$this->searchText ??= NativeMarkdownExtension::getInstance()
			->getMarkdownRenderer()
			->extractPlainText( $this->getText() );

		return $this->searchText;
	}

}
