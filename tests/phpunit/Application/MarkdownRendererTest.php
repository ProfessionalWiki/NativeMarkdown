<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\CodeHighlighter;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer;
use ProfessionalWiki\NativeMarkdown\Application\NoOpCodeHighlighter;
use ProfessionalWiki\NativeMarkdown\Application\PageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\RenderedMarkdown;
use ProfessionalWiki\NativeMarkdown\Application\Section;
use ProfessionalWiki\NativeMarkdown\Application\TemplateExpander;
use ProfessionalWiki\NativeMarkdown\Tests\FrontMatterBombs;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeCodeHighlighter;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeFileEmbedRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakePageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeTemplateExpander;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\FakeWikiTitleParser;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\RecordingPageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\SpyPageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Tests\TestDoubles\ThrowingPageLinkRenderer;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\RenderedMarkdown
 * @covers \ProfessionalWiki\NativeMarkdown\Application\NoOpCodeHighlighter
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\HighlightedCodeRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\FileEmbed
 * @covers \ProfessionalWiki\NativeMarkdown\Application\HeadingAnchorBuilder
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkParser
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkNode
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkNodeRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\ImageLinkRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\MarkdownLinkRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\SpacedLinkParser
 * @covers \ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownLinkTargetResolver
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TocPlaceholder
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TocPlaceholderRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallParser
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallBlockStartParser
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallBlockParser
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallNode
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallBlock
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallRenderer
 * @covers \ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateBraces
 * @covers \ProfessionalWiki\NativeMarkdown\Application\TemplateCall
 */
class MarkdownRendererTest extends TestCase {

	private const URL_PROTOCOLS = [ '//', 'http://', 'https://', 'ftp://', 'mailto:' ];

	private function newRenderer(
		bool $allowExternalImages = false,
		?PageLinkRenderer $pageLinkRenderer = null,
		?string $tocPlaceholderHtml = null,
		bool $templateTransclusion = false,
		?CodeHighlighter $codeHighlighter = null
	): MarkdownRenderer {
		return new MarkdownRenderer(
			titleParser: new FakeWikiTitleParser(),
			pageLinkRenderer: $pageLinkRenderer ?? new FakePageLinkRenderer(),
			fileEmbedRenderer: new FakeFileEmbedRenderer(),
			codeHighlighter: $codeHighlighter ?? new NoOpCodeHighlighter(),
			allowExternalImages: $allowExternalImages,
			maxNestingLevel: 100,
			tocPlaceholderHtml: $tocPlaceholderHtml,
			noFollowExternalLinks: true,
			templateTransclusion: $templateTransclusion,
			urlProtocols: self::URL_PROTOCOLS
		);
	}

	private function render(
		string $markdown,
		bool $allowExternalImages = false,
		?PageLinkRenderer $pageLinkRenderer = null,
		bool $generateHtml = true,
		?string $tocPlaceholderHtml = null,
		bool $templateTransclusion = false,
		?TemplateExpander $templateExpander = null,
		?CodeHighlighter $codeHighlighter = null
	): RenderedMarkdown {
		return $this->newRenderer(
			$allowExternalImages,
			$pageLinkRenderer,
			$tocPlaceholderHtml,
			$templateTransclusion,
			$codeHighlighter
		)->render( $markdown, $generateHtml, $templateExpander );
	}

	private function extractPlainText( string $markdown ): string {
		return $this->newRenderer()->extractPlainText( $markdown );
	}

	public function testRendersHeadingAndBoldText(): void {
		$this->assertSame(
			"<h1 id=\"Hello\">Hello</h1>\n<p>This is <strong>bold</strong> text.</p>\n",
			$this->render( "# Hello\n\nThis is **bold** text." )->html
		);
	}

	public function testEscapesRawHtml(): void {
		$html = $this->render( "<script>alert('xss')</script>\n\ntext <b onclick=\"x\">inline</b>" )->html;

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b onclick', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function testDropsUnsafeLinkTargets(): void {
		$html = $this->render( '[click](javascript:alert(1))' )->html;

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function testRendersGfmTableAsWikitable(): void {
		$html = $this->render( "| A | B |\n|---|---|\n| 1 | 2 |" )->html;

		$this->assertStringContainsString( '<table class="wikitable">', $html );
		$this->assertStringContainsString( '<td>1</td>', $html );
	}

	public function testEscapedPipeLetsWikiLinkLabelWorkInsideTableCell(): void {
		$html = $this->render( "| Col |\n|---|\n| [[Some Page\\|label]] |" )->html;

		$this->assertStringContainsString( '<a href="/wiki/Some Page">label</a>', $html );
	}

	public function testUnescapedPipeInWikiLinkSplitsTableCells(): void {
		$html = $this->render( "| A | B |\n|---|---|\n| [[Some Page|label]] |" )->html;

		$this->assertStringNotContainsString( '<a', $html );
	}

	public function testRendersStrikethrough(): void {
		$this->assertStringContainsString(
			'<del>gone</del>',
			$this->render( '~~gone~~' )->html
		);
	}

	public function testRendersTaskList(): void {
		$html = $this->render( "- [x] done\n- [ ] open" )->html;

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'checked', $html );
	}

	public function testRendersAutolinkAsExternalNoFollowLink(): void {
		$this->assertStringContainsString(
			'<a href="https://example.com" class="external" rel="nofollow">https://example.com</a>',
			$this->render( 'Visit https://example.com today' )->html
		);
	}

	public function testExternalLinkGetsExternalClassAndNoFollow(): void {
		$this->assertSame(
			"<p><a href=\"https://example.com/x\" class=\"external\" rel=\"nofollow\">label</a></p>\n",
			$this->render( '[label](https://example.com/x)' )->html
		);
	}

	public function testRelativeLinkStaysPlain(): void {
		$this->assertSame(
			"<p><a href=\"/local/path\">label</a></p>\n",
			$this->render( '[label](/local/path)' )->html
		);
	}

	public function testExternalLinksAreCollected(): void {
		$result = $this->render( "[a](https://example.com/a) and https://example.com/b and [rel](/not-external)" );

		$this->assertSame(
			[ 'https://example.com/a', 'https://example.com/b' ],
			$result->externalLinks
		);
	}

	public function testMarkdownLinkToInternalPageRendersWikiLinkAndIsCollected(): void {
		$result = $this->render( '[the docs](Help:Example)' );

		$this->assertSame(
			"<p><a href=\"/wiki/Help:Example\">the docs</a></p>\n",
			$result->html
		);
		$this->assertCount( 1, $result->links );
		$this->assertSame( 'Help:Example', $result->links[0]->prefixedText );
	}

