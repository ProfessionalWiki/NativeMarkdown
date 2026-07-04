<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders the ToC placeholder node as the raw placeholder HTML the wiki gave us.
 */
final class TocPlaceholderRenderer implements NodeRendererInterface {

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		return $node instanceof TocPlaceholder ? $node->placeholderHtml : '';
	}

}
