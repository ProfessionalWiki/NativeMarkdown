<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\TemplateCall;
use ProfessionalWiki\NativeMarkdown\Application\TemplateExpander;

/**
 * Records the calls it receives and returns deterministic HTML embedding the
 * wikitext, so tests can assert the pipeline injects the expander's raw HTML and
 * distinguishes block from inline calls without a real MediaWiki parser.
 */
final class FakeTemplateExpander implements TemplateExpander {

	/** @var TemplateCall[] */
	public array $calls = [];

	public function expand( TemplateCall $call ): string {
		$this->calls[] = $call;

		$tag = $call->isBlock ? 'div' : 'span';

		return "<$tag class=\"fake-expanded\">" . htmlspecialchars( $call->wikitext ) . "</$tag>";
	}

}
