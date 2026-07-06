<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer;
use ProfessionalWiki\NativeMarkdown\Application\RedirectSyntax;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiFileEmbedRenderer;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiPageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiTemplateExpander;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiTitleParser;

/**
 * Composition root. All object graph wiring happens here; MediaWikiServices
 * access is confined to this class and the EntryPoints layer.
 */
final class NativeMarkdownExtension {

	public const CONTENT_MODEL = 'markdown';

	private const MAX_NESTING_LEVEL = 100;

	private ?MarkdownRenderer $markdownRenderer = null;
	private ?MediaWikiServices $rendererServices = null;

	public static function getInstance(): self {
		/** @var self|null $instance */
		static $instance = null;
		$instance ??= new self();
		return $instance;
	}

	public function getMarkdownRenderer(): MarkdownRenderer {
		// The renderer wraps service objects, so it must be rebuilt when the
		// service container is reset (config changes, test isolation).
		$services = MediaWikiServices::getInstance();

		if ( $this->markdownRenderer === null || $this->rendererServices !== $services ) {
			$this->markdownRenderer = $this->newMarkdownRenderer();
			$this->rendererServices = $services;
		}

		return $this->markdownRenderer;
	}

	private function newMarkdownRenderer(): MarkdownRenderer {
		$services = MediaWikiServices::getInstance();

		return new MarkdownRenderer(
			titleParser: new MediaWikiTitleParser(
				$services->getTitleParser(),
				$services->getTitleFormatter()
			),
			pageLinkRenderer: new MediaWikiPageLinkRenderer(
				$services->getLinkRenderer(),
				$services->getLinkBatchFactory()
			),
			fileEmbedRenderer: new MediaWikiFileEmbedRenderer(
				$services->getRepoGroup(),
				$services->getLinkRenderer(),
				$this->defaultThumbnailWidth( $services )
			),
			allowExternalImages: (bool)$services->getMainConfig()->get( 'NativeMarkdownAllowExternalImages' ),
			maxNestingLevel: self::MAX_NESTING_LEVEL,
			tocPlaceholderHtml: Parser::TOC_PLACEHOLDER,
			noFollowExternalLinks: (bool)$services->getMainConfig()->get( 'NoFollowLinks' ),
			templateTransclusion: (bool)$services->getMainConfig()->get( 'NativeMarkdownTemplateTransclusion' )
		);
	}

	/**
	 * The width a bare `thumb` embed renders at, mirroring how the wikitext
	 * parser sizes a thumbnail: the default thumbnail preference indexed into
	 * the configured thumbnail sizes.
	 */
	private function defaultThumbnailWidth( MediaWikiServices $services ): int {
		$thumbLimits = (array)$services->getMainConfig()->get( 'ThumbLimits' );
		$defaultThumbSize = (int)$services->getUserOptionsLookup()->getDefaultOption( 'thumbsize' );

		return (int)( $thumbLimits[$defaultThumbSize] ?? 300 );
	}

	/**
	 * The expander a single page render uses to transclude its template calls,
	 * or null when transclusion is disabled. Built per render because it carries
	 * that render's page, options and revision.
	 */
	public function newTemplateExpander(
		PageReference $page,
		ParserOptions $parserOptions,
		?int $revisionId
	): ?MediaWikiTemplateExpander {
		$services = MediaWikiServices::getInstance();

		if ( !$services->getMainConfig()->get( 'NativeMarkdownTemplateTransclusion' ) ) {
			return null;
		}

		return new MediaWikiTemplateExpander(
			parserFactory: $services->getParserFactory(),
			page: $page,
			parserOptions: $parserOptions,
			revisionId: $revisionId
		);
	}

	public function newRedirectSyntax(): RedirectSyntax {
		return new RedirectSyntax(
			magicWordSynonyms: MediaWikiServices::getInstance()
				->getMagicWordFactory()
				->get( 'redirect' )
				->getSynonyms()
		);
	}

	public function newMarkdownDefaultPolicy(): MarkdownDefaultPolicy {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		return new MarkdownDefaultPolicy(
			namespaces: array_map( 'intval', (array)$config->get( 'NativeMarkdownNamespaces' ) ),
			everywhere: (bool)$config->get( 'NativeMarkdownEverywhere' ),
			suffixDetection: (bool)$config->get( 'NativeMarkdownSuffixDetection' )
		);
	}

}
