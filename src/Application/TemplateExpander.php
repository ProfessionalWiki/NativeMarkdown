<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Turns a `{{...}}` call into safe, ready-to-embed HTML.
 *
 * Expansion has a side effect the caller relies on but cannot see: the
 * implementation records the wiki dependencies of the call (templates,
 * links, images, categories) against the render in progress, so the page
 * is reparsed when a transcluded template changes. Infrastructure failures
 * propagate rather than degrading to literal text, matching MarkdownRenderer:
 * caching a page with empty dependency tables would break that invalidation.
 */
interface TemplateExpander {

	/**
	 * @return string Safe HTML. Block calls yield block-level HTML; inline
	 *   calls yield inline HTML with no surrounding paragraph.
	 */
	public function expand( TemplateCall $call ): string;

}
