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
use ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector;
use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * Renders markdown `[label](target)` links. A target that resolved to a wiki
 * page (MarkdownRenderer marks the node with a WikiTitle) renders as an internal
 * link through the wiki's link renderer, so red/blue styling and link tables work
 * the same as for `[[wikilinks]]`. Remaining links go through league's renderer,
 * with external URLs marked the way MediaWiki does: an "external" class and
 * (unless the wiki disables it) rel=nofollow.
 */
final class MarkdownLinkRenderer implements NodeRendererInterface, ConfigurationAwareInterface {

	/**
	 * Data-bag key under which MarkdownRenderer stashes the resolved title of an
	 * internal link, read back here at render time.
	 */
	public const INTERNAL_TITLE_KEY = 'native_markdown_internal_title';

	private LinkRenderer $innerRenderer;

	public function __construct(
		private readonly PageLinkRenderer $pageLinkRenderer,
		private readonly ExternalUrlDetector $externalUrlDetector,
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

		/** @var WikiTitle|null $resolvedTitle */
		$resolvedTitle = $node->data->get( self::INTERNAL_TITLE_KEY, null );

		if ( $resolvedTitle instanceof WikiTitle ) {
			return $this->pageLinkRenderer->renderLinkWithHtmlLabel(
				$resolvedTitle,
				$childRenderer->renderNodes( $node->children() )
			);
		}

		$rendered = $this->innerRenderer->render( $node, $childRenderer );

		if ( $rendered instanceof HtmlElement && $this->externalUrlDetector->isExternalUrl( $node->getUrl() ) ) {
			$rendered->setAttribute( 'class', 'external' );

			if ( $this->noFollowExternalLinks ) {
				$rendered->setAttribute( 'rel', 'nofollow' );
			}
		}

		return $rendered;
	}

}
