<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Persistence;

use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NativeMarkdown\Application\TemplateCall;
use ProfessionalWiki\NativeMarkdown\Application\TemplateExpander;

/**
 * Expands `{{...}}` calls by delegating to MediaWiki's wikitext parser, so
 * templates, parser functions, magic words, dependency tracking, and recursion
 * limits all come from core. One Parser is reused across a page's calls; each
 * child ParserOutput is kept and folded into the page's output by mergeInto(),
 * which is how the page gets reparsed when a transcluded template changes.
 *
 * Built fresh per page render (never memoized) because it carries that render's
 * page, options and revision, and accumulates that render's child outputs.
 */
final class MediaWikiTemplateExpander implements TemplateExpander {

	private ?Parser $parser = null;
	private ParserOptions $childOptions;

	/** @var ParserOutput[] */
	private array $childOutputs = [];

	public function __construct(
		private readonly ParserFactory $parserFactory,
		private readonly PageReference $page,
		ParserOptions $parserOptions,
		private readonly ?int $revisionId
	) {
		// A private copy so each child parse repoints its own option-usage watcher
		// instead of the one core registered on the page's ParserOptions (which
		// points at the page ParserOutput). The options the templates read still
		// reach the page output through mergeInternalMetaDataFrom() in mergeInto().
		$this->childOptions = clone $parserOptions;
	}

	public function expand( TemplateCall $call ): string {
		$output = $this->parser()->parse(
			$call->wikitext,
			$this->page,
			$this->childOptions,
			true,
			true,
			$this->revisionId
		);

		$this->childOutputs[] = $output;

		$html = $output->runOutputPipeline(
			$this->childOptions,
			[
				// The page composes its own wrapper, ToC and section edit links;
				// keeping the fragment free of them avoids per-user, per-skin
				// artifacts being frozen into the page's parser cache.
				'unwrap' => true,
				'allowTOC' => false,
				'enableSectionEditLinks' => false,
			]
		)->getContentHolderText();

		// A block call keeps the parser's block HTML; an inline call drops the
		// paragraph the parser wraps loose text in so it flows within the line.
		return $call->isBlock ? $html : Parser::stripOuterParagraph( $html );
	}

	/**
	 * Folds every child parse's metadata into the page's output using the same
	 * merges core uses to combine content slots (RevisionRenderer), plus the
	 * cache expiry no merge method carries, so templatelinks/imagelinks/
	 * categorylinks, the modules templates need, cache-varying options and TTLs
	 * are all recorded against the page.
	 */
	public function mergeInto( ParserOutput $pageOutput ): void {
		foreach ( $this->childOutputs as $childOutput ) {
			$pageOutput->mergeInternalMetaDataFrom( $childOutput );
			$pageOutput->mergeTrackingMetaDataFrom( $childOutput );
			$pageOutput->mergeHtmlMetaDataFrom( $childOutput );
			$pageOutput->updateCacheExpiry( $childOutput->getCacheExpiry() );
		}
	}

	private function parser(): Parser {
		$this->parser ??= $this->parserFactory->create();

		return $this->parser;
	}

}