	public function testMarkdownLinkWithUnderscoresLinksToMultiWordTitle(): void {
		// CommonMark ends an unbracketed destination at a space, so underscores
		// stand in for spaces the way they do in MediaWiki page titles and URLs.
		$result = $this->render( '[getting started](Help:Getting_Started)' );

		$this->assertSame(
			"<p><a href=\"/wiki/Help:Getting_Started\">getting started</a></p>\n",
			$result->html
		);
		$this->assertSame( 'Help:Getting_Started', $result->links[0]->prefixedText );
	}

	public function testMarkdownLinkToInternalPageKeepsFormattedLabel(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/Help:Example\"><strong>bold</strong> label</a></p>\n",
			$this->render( '[**bold** label](Help:Example)' )->html
		);
	}

	public function testMarkdownLinkWithFragmentLinksToSection(): void {
		$result = $this->render( '[history](Some_Page#History)' );

		$this->assertSame(
			"<p><a href=\"/wiki/Some_Page#History\">history</a></p>\n",
			$result->html
		);
		$this->assertSame( 'History', $result->links[0]->fragment );
	}

	public function testMarkdownLinkToInternalPageIsNotCollectedAsExternal(): void {
		$this->assertSame( [], $this->render( '[x](Help:Example)' )->externalLinks );
	}

	public function testMarkdownLinkToInternalPageIsCollectedInMetadataOnlyRender(): void {
		$result = $this->render( '[x](Help:Example)', generateHtml: false );

		$this->assertCount( 1, $result->links );
		$this->assertSame( 'Help:Example', $result->links[0]->prefixedText );
	}

	public function testMarkdownLinkExistenceIsPreloadedBeforeRendering(): void {
		$recorder = new RecordingPageLinkRenderer();

		$this->render( 'See [the docs](Help:Example) now', pageLinkRenderer: $recorder );

		$this->assertSame(
			[ 'preload:Help:Example', 'render:Help:Example' ],
			$recorder->calls
		);
	}

	public function testMailtoMarkdownLinkStaysExternal(): void {
		$this->assertSame(
			"<p><a href=\"mailto:hi@example.com\" class=\"external\" rel=\"nofollow\">mail us</a></p>\n",
			$this->render( '[mail us](mailto:hi@example.com)' )->html
		);
	}

	public function testMarkdownLinkToSamePageAnchorStaysPlainAndUnregistered(): void {
		$result = $this->render( '[jump](#Section)' );

		$this->assertSame( "<p><a href=\"#Section\">jump</a></p>\n", $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testMarkdownLinkWithSpacedTargetLinksToWikiPageAndIsCollected(): void {
		$result = $this->render( '[getting started](Help:Getting Started)' );

		$this->assertSame(
			"<p><a href=\"/wiki/Help:Getting Started\">getting started</a></p>\n",
			$result->html
		);
		$this->assertSame( 'Help:Getting Started', $result->links[0]->prefixedText );
	}

	public function testSpacedMarkdownLinkKeepsFormattedLabel(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/Some Page\"><strong>bold</strong> label</a></p>\n",
			$this->render( '[**bold** label](Some Page)' )->html
		);
	}

	public function testSpacedMarkdownLinkWithUnmatchedEmphasisMarkerRendersLabelAsText(): void {
		// The lone `*` leaves adjacent text nodes in the label; they must still
		// render as one run (the parser skips league's AST-only text merging).
		$this->assertSame(
			"<p><a href=\"/wiki/Some Page\">a * b</a></p>\n",
			$this->render( '[a * b](Some Page)' )->html
		);
	}

	public function testSpacedMarkdownLinkAllowsBalancedParensInTarget(): void {
		$result = $this->render( '[the planet](Mercury (planet))' );

		$this->assertSame(
			"<p><a href=\"/wiki/Mercury (planet)\">the planet</a></p>\n",
			$result->html
		);
		$this->assertSame( 'Mercury (planet)', $result->links[0]->prefixedText );
	}

	public function testSpacedMarkdownLinkIsCollectedInMetadataOnlyRender(): void {
		$result = $this->render( '[x](Some Page)', generateHtml: false );

		$this->assertSame( 'Some Page', $result->links[0]->prefixedText );
	}

	public function testSpacedTargetThatIsNotAValidTitleStaysLiteralText(): void {
		$result = $this->render( '[x](foo <bad> bar)' );

		$this->assertSame( "<p>[x](foo &lt;bad&gt; bar)</p>\n", $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testSpacelessLinksStillResolveAlongsideSpacedOnes(): void {
		$result = $this->render( '[a](Help:A) and [b](Help Long Name)' );

		$this->assertSame(
			"<p><a href=\"/wiki/Help:A\">a</a> and <a href=\"/wiki/Help Long Name\">b</a></p>\n",
			$result->html
		);
	}

	public function testSpacedTextInReferenceLinkStillResolves(): void {
		$result = $this->render( "[text][ref]\n\n[ref]: https://example.com" );

		$this->assertStringContainsString( '<a href="https://example.com"', $result->html );
	}

	public function testImageWithSpacedTargetIsNotTurnedIntoAWikiLink(): void {
		$html = $this->render( '![a cat](https://example.com/a cat.png)' )->html;

		$this->assertStringNotContainsString( '/wiki/', $html );
	}

	public function testMarkdownLinkWithQuotedTitleLinksToDestinationNotWholeTarget(): void {
		// `[docs](Manual "the guide")` is a standard titled link: it must link to
		// the page `Manual`, not a bogus page named `Manual "the guide"`.
		$result = $this->render( '[docs](Manual "the guide")' );

		$this->assertSame( "<p><a href=\"/wiki/Manual\">docs</a></p>\n", $result->html );
		$this->assertSame( 'Manual', $result->links[0]->prefixedText );
	}

	public function testOverlongSpacedTargetIsLeftToLeagueInsteadOfScannedToEnd(): void {
		// The destination scan is length-capped so a runaway `[](` cannot make
		// parsing quadratic; an over-long target is simply not treated as a link.
		$result = $this->render( '[x](' . str_repeat( 'a ', 700 ) . ')' );

		$this->assertStringNotContainsString( '<a', $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testRendersFootnote(): void {
		$html = $this->render( "Text with note[^1]\n\n[^1]: The note content" )->html;

		$this->assertStringContainsString( 'class="footnote-ref"', $html );
		$this->assertStringContainsString( 'The note content', $html );
	}

	public function testFrontMatterIsCapturedAndHiddenFromOutput(): void {
		$result = $this->render( "---\ntitle: My Page\ntags:\n  - one\n---\n\nBody text" );

		$this->assertSame( [ 'title' => 'My Page', 'tags' => [ 'one' ] ], $result->frontMatter );
		$this->assertSame( "<p>Body text</p>\n", $result->html );
	}

	public function testDocumentWithoutFrontMatterHasNullFrontMatter(): void {
		$this->assertNull( $this->render( 'Just text' )->frontMatter );
	}

	public function testInvalidFrontMatterRendersAsMarkdown(): void {
		$result = $this->render( "---\n{ not: valid: yaml\n---\n\nBody" );

		$this->assertNull( $result->frontMatter );
		$this->assertStringContainsString( 'Body', $result->html );
	}

	public function testAliasBombFrontMatterIsRejectedAndKeptOutOfOutput(): void {
		// Bounded stand-in for a YAML alias bomb: rejected before the parser can
		// expand it, so it is never stored and never leaks into the rendered body.
		$result = $this->render( FrontMatterBombs::aliasBombBlock() . "\nVisible body." );

		$this->assertNull( $result->frontMatter );
		$this->assertSame( "<p>Visible body.</p>\n", $result->html );
	}

	public function testRejectedFrontMatterWithDecoyDelimiterDoesNotLeakIntoBody(): void {
		// The guard matches league's block grammar (a closing "---" needs a
		// newline after it), so it rejects on the real closing delimiter. The
		// reject path must strip exactly that block, not stop at the decoy "---".
		$result = $this->render( FrontMatterBombs::aliasBombBlockWithDecoyDelimiter() . "\nVisible body." );

		$this->assertNull( $result->frontMatter );
		$this->assertStringNotContainsString( 'DECOY', $result->html );
		$this->assertStringContainsString( 'Visible body.', $result->html );
	}

	public function testFrontMatterWithAmpersandAndAsteriskInValuesIsStillParsed(): void {
		$result = $this->render( "---\ntitle: R&D notes\nformula: 3 * 4 = 12\n---\n\nBody" );

		$this->assertSame(
			[ 'title' => 'R&D notes', 'formula' => '3 * 4 = 12' ],
			$result->frontMatter
		);
	}

	public function testWikiLinkRendersAndIsCollected(): void {
		$result = $this->render( 'See [[Some Page]] for more' );

		$this->assertSame(
			"<p>See <a href=\"/wiki/Some Page\">Some Page</a> for more</p>\n",
			$result->html
		);
		$this->assertCount( 1, $result->links );
		$this->assertSame( 'Some Page', $result->links[0]->prefixedText );
	}

	public function testWikiLinkWithLabelUsesLabel(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/Some Page\">the label</a></p>\n",
			$this->render( '[[Some Page|the label]]' )->html
		);
	}

	public function testWikiLinkLabelKeepsExtraPipes(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/Some Page\">a|b</a></p>\n",
			$this->render( '[[Some Page|a|b]]' )->html
		);
	}

	public function testWikiLinkWithFragmentLinksToSection(): void {
		$result = $this->render( '[[Some Page#History]]' );

		$this->assertSame(
			"<p><a href=\"/wiki/Some Page#History\">Some Page#History</a></p>\n",
			$result->html
		);
		$this->assertSame( 'History', $result->links[0]->fragment );
	}

	public function testLinkExistenceIsPreloadedInOneBatchBeforeRendering(): void {
		$recorder = new RecordingPageLinkRenderer();

		$this->render( 'See [[First Page]] and [[Second Page]]', pageLinkRenderer: $recorder );

		$this->assertSame(
			[ 'preload:First Page,Second Page', 'render:First Page', 'render:Second Page' ],
			$recorder->calls
		);
	}

	public function testNoExistencePreloadWithoutLinks(): void {
		$recorder = new RecordingPageLinkRenderer();

		$this->render( 'Just **text** and a [link](https://example.com)', pageLinkRenderer: $recorder );

		$this->assertSame( [], $recorder->calls );
	}

	public function testSamePageAnchorsAreNotPreloaded(): void {
		$recorder = new RecordingPageLinkRenderer();

		$this->render( '[[#Anchor]] and [[Real Page]]', pageLinkRenderer: $recorder );

		$this->assertSame(
			[ 'preload:Real Page', 'render:#Anchor', 'render:Real Page' ],
			$recorder->calls
		);
	}

	public function testInterwikiLinksAreNotPreloaded(): void {
		$recorder = new RecordingPageLinkRenderer();

		$this->render( '[[wikipedia:Berlin]] and [[Local Page]]', pageLinkRenderer: $recorder );

		$this->assertSame(
			[ 'preload:Local Page', 'render:wikipedia:Berlin', 'render:Local Page' ],
			$recorder->calls
		);
	}

	public function testSamePageAnchorRendersFragmentLinkWithoutCollectingIt(): void {
		$result = $this->render( 'See [[#History]] below' );

		$this->assertSame( "<p>See <a href=\"#History\">#History</a> below</p>\n", $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testSamePageAnchorWithLabelUsesLabel(): void {
		$this->assertSame(
			"<p><a href=\"#History\">see above</a></p>\n",
			$this->render( '[[#History|see above]]' )->html
		);
	}

	public function testBareHashWikiLinkIsLiteralText(): void {
		$result = $this->render( 'A [[#]] here' );

		$this->assertSame( "<p>A [[#]] here</p>\n", $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testInvalidWikiLinkDegradesToLiteralText(): void {
		$result = $this->render( 'A [[<bad>title]] here' );

		$this->assertSame( "<p>A [[&lt;bad&gt;title]] here</p>\n", $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testUnclosedWikiLinkIsLiteralText(): void {
		$this->assertSame(
			"<p>[[not closed</p>\n",
			$this->render( '[[not closed' )->html
		);
	}

	public function testCategoryIsAssignedAndRendersNothing(): void {
		$result = $this->render( "Some text\n\n[[Category:Spike]]" );

		$this->assertCount( 1, $result->categories );
		$this->assertSame( 'Spike', $result->categories[0]->title->dbKey );
		$this->assertSame( '', $result->categories[0]->sortKey );
		$this->assertSame( "<p>Some text</p>\n", $result->html );
	}

	public function testCategoryWithSortKey(): void {
		$result = $this->render( '[[Category:Spike|Zzz]]' );

		$this->assertSame( 'Zzz', $result->categories[0]->sortKey );
	}

	public function testColonPrefixedCategoryRendersVisibleLink(): void {
		$result = $this->render( '[[:Category:Spike]]' );

		$this->assertSame(
			"<p><a href=\"/wiki/Category:Spike\">Category:Spike</a></p>\n",
			$result->html
		);
		$this->assertSame( [], $result->categories );
		$this->assertCount( 1, $result->links );
	}

	public function testFileEmbedRendersImageAndIsCollectedAsFile(): void {
		$result = $this->render( 'A cat: [[File:Cat.png]]' );

		$this->assertSame( "<p>A cat: <img data-fake-file=\"Cat.png\"></p>\n", $result->html );
		$this->assertCount( 1, $result->files );
		$this->assertSame( 'File:Cat.png', $result->files[0]->title->prefixedText );
		$this->assertSame( [], $result->links );
	}

	public function testStandaloneThumbnailEmbedIsNotWrappedInParagraph(): void {
		$html = $this->render( '[[File:Cat.png|thumb|A cat]]' )->html;

		$this->assertStringContainsString( 'data-fake-file="Cat.png"', $html );
		$this->assertStringNotContainsString( '<p>', $html );
	}

	public function testSolitaryInlineEmbedStaysWrappedInParagraph(): void {
		$html = $this->render( '[[File:Cat.png|A cat]]' )->html;

		$this->assertStringContainsString( '<p><img data-fake-file="Cat.png"', $html );
	}

	public function testThumbnailEmbedWithSurroundingTextStaysInParagraph(): void {
		$html = $this->render( 'Look: [[File:Cat.png|thumb|A cat]]' )->html;

		$this->assertStringContainsString( '<p>Look: <img data-fake-file="Cat.png"', $html );
	}

	public function testColonPrefixedFileRendersPageLinkInsteadOfEmbedding(): void {
		$result = $this->render( '[[:File:Cat.png]]' );

		$this->assertSame(
			"<p><a href=\"/wiki/File:Cat.png\">File:Cat.png</a></p>\n",
			$result->html
		);
		$this->assertCount( 1, $result->links );
		$this->assertSame( [], $result->files );
	}

	public function testFileEmbedParsesWidthParam(): void {
		$result = $this->render( '[[File:Cat.png|300px]]' );

		$this->assertSame( 300, $result->files[0]->width );
		$this->assertStringContainsString( 'width="300"', $result->html );
	}

	public function testFileEmbedParsesAltParam(): void {
		$result = $this->render( '[[File:Cat.png|alt=A sleeping cat]]' );

		$this->assertSame( 'A sleeping cat', $result->files[0]->altText );
	}

	public function testFileEmbedParsesCaptionParam(): void {
		$result = $this->render( '[[File:Cat.png|My favorite cat]]' );

		$this->assertSame( 'My favorite cat', $result->files[0]->caption );
	}

	public function testFileEmbedParsesAllParamsTogether(): void {
		$embed = $this->render( '[[File:Cat.png|300px|alt=A cat|The caption]]' )->files[0];

		$this->assertSame( 300, $embed->width );
		$this->assertSame( 'A cat', $embed->altText );
		$this->assertSame( 'The caption', $embed->caption );
	}

	public function testFileEmbedLastCaptionWins(): void {
		$this->assertSame(
			'second',
			$this->render( '[[File:Cat.png|first|second]]' )->files[0]->caption
		);
	}

	public function testFileEmbedIsInlineByDefault(): void {
		$this->assertFalse(
			$this->render( '[[File:Cat.png|300px|A caption]]' )->files[0]->thumbnail
		);
	}

	public function testFileEmbedThumbKeywordRequestsThumbnail(): void {
		$this->assertTrue(
			$this->render( '[[File:Cat.png|thumb]]' )->files[0]->thumbnail
		);
	}

	public function testFileEmbedThumbnailKeywordRequestsThumbnail(): void {
		$this->assertTrue(
			$this->render( '[[File:Cat.png|thumbnail]]' )->files[0]->thumbnail
		);
	}

	public function testFileEmbedThumbKeywordIsCaseInsensitive(): void {
		$this->assertTrue(
			$this->render( '[[File:Cat.png|Thumb]]' )->files[0]->thumbnail
		);
	}

	public function testFileEmbedThumbKeywordIsNotTreatedAsCaption(): void {
		$this->assertNull(
			$this->render( '[[File:Cat.png|thumb]]' )->files[0]->caption
		);
	}

	public function testFileEmbedCombinesThumbWithOtherParams(): void {
		$embed = $this->render( '[[File:Cat.png|300px|thumb|alt=A cat|The caption]]' )->files[0];

		$this->assertTrue( $embed->thumbnail );
		$this->assertSame( 300, $embed->width );
		$this->assertSame( 'A cat', $embed->altText );
		$this->assertSame( 'The caption', $embed->caption );
	}

	public function testFileEmbedHeightOnlySizeIsIgnoredNotCaption(): void {
		$embed = $this->render( '[[File:Cat.png|x300px]]' )->files[0];

		$this->assertNull( $embed->width );
		$this->assertNull( $embed->caption );
	}

	public function testFileEmbedWidthAndHeightSizeUsesWidth(): void {
		$this->assertSame(
			120,
			$this->render( '[[File:Cat.png|120x300px]]' )->files[0]->width
		);
	}

	public function testFileEmbedEmptyAltIsKeptAsExplicitlyEmpty(): void {
		$this->assertSame(
			'',
			$this->render( '[[File:Cat.png|alt=]]' )->files[0]->altText
		);
	}

	public function testFileEmbedInsideMarkdownLinkDegradesToPlainText(): void {
		$html = $this->render( '[before [[File:Cat.png|alt=A cat]] after](https://example.com)' )->html;

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'A cat', $html );
	}

	public function testFileLookupsArePreloadedInOneBatchBeforeRendering(): void {
		$fileEmbedRenderer = new FakeFileEmbedRenderer();
		$renderer = new MarkdownRenderer(
			titleParser: new FakeWikiTitleParser(),
			pageLinkRenderer: new FakePageLinkRenderer(),
			fileEmbedRenderer: $fileEmbedRenderer,
			codeHighlighter: new NoOpCodeHighlighter(),
			allowExternalImages: false,
			maxNestingLevel: 100,
			tocPlaceholderHtml: null,
			noFollowExternalLinks: true,
			templateTransclusion: false,
			urlProtocols: self::URL_PROTOCOLS
		);

		$renderer->render( "[[File:First.png]] and [[File:Second.png]]", true );

		$this->assertSame(
			[ [ 'First.png', 'Second.png' ] ],
			array_map(
				static fn ( array $titles ) => array_map( static fn ( $title ) => $title->dbKey, $titles ),
				$fileEmbedRenderer->preloadCalls
			)
		);
	}

	public function testFileEmbedInHeadingContributesNothingToAnchor(): void {
		$result = $this->render( '## See [[File:Cat.png]] here' );

		$this->assertSame( 'See_here', $result->sections[0]->anchor );
	}

	public function testHeadingsGetMediaWikiStyleAnchors(): void {
		$html = $this->render( '## My Section Name' )->html;

		$this->assertSame( "<h2 id=\"My_Section_Name\">My Section Name</h2>\n", $html );
	}

	public function testSectionsAreCollectedWithLevelsAndAnchors(): void {
		$result = $this->render( "# One\n\n## Two\n\ntext\n\n### Three\n\n## Four" );

		$this->assertEquals(
			[
				new Section( 1, 'One', 'One' ),
				new Section( 2, 'Two', 'Two' ),
				new Section( 3, 'Three', 'Three' ),
				new Section( 2, 'Four', 'Four' ),
			],
			$result->sections
		);
	}

	public function testDuplicateHeadingsGetSuffixedAnchors(): void {
		$result = $this->render( "## Same\n\n## Same\n\n## Same" );

		$this->assertEquals(
			[
				new Section( 2, 'Same', 'Same' ),
				new Section( 2, 'Same', 'Same_2' ),
				new Section( 2, 'Same', 'Same_3' ),
			],
			$result->sections
		);
	}

	public function testHeadingWithFormattingUsesPlainTextForAnchor(): void {
		$result = $this->render( '## Some **bold** heading' );

		$this->assertSame( 'Some_bold_heading', $result->sections[0]->anchor );
		$this->assertSame( 'Some bold heading', $result->sections[0]->text );
	}

	public function testExternalImageRendersAsPlainLinkByDefault(): void {
		$this->assertSame(
			"<p><a href=\"https://example.com/cat.png\" class=\"external\" rel=\"nofollow\">a cat</a></p>\n",
			$this->render( '![a cat](https://example.com/cat.png)' )->html
		);
	}

	public function testExternalImageWithoutAltLinksUrlText(): void {
		$this->assertSame(
			"<p><a href=\"https://example.com/cat.png\" class=\"external\" rel=\"nofollow\">https://example.com/cat.png</a></p>\n",
			$this->render( '![](https://example.com/cat.png)' )->html
		);
	}

	public function testExternalImageEmbedsWhenAllowed(): void {
		$this->assertSame(
			"<p><img src=\"https://example.com/cat.png\" alt=\"a cat\" /></p>\n",
			$this->render( '![a cat](https://example.com/cat.png)', allowExternalImages: true )->html
		);
	}

	public function testUnsafeImageUrlIsNeverLinked(): void {
		$html = $this->render( '![x](javascript:alert(1))' )->html;

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function testDeeplyNestedInputDoesNotFatal(): void {
		$markdown = str_repeat( '> ', 300 ) . 'deep';

		$this->assertNotSame( '', $this->render( $markdown )->html );
	}

	public function testInvalidUtf8DegradesToEscapedSourceInsteadOfFataling(): void {
		$html = $this->render( "Before \xC3\x28 after" )->html;

		$this->assertStringStartsWith( '<pre>', $html );
		$this->assertStringContainsString( 'after', $html );
	}

	public function testInvalidUtf8FallbackEscapesHtmlInSource(): void {
		$result = $this->render( "<script>alert(1)</script> \xC3\x28" );

		$this->assertStringNotContainsString( '<script>', $result->html );
		$this->assertSame( [], $result->links );
	}

	public function testInterwikiFragmentOnlyLinkLabelsWithItsPrefix(): void {
		$this->assertStringContainsString(
			'>wikipedia:#Coordinates</a>',
			$this->render( '[[wikipedia:#Coordinates]]' )->html
		);
	}

	public function testCategoryInsideTableCellLeavesEmptyCellInsteadOfShiftingColumns(): void {
		$result = $this->render( "| A | B |\n|---|---|\n| [[Category:Hidden]] | data |" );

		$this->assertStringContainsString( '<td></td>', $result->html );
		$this->assertStringContainsString( '<td>data</td>', $result->html );
		$this->assertCount( 1, $result->categories );
	}

	public function testInfrastructureFailureDuringRenderingPropagates(): void {
		$renderer = $this->newRenderer( pageLinkRenderer: new ThrowingPageLinkRenderer() );

		$this->expectException( \RuntimeException::class );
		$renderer->render( 'A [[Link]] needing existence lookups', true );
	}

	public function testVeryLargeDocumentRenders(): void {
		$markdown = str_repeat( "## Section\n\nSome **bold** text with [[A Link]] here.\n\n", 2000 );

		$result = $this->render( $markdown );

		$this->assertStringContainsString( '<strong>bold</strong>', $result->html );
		$this->assertCount( 2000, $result->sections );
	}

	public function testMixedLineEndingsRender(): void {
		$html = $this->render( "# Windows Heading\r\n\r\nBody text\r\n" )->html;

		$this->assertStringContainsString( '<h1 id="Windows_Heading">Windows Heading</h1>', $html );
		$this->assertStringContainsString( '<p>Body text</p>', $html );
	}

	public function testBrokenTableWithoutDelimiterRowDegradesToParagraph(): void {
		$html = $this->render( "| A | B |\n| 1 | 2 |" )->html;

		$this->assertStringNotContainsString( '<table', $html );
		$this->assertStringContainsString( '| A | B |', $html );
	}

	public function testRaggedTableRowIsTruncatedToHeaderColumnsWithoutFataling(): void {
		$html = $this->render( "| A | B |\n|---|---|\n| 1 | 2 | 3 | 4 |" )->html;

		$this->assertStringContainsString( '<table class="wikitable">', $html );
		$this->assertStringContainsString( '<td>1</td>', $html );
		$this->assertStringNotContainsString( '<td>3</td>', $html );
	}

	public function testRightToLeftHeadingRendersWithUnicodeAnchor(): void {
		$result = $this->render( "# مرحبا بالعالم\n\nنص عربي مع **غامق**." );

		$this->assertSame( 'مرحبا_بالعالم', $result->sections[0]->anchor );
		$this->assertStringContainsString( '<h1 id="مرحبا_بالعالم">مرحبا بالعالم</h1>', $result->html );
		$this->assertStringContainsString( '<strong>غامق</strong>', $result->html );
	}

	public function testMixedBidirectionalTextRendersWithoutFataling(): void {
		$this->assertSame(
			"<p>Mixed שלום world مرحبا text.</p>\n",
			$this->render( 'Mixed שלום world مرحبا text.' )->html
		);
	}

	public function testHeadingWithEmojiKeepsItInTheAnchor(): void {
		$result = $this->render( '# Hello 🌍 World' );

		$this->assertSame( 'Hello_🌍_World', $result->sections[0]->anchor );
		$this->assertStringContainsString( 'id="Hello_🌍_World"', $result->html );
	}

	public function testWikiLinkInsideHeadingLinksAndAnchorsUseLabel(): void {
		$result = $this->render( '## About [[Some Page|pages]]' );

		$this->assertStringContainsString( '<a href="/wiki/Some Page">pages</a>', $result->html );
		$this->assertSame( 'About_pages', $result->sections[0]->anchor );
	}

	public function testMultibyteWikiLinkKeepsFollowingText(): void {
		$this->assertSame(
			"<p>See <a href=\"/wiki/Zürich\">Zürich</a>, ok</p>\n",
			$this->render( 'See [[Zürich]], ok' )->html
		);
	}

	public function testCjkWikiLinkKeepsFollowingTextAndLinks(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/日本語\">日本語</a> and <a href=\"/wiki/Other\">Other</a></p>\n",
			$this->render( '[[日本語]] and [[Other]]' )->html
		);
	}

	public function testScalarFrontMatterRendersOriginalDocument(): void {
		$result = $this->render( "---\nJust a disclaimer\n---\n\nBody" );

		$this->assertNull( $result->frontMatter );
		$this->assertStringContainsString( 'Just a disclaimer', $result->html );
		$this->assertStringContainsString( 'Body', $result->html );
	}

	public function testFrontMatterWithoutTrailingNewlineIsStillHiddenAndCaptured(): void {
		$result = $this->render( "---\ntitle: Secret\n---" );

		$this->assertSame( [ 'title' => 'Secret' ], $result->frontMatter );
		$this->assertStringNotContainsString( 'Secret', $result->html );
	}

	public function testEmptyPipeLabelFallsBackToTitleText(): void {
		$this->assertSame(
			"<p><a href=\"/wiki/Some Page\">Some Page</a></p>\n",
			$this->render( '[[Some Page|]]' )->html
		);
	}

	public function testWikiLinkInsideMarkdownLinkLabelRendersAsPlainText(): void {
		$this->assertSame(
			"<p><a href=\"https://example.com\" class=\"external\" rel=\"nofollow\">see Help page</a></p>\n",
			$this->render( '[see [[Help]] page](https://example.com)' )->html
		);
	}

	public function testWikiLinkInsideMarkdownLinkIsNotRegistered(): void {
		$this->assertSame(
			[],
			$this->render( '[see [[Help]] page](https://example.com)' )->links
		);
	}

	public function testFileEmbedInsideMarkdownLinkIsNotRegistered(): void {
		$this->assertSame(
			[],
			$this->render( '[before [[File:Cat.png|alt=A cat]] after](https://example.com)' )->files
		);
	}

	public function testFileEmbedInsideMarkdownLinkWithEmptyAltFallsBackToCaption(): void {
		$this->assertStringContainsString(
			'The caption',
			$this->render( '[see [[File:Chart.png|alt=|The caption]]](https://example.com)' )->html
		);
	}

	public function testImageInsideMarkdownLinkRendersAsPlainTextLabel(): void {
		$this->assertSame(
			"<p><a href=\"https://example.com/home\" class=\"external\" rel=\"nofollow\">logo</a></p>\n",
			$this->render( '[![logo](https://example.com/logo.png)](https://example.com/home)' )->html
		);
	}

	public function testCategoryPerLineLeavesNoEmptyParagraph(): void {
		$result = $this->render( "Text\n\n[[Category:A]]\n[[Category:B]]" );

		$this->assertSame( "<p>Text</p>\n", $result->html );
		$this->assertCount( 2, $result->categories );
	}

	public function testCategoryOnlyListItemIsRemoved(): void {
		$this->assertSame(
			"<ul>\n<li>one</li>\n</ul>\n",
			$this->render( "- one\n- [[Category:C]]" )->html
		);
	}

	public function testTocPlaceholderInsertedBeforeFirstTopLevelHeading(): void {
		$result = $this->render( "intro\n\n# One\n\ntext", tocPlaceholderHtml: '<meta toc />' );

		$this->assertSame(
			"<p>intro</p>\n<meta toc />\n<h1 id=\"One\">One</h1>\n<p>text</p>\n",
			$result->html
		);
	}

	public function testTocPlaceholderStaysOutsideBlockquote(): void {
		$result = $this->render( "> # Quoted\n\nafter", tocPlaceholderHtml: '<meta toc />' );

		$this->assertSame(
			"<meta toc />\n<blockquote>\n<h1 id=\"Quoted\">Quoted</h1>\n</blockquote>\n<p>after</p>\n",
			$result->html
		);
	}

	public function testNoTocPlaceholderWithoutHeadings(): void {
		$this->assertSame(
			"<p>just text</p>\n",
			$this->render( 'just text', tocPlaceholderHtml: '<meta toc />' )->html
		);
	}

	public function testSearchTextDropsMarkdownFormattingButKeepsWords(): void {
		$text = $this->extractPlainText( "# Heading One\n\nSome **bold** and _italic_ words." );

		$this->assertStringNotContainsString( '#', $text );
		$this->assertStringNotContainsString( '*', $text );
		$this->assertStringNotContainsString( '_', $text );
		$this->assertStringContainsString( 'Heading One', $text );
		$this->assertStringContainsString( 'bold', $text );
		$this->assertStringContainsString( 'italic', $text );
	}

	public function testSearchTextExcludesFrontMatter(): void {
		$text = $this->extractPlainText( "---\ntitle: Secret Metadata\n---\n\nVisible body text." );

		$this->assertStringNotContainsString( 'Secret Metadata', $text );
		$this->assertStringContainsString( 'Visible body text', $text );
	}

	public function testSearchTextUsesLinkLabelNotWikiMarkup(): void {
		$text = $this->extractPlainText( 'See [[Some Page|the label]] for details.' );

		$this->assertStringNotContainsString( '[[', $text );
		$this->assertStringNotContainsString( 'Some Page', $text );
		$this->assertStringContainsString( 'the label', $text );
	}

	public function testSearchTextExcludesCategoryAssignments(): void {
		$text = $this->extractPlainText( "Body words here.\n\n[[Category:Hidden Bucket]]" );

		$this->assertStringNotContainsString( 'Category', $text );
		$this->assertStringNotContainsString( 'Hidden Bucket', $text );
		$this->assertStringContainsString( 'Body words here', $text );
	}

	public function testSearchTextSeparatesAdjacentBlocksWithWhitespace(): void {
		$text = $this->extractPlainText( "# Title\n\nParagraph body." );

		$this->assertStringContainsString( 'Title Paragraph body', $text );
	}

	public function testSearchTextKeepsTableCellText(): void {
		$text = $this->extractPlainText( "| Fruit | Color |\n|---|---|\n| Apple | Red |" );

		$this->assertStringContainsString( 'Apple', $text );
		$this->assertStringContainsString( 'Red', $text );
	}

	public function testSearchTextKeepsVisibleEscapedRawHtmlText(): void {
		$this->assertStringContainsString(
			'quarterly figures',
			$this->extractPlainText( "Before\n\n<div>quarterly figures</div>\n\nAfter" )
		);
	}

	public function testSearchTextForInvalidUtf8FallsBackToSourceWithoutFrontMatter(): void {
		$text = $this->extractPlainText( "---\nsecret: value\n---\n\nAfter \xC3\x28 body" );

		$this->assertStringNotContainsString( 'secret', $text );
		$this->assertStringContainsString( 'body', $text );
	}

	public function testSearchTextFallbackStripsFrontMatterWithBareCarriageReturnLineEndings(): void {
		$text = $this->extractPlainText( "---\rsecret: value\r---\rBody \xC3\x28 text" );

		$this->assertStringNotContainsString( 'secret', $text );
		$this->assertStringContainsString( 'Body', $text );
	}

	public function testMetadataOnlyRenderSkipsHtmlAndLinkRendering(): void {
		$spy = new SpyPageLinkRenderer();

		$result = $this->render(
			"# Head\n\n[[Some Page]]\n\n[[Category:Spike]]",
			pageLinkRenderer: $spy,
			generateHtml: false
		);

		$this->assertSame( '', $result->html );
		$this->assertCount( 1, $result->links );
		$this->assertCount( 1, $result->categories );
		$this->assertCount( 1, $result->sections );
		$this->assertSame( 0, $spy->renderedLinkCount );
	}

	public function testTemplateCallsAreLiteralTextWhenTransclusionDisabled(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( 'See {{Greeting}} here', templateExpander: $expander );

		$this->assertSame( "<p>See {{Greeting}} here</p>\n", $result->html );
		$this->assertSame( [], $expander->calls );
	}

	public function testInlineTemplateCallIsExpandedAndInjectedRaw(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render(
			'See {{Greeting}} here',
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertSame(
			"<p>See <span class=\"fake-expanded\">{{Greeting}}</span> here</p>\n",
			$result->html
		);
	}

	public function testInlineTemplateCallPassesWikitextAndInlineFlagToExpander(): void {
		$expander = new FakeTemplateExpander();

		$this->render( 'See {{Greeting|Ada}} here', templateTransclusion: true, templateExpander: $expander );

		$this->assertCount( 1, $expander->calls );
		$this->assertSame( '{{Greeting|Ada}}', $expander->calls[0]->wikitext );
		$this->assertFalse( $expander->calls[0]->isBlock );
	}

	public function testBlockTemplateCallOnItsOwnLineIsNotWrappedInParagraph(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( '{{Infobox}}', templateTransclusion: true, templateExpander: $expander );

		$this->assertStringContainsString( '<div class="fake-expanded">{{Infobox}}</div>', $result->html );
		$this->assertStringNotContainsString( '<p>', $result->html );
		$this->assertTrue( $expander->calls[0]->isBlock );
	}

	public function testMultiLineBlockTemplateIsCapturedAsOneCall(): void {
		$expander = new FakeTemplateExpander();
		$wikitext = "{{Infobox person\n| name = Ada\n| born = 1815\n}}";

		$this->render( $wikitext, templateTransclusion: true, templateExpander: $expander );

		$this->assertCount( 1, $expander->calls );
		$this->assertSame( $wikitext, $expander->calls[0]->wikitext );
		$this->assertTrue( $expander->calls[0]->isBlock );
	}

	public function testMultiLineBlockTemplateKeepsBlankParameterLines(): void {
		$expander = new FakeTemplateExpander();
		$wikitext = "{{Infobox\n| a = 1\n\n| b = 2\n}}";

		$this->render( $wikitext, templateTransclusion: true, templateExpander: $expander );

		$this->assertSame( $wikitext, $expander->calls[0]->wikitext );
	}

	public function testNestedTemplateBecomesOneCall(): void {
		$expander = new FakeTemplateExpander();

		$this->render( 'X {{outer|{{inner}}}} Y', templateTransclusion: true, templateExpander: $expander );

		$this->assertCount( 1, $expander->calls );
		$this->assertSame( '{{outer|{{inner}}}}', $expander->calls[0]->wikitext );
	}

	public function testTripleBraceArgumentSyntaxStaysLiteral(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( 'Value: {{{param}}} here', templateTransclusion: true, templateExpander: $expander );

		$this->assertSame( [], $expander->calls );
		$this->assertStringContainsString( '{{{param}}}', $result->html );
	}

	public function testTemplateCallWithoutExpanderDegradesToEscapedLiteral(): void {
		$result = $this->render( '{{Infobox}}', templateTransclusion: true );

		$this->assertSame( "<p>{{Infobox}}</p>\n", $result->html );
	}

	public function testBalancedCallWithTrailingTextIsInlineNotBlock(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( '{{Foo}} and more', templateTransclusion: true, templateExpander: $expander );

		$this->assertFalse( $expander->calls[0]->isBlock );
		$this->assertSame(
			"<p><span class=\"fake-expanded\">{{Foo}}</span> and more</p>\n",
			$result->html
		);
	}

	public function testUnclosedBlockTemplateDegradesToLiteralInsteadOfExpanding(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render(
			"{{Unclosed\nmore body text\nand still more",
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertSame( [], $expander->calls );
		$this->assertStringContainsString( '{{Unclosed', $result->html );
	}

	public function testTemplateCallInsideInlineCodeStaysLiteral(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( 'Use `{{Foo}}` in code', templateTransclusion: true, templateExpander: $expander );

		$this->assertSame( [], $expander->calls );
		$this->assertStringContainsString( '<code>{{Foo}}</code>', $result->html );
	}

	public function testTemplateCallInsideFencedCodeStaysLiteral(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render(
			"```\n{{Foo}}\n```",
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertSame( [], $expander->calls );
		$this->assertStringContainsString( '{{Foo}}', $result->html );
	}

	public function testBackslashEscapedBracesStayLiteral(): void {
		$expander = new FakeTemplateExpander();

		$result = $this->render( 'Literal \\{\\{Foo}} here', templateTransclusion: true, templateExpander: $expander );

		$this->assertSame( [], $expander->calls );
		$this->assertStringContainsString( '{{Foo}}', $result->html );
	}

	public function testTemplateCallInHeadingContributesNothingToAnchor(): void {
		$result = $this->render(
			'## Section {{Foo}}',
			templateTransclusion: true,
			templateExpander: new FakeTemplateExpander()
		);

		$this->assertStringNotContainsString( 'Foo', $result->sections[0]->anchor );
		$this->assertStringNotContainsString( '{', $result->sections[0]->anchor );
	}

	public function testSearchTextExcludesTemplateWikitext(): void {
		$text = $this->newRenderer( templateTransclusion: true )
			->extractPlainText( 'Body {{Foo|bar}} words' );

		$this->assertStringNotContainsString( 'Foo', $text );
		$this->assertStringNotContainsString( '{{', $text );
		$this->assertStringContainsString( 'Body', $text );
		$this->assertStringContainsString( 'words', $text );
	}

	public function testTemplateExpansionRunsEvenWhenNotGeneratingHtml(): void {
		$expander = new FakeTemplateExpander();

		$this->render(
			'{{Infobox}}',
			generateHtml: false,
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertCount( 1, $expander->calls );
	}

	public function testEachTemplateCallOnItsOwnLineIsExpanded(): void {
		$expander = new FakeTemplateExpander();

		$this->render( "{{First}}\n\n{{Second}}", templateTransclusion: true, templateExpander: $expander );

		$this->assertSame(
			[ '{{First}}', '{{Second}}' ],
			array_map( static fn ( $call ) => $call->wikitext, $expander->calls )
		);
	}

	public function testTemplateCallInTableCellUsesEscapedPipeForArguments(): void {
		$expander = new FakeTemplateExpander();

		$this->render(
			"| Col |\n|---|\n| {{Greeting\\|Ada}} |",
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertCount( 1, $expander->calls );
		$this->assertSame( '{{Greeting|Ada}}', $expander->calls[0]->wikitext );
		$this->assertFalse( $expander->calls[0]->isBlock );
	}

	/**
	 * A stack of distinct block calls `{{T1}}` .. `{{T$count}}`, one per block.
	 */
	private function blockTemplateCalls( int $count ): string {
		return implode(
			"\n\n",
			array_map( static fn ( int $number ) => '{{T' . $number . '}}', range( 1, $count ) )
		);
	}

	public function testExpandsEveryCallWhenDocumentIsExactlyAtTheExpansionCap(): void {
		$expander = new FakeTemplateExpander();

		$this->render(
			$this->blockTemplateCalls( 100 ),
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertCount( 100, $expander->calls );
	}

	public function testExpandsAtMostTheCapNumberOfCallsWhenDocumentExceedsIt(): void {
		$expander = new FakeTemplateExpander();

		$this->render(
			$this->blockTemplateCalls( 103 ),
			templateTransclusion: true,
			templateExpander: $expander
		);

		$this->assertCount( 100, $expander->calls );
	}

	public function testCapBoundaryExpandsTheHundredthCallButLeavesTheHundredFirstLiteral(): void {
		$html = $this->render(
			$this->blockTemplateCalls( 103 ),
			templateTransclusion: true,
			templateExpander: new FakeTemplateExpander()
		)->html;

		$this->assertStringContainsString( '<div class="fake-expanded">{{T100}}</div>', $html );
		$this->assertStringContainsString( '<p>{{T101}}</p>', $html );
	}

	public function testFencedCodeWithLanguageIsRenderedByTheHighlighter(): void {
		$html = $this->render(
			"```python\nprint('hi')\n```",
			codeHighlighter: new FakeCodeHighlighter()
		)->html;

		$this->assertStringContainsString( '<div class="fake-highlight">HIGHLIGHTED</div>', $html );
		$this->assertStringNotContainsString( '<code class="language-', $html );
	}

	public function testHighlighterDeclinedFencedCodeFallsBackToDefaultRendering(): void {
		$html = $this->render(
			"```python\nprint('hi')\n```",
			codeHighlighter: new FakeCodeHighlighter( html: null )
		)->html;

		$this->assertStringContainsString( '<pre><code class="language-python">', $html );
	}

	public function testFencedCodeWithoutInfoStringIsNotHighlighted(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( "```\nplain code\n```", codeHighlighter: $highlighter );

		$this->assertSame( [], $highlighter->calls );
	}

	public function testIndentedCodeBlockIsNotHighlighted(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( "    indented code line\n", codeHighlighter: $highlighter );

		$this->assertSame( [], $highlighter->calls );
	}

	public function testFirstInfoWordIsPassedAsTheLanguage(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( "```python line-numbers\ncode\n```", codeHighlighter: $highlighter );

		$this->assertSame( 'python', $highlighter->calls[0]['language'] );
	}

	public function testFencedCodeLiteralIsPassedToTheHighlighter(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( "```js\nconst x = 1;\n```", codeHighlighter: $highlighter );

		$this->assertSame( "const x = 1;\n", $highlighter->calls[0]['code'] );
	}

	public function testMetadataOnlyRenderDoesNotHighlightCode(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( "```python\nprint('hi')\n```", generateHtml: false, codeHighlighter: $highlighter );

		$this->assertSame( [], $highlighter->calls );
	}

	public function testHighlighterModulesAreReportedWhenABlockIsHighlighted(): void {
		$result = $this->render(
			"```python\nprint('hi')\n```",
			codeHighlighter: new FakeCodeHighlighter(
				modules: [ 'ext.demo.view' ],
				styleModules: [ 'ext.demo' ]
			)
		);

		$this->assertSame( [ 'ext.demo.view' ], $result->modules );
		$this->assertSame( [ 'ext.demo' ], $result->styleModules );
	}

	public function testNoModulesWhenDocumentHasNoHighlightableCode(): void {
		$result = $this->render(
			'Just a paragraph with no code.',
			codeHighlighter: new FakeCodeHighlighter()
		);

		$this->assertSame( [], $result->modules );
		$this->assertSame( [], $result->styleModules );
	}

	public function testNoModulesWhenEveryBlockDeclinesHighlighting(): void {
		$result = $this->render(
			"```python\nprint('hi')\n```",
			codeHighlighter: new FakeCodeHighlighter( html: null )
		);

		$this->assertSame( [], $result->modules );
		$this->assertSame( [], $result->styleModules );
	}

	public function testEmbeddedThumbnailReportsTheFileRenderersMediaModules(): void {
		$result = $this->render( '[[File:Cat.png|thumb]]' );

		$this->assertSame( [ 'test.file.media' ], $result->modules );
	}

	public function testInlineEmbedReportsNoMediaModules(): void {
		$result = $this->render( '[[File:Cat.png]]' );

		$this->assertSame( [], $result->modules );
	}

	public function testMetadataOnlyRenderReportsNoMediaModules(): void {
		$result = $this->render( '[[File:Cat.png|thumb]]', generateHtml: false );

		$this->assertSame( [], $result->modules );
	}

	public function testThumbnailAndHighlightedCodeReportBothModuleSets(): void {
		$result = $this->render(
			"[[File:Cat.png|thumb]]\n\n```python\nx\n```",
			codeHighlighter: new FakeCodeHighlighter( modules: [ 'ext.demo.view' ] )
		);

		$this->assertSame( [ 'ext.demo.view', 'test.file.media' ], $result->modules );
	}

	/**
	 * A stack of distinct fenced blocks ```lang1 .. ```lang$count, each with a
	 * one-word language so every block is a highlight candidate.
	 */
	private function fencedCodeBlocks( int $count ): string {
		return implode(
			"\n\n",
			array_map(
				static fn ( int $number ) => "```lang$number\ncode $number\n```",
				range( 1, $count )
			)
		);
	}

	public function testHighlightAttemptsAreCappedPerRender(): void {
		$highlighter = new FakeCodeHighlighter();

		$this->render( $this->fencedCodeBlocks( 101 ), codeHighlighter: $highlighter );

		$this->assertCount( 100, $highlighter->calls );
	}

	public function testFencedBlockPastTheHighlightCapKeepsDefaultRendering(): void {
		$html = $this->render(
			$this->fencedCodeBlocks( 101 ),
			codeHighlighter: new FakeCodeHighlighter()
		)->html;

		$this->assertStringNotContainsString( 'class="language-lang100"', $html );
		$this->assertStringContainsString( 'class="language-lang101"', $html );
	}

}
