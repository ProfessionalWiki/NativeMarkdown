<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\EntryPoints;

use MediaWiki\Content\Content;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;
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

	public function supportsRedirects(): bool {
		return true;
	}

	public function makeRedirectContent( Title $destination, $text = '' ): Content {
		$redirectText = NativeMarkdownExtension::getInstance()->newRedirectSyntax()->buildRedirectText(
			$this->redirectTargetText( $destination )
		);

		if ( $text !== '' ) {
			$redirectText .= "\n" . $text;
		}

		return new MarkdownContent( $redirectText );
	}

	private function redirectTargetText( Title $destination ): string {
		$colon = $this->redirectTargetNeedsColon( $destination ) ? ':' : '';

		return $colon . $destination->getFullText();
	}

	/**
	 * A redirect to a category or an interlanguage link needs a leading colon,
	 * otherwise the target reads as a category assignment or a language link
	 * rather than a redirect. Mirrors WikitextContentHandler.
	 */
	private function redirectTargetNeedsColon( Title $destination ): bool {
		// NS_CATEGORY is a MediaWiki global constant psalm cannot resolve from scanned core files.
		/** @psalm-suppress UndefinedConstant */
		if ( $destination->getNamespace() === NS_CATEGORY ) {
			return true;
		}

		$interwiki = $destination->getInterwiki();

		return $interwiki !== '' && MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName(
			$interwiki,
			LanguageNameUtils::AUTONYMS,
			LanguageNameUtils::DEFINED
		) !== '';
	}

	public function getRedirectTargetForContent( MarkdownContent $content ): ?Title {
		return $this->resolveRedirectTarget( $content->getText() );
	}

	private function resolveRedirectTarget( string $text ): ?Title {
		$targetText = NativeMarkdownExtension::getInstance()->newRedirectSyntax()->extractTargetText( $text );

		if ( $targetText === null ) {
			return null;
		}

		$target = MediaWikiServices::getInstance()->getTitleFactory()->newFromText( $targetText );

		if ( !$target instanceof Title || !$target->isValidRedirectTarget() ) {
			return null;
		}

		return $target;
	}

	protected function fillParserOutput( Content $content, ContentParseParams $cpoParams, ParserOutput &$output ): void {
		$text = $content instanceof MarkdownContent ? $content->getText() : '';

		$redirectTarget = $this->resolveRedirectTarget( $text );

		if ( $redirectTarget !== null ) {
			$this->fillRedirectParserOutput( $output, $cpoParams, $redirectTarget );
			return;
		}

		$extension = NativeMarkdownExtension::getInstance();

		$templateExpander = $extension->newTemplateExpander(
			$cpoParams->getPage(),
			$cpoParams->getParserOptions(),
			$cpoParams->getRevId()
		);

		$rendered = $extension->getMarkdownRenderer()->render(
			$text,
			$cpoParams->getGenerateHtml(),
			$templateExpander
		);

		$output->setFromParserOptions( $cpoParams->getParserOptions() );

		$this->registerLinks( $output, $rendered );
		$this->registerCategories( $output, $rendered );
		$this->registerFiles( $output, $rendered );
		$this->registerExternalLinks( $output, $rendered );
		$this->registerFrontMatter( $output, $rendered );

		if ( $templateExpander !== null ) {
			$templateExpander->mergeInto( $output );
		}

		// Last, so the page's table of contents comes from its own markdown
		// headings, not from headings inside transcluded templates.
		$this->registerSections( $output, $rendered );

		$output->setRawText( $cpoParams->getGenerateHtml() ? $rendered->html : null );
	}

	/**
	 * A redirect page shows MediaWiki's redirect view (the arrow to the target)
	 * in place of its markdown body, and records the target as a link so page
	 * moves, WhatLinksHere and the redirect table all see it.
	 */
	private function fillRedirectParserOutput( ParserOutput $output, ContentParseParams $cpoParams, Title $target ): void {
		$output->setFromParserOptions( $cpoParams->getParserOptions() );
		$output->addLink( $target );

		if ( !$cpoParams->getGenerateHtml() ) {
			$output->setRawText( null );
			return;
		}

		$services = MediaWikiServices::getInstance();
		$page = $services->getTitleFactory()->newFromPageReference( $cpoParams->getPage() );

		$output->setRedirectHeader(
			$services->getLinkRenderer()->makeRedirectHeader( $page->getPageLanguage(), $target )
		);
		$output->addModuleStyles( [ 'mediawiki.action.view.redirectPage' ] );
		$output->setRawText( '' );
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
