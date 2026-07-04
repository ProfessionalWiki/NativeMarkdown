<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Persistence;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleParser;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitleParser;

/**
 * Adapts MediaWiki's TitleParser to the pure WikiTitleParser port.
 */
final class MediaWikiTitleParser implements WikiTitleParser {

	public function __construct(
		private readonly TitleParser $titleParser,
		private readonly TitleFormatter $titleFormatter
	) {
	}

	public function parse( string $titleText ): ?WikiTitle {
		try {
			$title = $this->titleParser->parseTitle( $titleText );
		} catch ( MalformedTitleException ) {
			return null;
		}

		if ( $title->getDBkey() === '' && $title->getFragment() === '' ) {
			return null;
		}

		return new WikiTitle(
			namespace: $title->getNamespace(),
			dbKey: $title->getDBkey(),
			prefixedText: $this->prefixedTextOf( $title ),
			fragment: $title->getFragment(),
			interwiki: $title->getInterwiki()
		);
	}

	/**
	 * Same-page anchors get no prefixed text, so they label as `#Fragment`.
	 * Interwiki fragment-only titles keep their prefix: `[[wikipedia:#History]]`
	 * leaves the wiki, so its default label must say where it goes.
	 */
	private function prefixedTextOf( LinkTarget $title ): string {
		if ( $title->getDBkey() === '' && $title->getInterwiki() === '' ) {
			return '';
		}

		return $this->titleFormatter->getPrefixedText( $title );
	}

}
