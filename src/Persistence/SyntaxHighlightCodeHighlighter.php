<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Persistence;

use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use ProfessionalWiki\NativeMarkdown\Application\CodeHighlighter;

/**
 * Highlights fenced code blocks with Extension:SyntaxHighlight (Pygments), the
 * same highlighter wikitext `<syntaxhighlight>` uses, so a code block looks the
 * same whether it is written in Markdown or wikitext.
 */
final class SyntaxHighlightCodeHighlighter implements CodeHighlighter {

	public function highlight( string $code, string $language ): ?string {
		// Trim the same way SyntaxHighlight's own tag hook does, for parity with a
		// wikitext code block. No Parser is passed: there is none in ContentHandler
		// context, and MarkdownRenderer's per-render cap replaces the wikitext
		// parser's expensive-function budget.
		$trimmedCode = rtrim( trim( $code, "\n" ) );

		// Empty code has nothing to highlight; SyntaxHighlight would still wrap it
		// in an empty highlight div, so short-circuit to the default rendering.
		if ( $trimmedCode === '' ) {
			return null;
		}

		try {
			$status = SyntaxHighlight::highlight( $trimmedCode, $language );
		} catch ( \Exception ) {
			// unwrap() throws a RuntimeException on unexpected Pygments output.
			return null;
		}

		// A non-good status covers an unknown language, an exceeded size limit and
		// a Pygments invocation failure: all should degrade to default rendering.
		if ( !$status->isGood() ) {
			return null;
		}

		// highlight()'s contract makes the caller responsible for loading
		// ext.pygments; modules() and styleModules() below provide that.
		/** @psalm-suppress MixedAssignment Status::getValue() is untyped; guarded by is_string(). */
		$html = $status->getValue();

		return is_string( $html ) ? $html : null;
	}

	public function modules(): array {
		return [ 'ext.pygments.view' ];
	}

	public function styleModules(): array {
		return [ 'ext.pygments' ];
	}

}
