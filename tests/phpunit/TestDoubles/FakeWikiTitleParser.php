<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitleParser;

/**
 * Pure-PHP stand-in for the MediaWiki TitleParser adapter. Understands just enough
 * title syntax for the pipeline tests: Category:/File: prefixes (case-insensitive,
 * like MediaWiki), the "wikipedia" interwiki prefix, #fragments, first-letter
 * capitalization (MediaWiki's $wgCapitalLinks default), and rejection of
 * characters MediaWiki considers invalid in titles.
 */
final class FakeWikiTitleParser implements WikiTitleParser {

	private const MAIN_NAMESPACE = 0;
	private const INTERWIKI_PREFIX = 'wikipedia';

	public function parse( string $titleText ): ?WikiTitle {
		[ $pageText, $fragment ] = $this->splitFragment( $titleText );

		if ( $pageText === '' && $fragment !== '' ) {
			return new WikiTitle(
				namespace: self::MAIN_NAMESPACE,
				dbKey: '',
				prefixedText: '',
				fragment: $fragment,
				interwiki: ''
			);
		}

		if ( $pageText === '' || preg_match( '/[<>\[\]{}|]/', $pageText ) === 1 ) {
			return null;
		}

		if ( stripos( $pageText, self::INTERWIKI_PREFIX . ':' ) === 0 ) {
			$text = trim( substr( $pageText, strlen( self::INTERWIKI_PREFIX ) + 1 ) );

			return new WikiTitle(
				namespace: self::MAIN_NAMESPACE,
				dbKey: str_replace( ' ', '_', $text ),
				prefixedText: self::INTERWIKI_PREFIX . ':' . $text,
				fragment: $fragment,
				interwiki: self::INTERWIKI_PREFIX
			);
		}

		[ $namespace, $prefix, $text ] = $this->splitNamespace( $pageText );
		$text = $this->capitalizeFirst( $text );

		return new WikiTitle(
			namespace: $namespace,
			dbKey: str_replace( ' ', '_', $text ),
			prefixedText: $prefix . $text,
			fragment: $fragment,
			interwiki: ''
		);
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private function splitFragment( string $titleText ): array {
		$parts = explode( '#', $titleText, 2 );
		return [ trim( $parts[0] ), trim( $parts[1] ?? '' ) ];
	}

	/**
	 * @return array{0: int, 1: string, 2: string}
	 */
	private function splitNamespace( string $pageText ): array {
		$namespacesByPrefix = [
			'Category' => WikiTitle::CATEGORY_NAMESPACE,
			'File' => WikiTitle::FILE_NAMESPACE,
		];

		foreach ( $namespacesByPrefix as $prefix => $namespace ) {
			if ( stripos( $pageText, $prefix . ':' ) === 0 ) {
				return [ $namespace, $prefix . ':', trim( substr( $pageText, strlen( $prefix ) + 1 ) ) ];
			}
		}

		return [ self::MAIN_NAMESPACE, '', $pageText ];
	}

	private function capitalizeFirst( string $text ): string {
		if ( $text === '' ) {
			return '';
		}

		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}

}
