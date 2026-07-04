<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbedRenderer;

/**
 * Renders file embed nodes via the wiki's file rendering. Embeds inside
 * markdown links never reach this renderer: they degrade to plain text
 * during link resolution, since nested anchors are invalid HTML.
 */
final class FileEmbedNodeRenderer implements NodeRendererInterface {

	public function __construct(
		private readonly FileEmbedRenderer $fileEmbedRenderer
	) {
	}

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		if ( !$node instanceof FileEmbedNode ) {
			return '';
		}

		return $this->fileEmbedRenderer->renderEmbed( $node->embed );
	}

}
