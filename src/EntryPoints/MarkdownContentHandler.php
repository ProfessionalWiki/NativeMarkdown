<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\EntryPoints;

use MediaWiki\Content\Content;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NativeMarkdown\Application\RenderedMarkdown;
use ProfessionalWiki\NativeMarkdown\Application\Section;
use ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension;
use Wikimedia\Parsoid\Core\SectionMetadata;
use Wikimedia\Parsoid\Core\TOCData;

/**
 * ContentHandler for the markdown content model. Translates the pure
 * rendering result into MediaWiki's ParserOutput.
 */
final class MarkdownContentHandler extends TextContentHandler {

	private const FRONT_MATTER_DATA_KEY = 'nativemarkdown-front-matter';

	// Matches the threshold used by the wikitext parser ("enough" headings for a ToC).
	private const TOC_SECTION_THRESHOLD = 4;

	public function __construct( string $modelId = NativeMarkdownExtension::CONTENT_MODEL ) {
		// CONTENT_FORMAT_TEXT is a MediaWiki global constant psalm cannot resolve from scanned core files.
		/** @psalm-suppress UndefinedConstant */
		parent::__construct( $modelId, [ CONTENT_FORMAT_TEXT ] );
	}

	/**
	 * @return class-string<MarkdownContent>
	 */
	protected function getContentClass() {
		return MarkdownContent::class;
	}

	public function isParserCacheSupported() {
		return true;
	}

	protected function fillParserOutput( Content $content, ContentParseParams $cpoParams, ParserOutput &$output ): void {
		$rendered = NativeMarkdownExtension::getInstance()->getMarkdownRenderer()->render(
			$content instanceof MarkdownContent ? $content->getText() : '',
			$cpoParams->getGenerateHtml()
		);

		$output->setFromParserOptions( $cpoParams->getParserOptions() );

		$this->registerLinks( $output, $rendered );
		$this->registerCategories( $output, $rendered );
		$this->registerFiles( $output, $rendered );
		$this->registerExternalLinks( $output, $rendered );
		$this->registerFrontMatter( $output, $rendered );
		$this->registerSections( $output, $rendered );

		$output->setRawText( $cpoParams->getGenerateHtml() ? $rendered->html : null );
	}

	private function registerLinks( ParserOutput $output, RenderedMarkdown $rendered ): void {
		foreach ( $rendered->links as $link ) {
			// addLink() routes interwiki targets to the interwiki table itself.
			$output->addLink( new TitleValue( $link->namespace, $link->dbKey, '', $link->interwiki ) );
		}
	}

	private function registerCategories( ParserOutput $output, RenderedMarkdown $rendered ): void {
		foreach ( $rendered->categories as $category ) {
			$output->addCategory( $category->title->dbKey, $category->sortKey );
		}
	}

	private function registerFiles( ParserOutput $output, RenderedMarkdown $rendered ): void {
		foreach ( $rendered->files as $embed ) {
			$output->addImage( $embed->title->dbKey );
		}
	}

	private function registerExternalLinks( ParserOutput $output, RenderedMarkdown $rendered ): void {
		foreach ( $rendered->externalLinks as $url ) {
			$output->addExternalLink( $url );
		}
	}

	private function registerFrontMatter( ParserOutput $output, RenderedMarkdown $rendered ): void {
		if ( $rendered->frontMatter !== null ) {
			$output->setExtensionData( self::FRONT_MATTER_DATA_KEY, $rendered->frontMatter );
		}
	}

	private function registerSections( ParserOutput $output, RenderedMarkdown $rendered ): void {
		if ( $rendered->sections === [] ) {
			return;
		}

		$output->setTOCData( $this->buildTocData( $rendered->sections ) );

		if ( count( $rendered->sections ) >= self::TOC_SECTION_THRESHOLD ) {
			$output->setOutputFlag( ParserOutputFlags::SHOW_TOC );
		}
	}

	/**
	 * @param Section[] $sections
	 */
	private function buildTocData( array $sections ): TOCData {
		$tocData = new TOCData();
		$previousLevel = 0;

		foreach ( $sections as $section ) {
			$metadata = SectionMetadata::fromLegacy( [ 'index' => '' ] );

			$tocData->addSection( $metadata );
			$tocData->processHeading( $previousLevel, $section->level, $metadata );

			$metadata->line = htmlspecialchars( $section->text, ENT_QUOTES );
			$metadata->anchor = $section->anchor;
			$metadata->linkAnchor = $section->anchor;

			$previousLevel = $section->level;
		}

		return $tocData;
	}

}
