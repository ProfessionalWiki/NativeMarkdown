<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer;
use ProfessionalWiki\NativeMarkdown\Application\NoOpCodeHighlighter;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeFileEmbedRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakePageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeWikiTitleParser;

/**
 * States the XSS-safety guarantee directly: no attack input yields active
 * content. This complements the exact-output fixtures (tests/fixtures/xss-*)
 * by asserting the security property in a way a careless fixture update
 * cannot silently break.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\ImageLinkRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\MarkdownLinkRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector
 */
class XssSafetyTest extends TestCase {

	/**
	 * @dataProvider attackVectorProvider
	 */
	public function testAttackVectorProducesNoActiveContent( string $markdown ): void {
		$this->assertNoActiveContent( $this->render( $markdown ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function attackVectorProvider(): iterable {
		yield 'raw script block' => [ "<script>alert(1)</script>\n\ntext" ];
		yield 'inline event handler' => [ 'Text <img src=x onerror=alert(1)> here' ];
		yield 'javascript link' => [ '[click](javascript:alert(1))' ];
		yield 'uppercase javascript link' => [ '[click](JavaScript:alert(1))' ];
		yield 'vbscript link' => [ '[click](vbscript:msgbox(1))' ];
		yield 'data html link' => [ '[x](data:text/html,<script>alert(1)</script>)' ];
		yield 'entity encoded javascript link' => [ '[x](javascript&#58;alert(1))' ];
		yield 'tab obfuscated javascript link' => [ "[x](java\tscript:alert(1))" ];
		yield 'spaced javascript link' => [ '[x](javascript: alert(1))' ];
		yield 'spaced vbscript link' => [ '[x](vbscript: msgbox 1)' ];
		yield 'nested image in unsafe link' => [ '[![logo](https://e.com/l.png)](javascript:alert(1))' ];
		yield 'wikilink label html' => [ '[[Some Page|<img src=x onerror=alert(1)>]]' ];
		yield 'wikilink target breakout' => [ '[[Some Page"><script>alert(1)</script>]]' ];
		yield 'heading html' => [ '# <script>alert(1)</script>' ];
		yield 'autolink javascript scheme' => [ 'see javascript:alert(1) inline' ];
		yield 'image alt breakout' => [ '![x"onerror="alert(1)](https://e.com/i.png)' ];
		yield 'unsafe image scheme' => [ '![x](javascript:alert(1))' ];
	}

	/**
	 * @dataProvider embeddedImageAttackVectorProvider
	 */
	public function testAttackVectorProducesNoActiveContentWhenExternalImagesAllowed( string $markdown ): void {
		$this->assertNoActiveContent( $this->render( $markdown, allowExternalImages: true ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function embeddedImageAttackVectorProvider(): iterable {
		yield 'image alt breakout' => [ '![breakout"onerror="alert(1)](https://e.com/i.png)' ];
		yield 'image url breakout' => [ '![alt](https://e.com/i.png"onload="alert(1))' ];
		yield 'unsafe image scheme' => [ '![x](javascript:alert(1))' ];
	}

	/**
	 * Active content = an unescaped script tag, a dangerous URL scheme sitting in
	 * an href/src attribute, or an inline event handler on a real tag. Escaped
	 * text may legitimately contain these character sequences, so the checks are
	 * written to match only live markup, never escaped source.
	 */
	private function assertNoActiveContent( string $html ): void {
		$this->assertDoesNotMatchRegularExpression( '/<script\b/i', $html, "unescaped <script> in: $html" );
		$this->assertDoesNotMatchRegularExpression(
			'#(?:href|src)\s*=\s*["\']?\s*(?:javascript|vbscript|data:text/html)#i',
			$html,
			"dangerous URL scheme in an attribute in: $html"
		);
		$this->assertDoesNotMatchRegularExpression(
			'/<[a-z][^>]*\son[a-z]+\s*=/i',
			$html,
			"inline event handler on a live tag in: $html"
		);
	}

	private function render( string $markdown, bool $allowExternalImages = false ): string {
		$renderer = new MarkdownRenderer(
			titleParser: new FakeWikiTitleParser(),
			pageLinkRenderer: new FakePageLinkRenderer(),
			fileEmbedRenderer: new FakeFileEmbedRenderer(),
			codeHighlighter: new NoOpCodeHighlighter(),
			allowExternalImages: $allowExternalImages,
			maxNestingLevel: 100,
			tocPlaceholderHtml: null,
			noFollowExternalLinks: true,
			templateTransclusion: false,
			urlProtocols: [ '//', 'http://', 'https://', 'ftp://', 'mailto:' ]
		);

		return $renderer->render( $markdown, generateHtml: true )->html;
	}

}
