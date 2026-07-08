<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Persistence;

use HtmlArmor;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * Renders internal links via MediaWiki's LinkRenderer, which provides
 * existence styling (red links) and correct URLs.
 */
final class MediaWikiPageLinkRenderer implements PageLinkRenderer {

	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly LinkBatchFactory $linkBatchFactory
	) {
	}

	public function preloadExistence( array $titles ): void {
		$this->linkBatchFactory->newLinkBatch(
			array_map(
				static fn ( WikiTitle $title ) => new TitleValue( $title->namespace, $title->dbKey ),
				array_filter( $titles, static fn ( WikiTitle $title ) => $title->dbKey !== '' )
			)
		)->execute();
	}

	public function renderLink( WikiTitle $title, string $label ): string {
		return $this->linkRenderer->makeLink(
			new TitleValue( $title->namespace, $title->dbKey, $title->fragment, $title->interwiki ),
			$label
		);
	}

	public function renderLinkWithHtmlLabel( WikiTitle $title, string $labelHtml ): string {
		return $this->linkRenderer->makeLink(
			new TitleValue( $title->namespace, $title->dbKey, $title->fragment, $title->interwiki ),
			new HtmlArmor( $labelHtml )
		);
	}

}
