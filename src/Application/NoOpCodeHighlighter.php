<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * Highlights nothing: used when the wiki has no syntax highlighter installed,
 * so every fenced block keeps the default escaped rendering.
 */
final class NoOpCodeHighlighter implements CodeHighlighter {

	public function highlight( string $code, string $language ): ?string {
		return null;
	}

	public function modules(): array {
		return [];
	}

	public function styleModules(): array {
		return [];
	}

}
