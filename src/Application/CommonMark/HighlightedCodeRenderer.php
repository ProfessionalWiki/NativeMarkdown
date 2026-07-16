<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders a fenced code block as the highlighter's trusted HTML when
 * MarkdownRenderer stashed some on the node. When nothing is stashed it returns
 * null, so league's default FencedCodeRenderer takes over and emits the plain
 * escaped `<pre><code>` block. Stateless: the highlighting decision is made in
 * the pipeline and only its result is read back here at render time.
 */
final class HighlightedCodeRenderer implements NodeRendererInterface {

	/**
	 * Data-bag key under which MarkdownRenderer stashes the highlighted HTML of a
	 * fenced code block, read back here at render time.
	 */
	public const HIGHLIGHTED_HTML_KEY = 'native_markdown_highlighted_html';

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): ?string {
		/** @var string|null $highlightedHtml */
		$highlightedHtml = $node->data->get( self::HIGHLIGHTED_HTML_KEY, null );

		return $highlightedHtml;
	}

}
