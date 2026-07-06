<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;

/**
 * Renders template-call nodes as the expander's trusted HTML. When no expansion
 * ran (feature off, no page context, or an unbalanced block) the raw wikitext
 * is shown escaped, mirroring how unresolved wikilinks degrade to literal text.
 */
final class TemplateCallRenderer implements NodeRendererInterface {

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		if ( $node instanceof TemplateCallNode ) {
			return $node->expandedHtml ?? Xml::escape( $node->wikitext );
		}

		if ( $node instanceof TemplateCallBlock ) {
			// The HTML renderer adds the trailing block separator itself.
			return $node->expandedHtml ?? '<p>' . Xml::escape( $node->wikitext ) . '</p>';
		}

		return '';
	}

}
