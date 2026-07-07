<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests;

/**
 * Bounded stand-ins for YAML alias bombs, shared across the guard, renderer and
 * content-handler tests. Each has more than a handful of anchor/alias markers so
 * FrontMatterGuard rejects it, yet expands to only a few dozen elements, so the
 * tests never build a huge structure.
 */
final class FrontMatterBombs {

	private const FLOW_SEQUENCE_ALIASES =
		"a: &a [boom, boom, boom, boom, boom]\n"
		. "b: &b [*a, *a, *a, *a, *a]\n"
		. "c: &c [*b, *b, *b, *b, *b]";

	public static function aliasBombBlock(): string {
		return "---\n" . self::FLOW_SEQUENCE_ALIASES . "\n---\n";
	}

	/**
	 * A bomb whose block contains a decoy "---" line that is not a real closing
	 * delimiter (no newline directly after it), exercising the reject path's
	 * boundary handling.
	 */
	public static function aliasBombBlockWithDecoyDelimiter(): string {
		return "---\n" . self::FLOW_SEQUENCE_ALIASES . "\n---DECOY\n---\n";
	}

	/**
	 * The same threat expressed as a JSON-style flow mapping whose aliases sit
	 * immediately after a quoted key's colon. Symfony honors these, but a marker
	 * scan that does not treat ":" as a node-start predecessor undercounts them.
	 */
	public static function colonAdjacentAliasBombBlock(): string {
		return "---\n"
			. "a: &a boom\n"
			. 'b: &b {"1":*a,"2":*a,"3":*a,"4":*a,"5":*a,"6":*a,"7":*a,"8":*a,"9":*a}' . "\n"
			. "---\n";
	}

}
