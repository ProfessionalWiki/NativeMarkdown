<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Port for turning a fenced code block into syntax-highlighted HTML.
 *
 * Implementations delegate to whatever highlighter the wiki has available.
 * When a block cannot be highlighted, highlight() returns null and the caller
 * keeps the default escaped `<pre><code>` rendering, so highlighting is always
 * an enhancement over, never a replacement for, plain code blocks.
 */
interface CodeHighlighter {

	/**
	 * @param string $code The raw code, exactly as written between the fences.
	 * @param string $language The first word of the fence's info string.
	 * @return string|null Ready-to-embed HTML for the highlighted block, or null
	 *   when the code cannot be highlighted (unknown language, highlighter
	 *   unavailable or failing, size limit exceeded, empty code).
	 */
	public function highlight( string $code, string $language ): ?string;

	/**
	 * ResourceLoader modules the highlighted HTML needs to be interactive.
	 *
	 * @return string[]
	 */
	public function modules(): array;

	/**
	 * ResourceLoader style modules the highlighted HTML needs to look right.
	 *
	 * @return string[]
	 */
	public function styleModules(): array;

}
