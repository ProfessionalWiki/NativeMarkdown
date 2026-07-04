<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Block node marking where the wiki's table of contents belongs.
 */
final class TocPlaceholder extends AbstractBlock {

	public function __construct(
		public readonly string $placeholderHtml
	) {
		parent::__construct();
	}

}
