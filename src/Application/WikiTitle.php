<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * A validated wiki page title, as produced by a WikiTitleParser.
 */
final class WikiTitle {

	// Mirror NS_CATEGORY and NS_FILE from MediaWiki's Defines.php. These IDs are
	// fixed for all MediaWiki installations, so the pure layer can rely on them.
	public const CATEGORY_NAMESPACE = 14;
	public const FILE_NAMESPACE = 6;

	public function __construct(
		public readonly int $namespace,
		public readonly string $dbKey,
		public readonly string $prefixedText,
		public readonly string $fragment = '',
		public readonly string $interwiki = ''
	) {
	}

	public function isCategory(): bool {
		return $this->namespace === self::CATEGORY_NAMESPACE;
	}

	public function isFile(): bool {
		return $this->namespace === self::FILE_NAMESPACE;
	}

	public function isInterwiki(): bool {
		return $this->interwiki !== '';
	}

	/**
	 * Fragment-only targets like `[[#Section]]`, which link within the current page.
	 */
	public function isSamePageAnchor(): bool {
		return $this->dbKey === '' && $this->fragment !== '' && $this->interwiki === '';
	}

	public function textWithFragment(): string {
		return $this->prefixedText . ( $this->fragment === '' ? '' : '#' . $this->fragment );
	}

}
