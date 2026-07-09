<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownLinkTargetResolver;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeWikiTitleParser;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownLinkTargetResolver
 */
class MarkdownLinkTargetResolverTest extends TestCase {

	private function newResolver(): MarkdownLinkTargetResolver {
		return new MarkdownLinkTargetResolver(
			new FakeWikiTitleParser(),
			new ExternalUrlDetector( [ '//', 'http://', 'https://', 'mailto:' ] )
		);
	}

	public function testPageNameResolvesToTitle(): void {
		$this->assertSame( 'Help:Example', $this->newResolver()->resolve( 'Help:Example' )?->prefixedText );
	}

	public function testSpacedPageNameResolvesToTitle(): void {
		$this->assertSame( 'Some Page', $this->newResolver()->resolve( 'Some Page' )?->prefixedText );
	}

	public function testExternalUrlDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'https://example.com/page' ) );
	}

	public function testMailtoDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'mailto:person@example.com' ) );
	}

	public function testJavascriptSchemeDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'javascript:alert(1)' ) );
	}

	public function testVbscriptSchemeDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'vbscript:msgbox(1)' ) );
	}

	public function testDataSchemeDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'data:text/html,payload' ) );
	}

	public function testFileNamespaceTargetResolvesDespiteFileSchemeLookalike(): void {
		$this->assertSame( 'File:Cat.png', $this->newResolver()->resolve( 'File:Cat.png' )?->prefixedText );
	}

	public function testTitleContainingSchemeWordResolvesBecauseMatchIsAnchored(): void {
		$this->assertSame( 'Metadata:Schema', $this->newResolver()->resolve( 'Metadata:Schema' )?->prefixedText );
	}

	public function testFragmentDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( '#Section' ) );
	}

	public function testRootRelativePathDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( '/wiki/Some_Page' ) );
	}

	public function testTargetRejectedByTitleParserDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( 'foo <bad> bar' ) );
	}

	public function testEmptyTargetDoesNotResolve(): void {
		$this->assertNull( $this->newResolver()->resolve( '' ) );
	}

}
