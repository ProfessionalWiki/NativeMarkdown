<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Decorates league's link renderer to mark external links the way MediaWiki
 * does: an "external" class and (unless the wiki disables it) rel=nofollow.
 */
final class ExternalLinkRenderer implements NodeRendererInterface, ConfigurationAwareInterface {

	private LinkRenderer $innerRenderer;

	public function __construct(
		private readonly bool $noFollowExternalLinks
	) {
		$this->innerRenderer = new LinkRenderer();
	}

	public function setConfiguration( ConfigurationInterface $configuration ): void {
		$this->innerRenderer->setConfiguration( $configuration );
	}

	/**
	 * @return \Stringable|string
	 */
	public function render( Node $node, ChildNodeRendererInterface $childRenderer ) {
		if ( !$node instanceof Link ) {
			return '';
		}

		$rendered = $this->innerRenderer->render( $node, $childRenderer );

		if ( $rendered instanceof HtmlElement && self::isExternalUrl( $node->getUrl() ) ) {
			$rendered->setAttribute( 'class', 'external' );

			if ( $this->noFollowExternalLinks ) {
				$rendered->setAttribute( 'rel', 'nofollow' );
			}
		}

		return $rendered;
	}

	public static function isExternalUrl( string $url ): bool {
		return preg_match( '#^(?:[a-z][a-z0-9+.-]*:)?//#i', $url ) === 1;
	}

}
