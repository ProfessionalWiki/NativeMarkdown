<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeFileEmbedRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakePageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeWikiTitleParser;

/**
 * Renders every tests/fixtures/*.md through the pure pipeline and asserts the
 * output equals the matching *.html file. New rendering cases (and especially
 * XSS vectors) are added by dropping a .md/.html pair into tests/fixtures/.
 *
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer
 */
class FixtureRenderingTest extends TestCase {

	private const FIXTURE_DIR = __DIR__ . '/../../fixtures';

	/**
	 * @dataProvider fixtureProvider
	 */
	public function testFixtureRendersToExpectedHtml( string $markdown, string $expectedHtml ): void {
		$this->assertSame( trim( $expectedHtml ), trim( $this->render( $markdown ) ) );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function fixtureProvider(): iterable {
		foreach ( glob( self::FIXTURE_DIR . '/*.md' ) as $markdownFile ) {
			$name = basename( $markdownFile, '.md' );
			$htmlFile = self::FIXTURE_DIR . '/' . $name . '.html';

			yield $name => [ file_get_contents( $markdownFile ), file_get_contents( $htmlFile ) ];
		}
	}

	private function render( string $markdown ): string {
		$renderer = new MarkdownRenderer(
			titleParser: new FakeWikiTitleParser(),
			pageLinkRenderer: new FakePageLinkRenderer(),
			fileEmbedRenderer: new FakeFileEmbedRenderer(),
			allowExternalImages: false,
			maxNestingLevel: 100,
			tocPlaceholderHtml: null,
			noFollowExternalLinks: true
		);

		return $renderer->render( $markdown, generateHtml: true )->html;
	}

}
