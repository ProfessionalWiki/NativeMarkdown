<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Inline\AbstractInline;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;

/**
 * AST node for `[[...]]` wikilink syntax. Holds the raw pieces from parsing;
 * resolution against real wiki titles happens in a later pipeline stage.
 */
final class WikiLinkNode extends AbstractInline {

	public ?WikiTitle $resolvedTitle = null;

	public function __construct(
		public readonly string $target,
		public readonly ?string $label,
		public readonly bool $hasLeadingColon,
		public readonly string $rawSource
	) {
		parent::__construct();
	}

	/**
	 * Pipe-less links keep the target text exactly as typed (colon-stripped and
	 * trimmed by the parser), matching how MediaWiki wikitext and Obsidian render
	 * them. The resolved title drives the href and link registration, not the label.
	 */
	public function displayLabel(): string {
		if ( $this->label !== null && $this->label !== '' ) {
			return $this->label;
		}

		return $this->target;
	}

}
