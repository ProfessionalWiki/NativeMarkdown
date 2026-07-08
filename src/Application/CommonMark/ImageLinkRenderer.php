<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\CommonMark\Util\Xml;
use ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector;

/**
 * Renders external images as plain links instead of embedding them,
 * mirroring MediaWiki's default posture towards external images.
 */
final class ImageLinkRenderer implements NodeRendererInterface {

	public function __construct(
		private readonly ExternalUrlDetector $externalUrlDetector,
		private readonly bool $noFollowExternalLinks
	) {
	}

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		if ( !$node instanceof Image ) {
			return '';
		}

		$label = $this->labelFor( $node );

		if ( RegexHelper::isLinkPotentiallyUnsafe( $node->getUrl() ) || $this->isInsideLink( $node ) ) {
			return Xml::escape( $label );
		}

		return (string)$this->newLinkElement( $node->getUrl(), $label );
	}

	private function newLinkElement( string $url, string $label ): HtmlElement {
		$attributes = [ 'href' => $url ];

		if ( $this->externalUrlDetector->isExternalUrl( $url ) ) {
			$attributes['class'] = 'external';

			if ( $this->noFollowExternalLinks ) {
				$attributes['rel'] = 'nofollow';
			}
		}

		return new HtmlElement( 'a', $attributes, Xml::escape( $label ) );
	}

	private function labelFor( Image $node ): string {
		$altText = StringContainerHelper::getChildText( $node, [ RawMarkupContainerInterface::class ] );

		return $altText === '' ? $node->getUrl() : $altText;
	}

	private function isInsideLink( Node $node ): bool {
		for ( $ancestor = $node->parent(); $ancestor !== null; $ancestor = $ancestor->parent() ) {
			if ( $ancestor instanceof Link ) {
				return true;
			}
		}

		return false;
	}

}
