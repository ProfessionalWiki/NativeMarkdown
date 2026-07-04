<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Node\Inline\AbstractInline;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbed;

/**
 * AST node for a `[[File:...]]` embed, produced when a wikilink node
 * resolves to a file title without a leading colon.
 */
final class FileEmbedNode extends AbstractInline {

	public function __construct(
		public readonly FileEmbed $embed
	) {
		parent::__construct();
	}

}
