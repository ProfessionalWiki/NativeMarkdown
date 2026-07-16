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

	private const CONTENT_STYLES_MODULE = 'ext.nativeMarkdown.content';

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

		$extension = NativeMarkdownExtension::getInstance();

		// A redirect page renders the content after its `#REDIRECT [[Target]]`
		// line (redirect categories and the like), the same as the wikitext
		// handler; the redirect view is then added on top.
		$redirectTarget = $this->resolveRedirectTarget( $text );
		$markdown = $redirectTarget === null
			? $text
			: $extension->newRedirectSyntax()->extractTrailingContent( $text );

		$templateExpander = $extension->newTemplateExpander(
			$cpoParams->getPage(),
			$cpoParams->getParserOptions(),
			$cpoParams->getRevId()
		);

		$rendered = $extension->getMarkdownRenderer()->render(
			$markdown,
			$cpoParams->getGenerateHtml(),
			$templateExpander
		);

		$output->setFromParserOptions( $cpoParams->getParserOptions() );

		$this->registerLinks( $output, $rendered );
		$this->registerCategories( $output, $rendered );
		$this->registerFiles( $output, $rendered );
		$this->registerExternalLinks( $output, $rendered );
		$this->registerFrontMatter( $output, $rendered );
		$this->registerModules( $output, $rendered );

		if ( $templateExpander !== null ) {
			$templateExpander->mergeInto( $output );
		}

		// Last, so the page's table of contents comes from its own markdown
		// headings, not from headings inside transcluded templates.
		$this->registerSections( $output, $rendered );

		$output->setRawText( $cpoParams->getGenerateHtml() ? $rendered->html : null );

		if ( $redirectTarget !== null ) {
			$this->addRedirectIndicator( $output, $cpoParams, $redirectTarget );
		}
	}

	/**
	 * Adds MediaWiki's redirect view (the arrow to the target) above the already
	 * rendered body, and records the target as a link so page moves,
	 * WhatLinksHere and the redirect table all see it. Mirrors the wikitext
	 * handler, which likewise renders a redirect page's trailing content and
	 * shows the redirect indicator on top of it.
	 */
	private function addRedirectIndicator( ParserOutput $output, ContentParseParams $cpoParams, Title $target ): void {
		$output->addLink( $target );

		if ( !$cpoParams->getGenerateHtml() ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$page = $services->getTitleFactory()->newFromPageReference( $cpoParams->getPage() );

		$output->setRedirectHeader(
			$services->getLinkRenderer()->makeRedirectHeader( $page->getPageLanguage(), $target )
		);
		$output->addModuleStyles( [ 'mediawiki.action.view.redirectPage' ] );
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

	/**
	 * Loads the base styles every markdown page needs, since skins do not style
	 * CommonMark's markup correctly on their own, plus the modules the render
	 * itself needs, such as the highlighter's for highlighted code blocks.
	 */
	private function registerModules( ParserOutput $output, RenderedMarkdown $rendered ): void {
		$output->addModuleStyles( array_merge( [ self::CONTENT_STYLES_MODULE ], $rendered->styleModules ) );
		$output->addModules( $rendered->modules );
	}

	/**
	 * Sets the table of contents from the page's own markdown headings,
	 * authoritatively: it overwrites any TOCData and SHOW_TOC flag a transcluded
	 * template merged in, so the contents list never lists template headings.
	 */
	private function registerSections( ParserOutput $output, RenderedMarkdown $rendered ): void {
		$output->setTOCData( $this->buildTocData( $rendered->sections ) );
		$output->setOutputFlag(
			ParserOutputFlags::SHOW_TOC,
			count( $rendered->sections ) >= self::TOC_SECTION_THRESHOLD
		);
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
