<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\FrontMatterGuard;
use ProfessionalWiki\NativeMarkdown\Tests\FrontMatterBombs;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\FrontMatterGuard
 */
class FrontMatterGuardTest extends TestCase {

	private function rejects( string $markdown ): bool {
		return ( new FrontMatterGuard() )->rejectedBlock( $markdown ) !== null;
	}

	public function testAcceptsSimpleKeyValueFrontMatter(): void {
		$this->assertFalse(
			$this->rejects( "---\ntitle: My Page\ntags: [one, two]\n---\n" )
		);
	}

	public function testAcceptsDocumentWithoutFrontMatter(): void {
		$this->assertFalse( $this->rejects( "Just a paragraph, no front matter.\n" ) );
	}

	public function testAcceptsAmpersandAndAsteriskInsidePlainScalars(): void {
		// "R&D" and "3 * 4" are ordinary scalar text, not YAML anchors/aliases;
		// a guard that counted raw & and * characters would wrongly reject them.
		$this->assertFalse(
			$this->rejects( "---\ntitle: R&D notes\nformula: 3 * 4 = 12\n---\n" )
		);
	}

	public function testAcceptsUrlValueWithSeveralQueryParameters(): void {
		// The & separators in a URL query sit against ordinary characters, not at
		// a node start, so they must not be mistaken for YAML anchors.
		$this->assertFalse(
			$this->rejects( "---\nlink: \"http://example.com/a?p=1&b=2&c=3&d=4&e=5\"\n---\n" )
		);
	}

	public function testAcceptsFrontMatterThatReusesASingleAnchor(): void {
		$this->assertFalse(
			$this->rejects( "---\nbase: &base 5\nsame: *base\n---\n" )
		);
	}

	public function testRejectsFrontMatterUsingManyAnchorsAndAliases(): void {
		$this->assertTrue( $this->rejects( FrontMatterBombs::aliasBombBlock() ) );
	}

	public function testRejectsColonAdjacentAliasBomb(): void {
		// Aliases placed immediately after a quoted key's colon are honored by
		// Symfony YAML; the guard must count them, not only the whitespace- and
		// bracket-adjacent ones.
		$this->assertTrue( $this->rejects( FrontMatterBombs::colonAdjacentAliasBombBlock() ) );
	}

	public function testRejectsOversizedFrontMatterBlock(): void {
		$oversized = "---\nnote: " . str_repeat( 'a', 128 * 1024 ) . "\n---\n";

		$this->assertTrue( $this->rejects( $oversized ) );
	}

}
