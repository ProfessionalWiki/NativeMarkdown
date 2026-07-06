<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

/**
 * Matching and measuring `{{...}}` brace spans, shared by the inline and block
 * template-call parsers so they agree on what a balanced call is.
 */
final class TemplateBraces {

	// Balanced `{{...}}` at the start of the string, allowing nested calls on a
	// single line. The recursion refers to group 1 (not the whole pattern) so the
	// `\A` anchor stays outside it; the atomic group bounds backtracking so
	// adversarial brace runs fail fast to no match.
	private const LEADING_CALL = '/\A(\{\{(?>[^{}\n]++|(?1))*\}\})/';

	/**
	 * The balanced `{{...}}` call the text begins with, or null when it does not
	 * start with one (unbalanced, a `{{{` run, or a line break inside the braces).
	 */
	public static function matchLeadingCall( string $text ): ?string {
		if ( preg_match( self::LEADING_CALL, $text, $matches ) === 1 ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Net change in brace nesting across the text: `{{` opens a level, `}}` closes
	 * one. Stray single braces are ignored, which is enough to find where an outer
	 * call balances as its lines are accumulated.
	 */
	public static function depthDelta( string $text ): int {
		$delta = 0;
		$length = strlen( $text );

		for ( $i = 0; $i < $length - 1; ) {
			$pair = $text[$i] . $text[$i + 1];

			if ( $pair === '{{' ) {
				$delta++;
				$i += 2;
			} elseif ( $pair === '}}' ) {
				$delta--;
				$i += 2;
			} else {
				$i++;
			}
		}

		return $delta;
	}

}
