<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Decides whether a link destination is an external URL rather than a wiki
 * page title. Uses the wiki's configured URL protocols ($wgUrlProtocols) so a
 * namespaced title like `Help:Example` is not mistaken for a `help:` URL scheme,
 * exactly as MediaWiki distinguishes external links from titles in wikitext.
 */
final class ExternalUrlDetector {

	/**
	 * @param string[] $urlProtocols Protocol prefixes such as `https://` and `mailto:`
	 */
	public function __construct(
		private readonly array $urlProtocols
	) {
	}

	public function isExternalUrl( string $url ): bool {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) === 1 ) {
			return true;
		}

		foreach ( $this->urlProtocols as $protocol ) {
			if ( $protocol !== '' && stripos( $url, $protocol ) === 0 ) {
				return true;
			}
		}

		return false;
	}

}
