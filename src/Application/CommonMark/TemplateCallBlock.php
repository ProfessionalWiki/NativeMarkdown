<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * AST node for a block-level `{{...}}` call standing on its own line(s), such
 * as an infobox. The continue parser fills in the raw wikitext and whether the
 * braces balanced; an unbalanced call (never closed before end of input)
 * degrades to escaped literal text instead of being expanded.
 */
final class TemplateCallBlock extends AbstractBlock {

	public string $wikitext = '';
	public bool $balanced = false;
	public ?string $expandedHtml = null;

}
