<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * AST node for an inline `{{...}}` call. Holds the raw wikitext (braces
 * included); the expanded HTML is filled in by a later pipeline stage and
 * stays null when no expander runs, degrading to escaped literal text.
 */
final class TemplateCallNode extends AbstractInline {

	public ?string $expandedHtml = null;

	public function __construct(
		public readonly string $wikitext
	) {
		parent::__construct();
	}

}
