<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;
use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;

/**
 * Renders resolved wikilink nodes via the wiki's link renderer.
 * Unresolved nodes (which should have been replaced earlier) degrade to their raw source.
 */
final class WikiLinkNodeRenderer implements NodeRendererInterface {

	public function __construct(
		private readonly PageLinkRenderer $pageLinkRenderer
	) {
	}

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		if ( !$node instanceof WikiLinkNode ) {
			return '';
		}

		if ( $node->resolvedTitle === null ) {
			return Xml::escape( $node->rawSource );
		}

		return $this->pageLinkRenderer->renderLink( $node->resolvedTitle, $node->displayLabel() );
	}

}
