<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\FileEmbed;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbedRenderer;

/**
 * Renders file embeds in a deterministic shape so tests can assert exact
 * pipeline output without depending on MediaWiki's file rendering HTML.
 */
final class FakeFileEmbedRenderer implements FileEmbedRenderer {

	/** @var array<int, \ProfessionalWiki\NativeMarkdown\Application\WikiTitle[]> */
	public array $preloadCalls = [];

	public function preloadFiles( array $titles ): void {
		$this->preloadCalls[] = $titles;
	}

	public function renderEmbed( FileEmbed $embed ): string {
		return '<img data-fake-file="' . htmlspecialchars( $embed->title->dbKey, ENT_QUOTES ) . '"'
			. ( $embed->width === null ? '' : ' width="' . $embed->width . '"' )
			. ( $embed->altText === null ? '' : ' alt="' . htmlspecialchars( $embed->altText, ENT_QUOTES ) . '"' )
			. ( $embed->caption === null ? '' : ' data-caption="' . htmlspecialchars( $embed->caption, ENT_QUOTES ) . '"' )
			. '>';
	}

	public function modules(): array {
		return [ 'test.file.media' ];
	}

}
