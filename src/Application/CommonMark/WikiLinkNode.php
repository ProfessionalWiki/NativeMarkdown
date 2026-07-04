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

	public function displayLabel(): string {
		if ( $this->label !== null && $this->label !== '' ) {
			return $this->label;
		}

		if ( $this->resolvedTitle !== null ) {
			return $this->resolvedTitle->textWithFragment();
		}

		return $this->target;
	}

}
