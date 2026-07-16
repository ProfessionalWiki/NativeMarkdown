<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Util\HtmlFilter;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\FileEmbedNode;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\FileEmbedNodeRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\HighlightedCodeRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\ImageLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\MarkdownLinkRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\SpacedLinkParser;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallBlock;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallBlockStartParser;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallNode;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallParser;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TemplateCallRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TocPlaceholder;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\TocPlaceholderRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkNode;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkNodeRenderer;
use ProfessionalWiki\NativeMarkdown\Application\CommonMark\WikiLinkParser;

/**
 * The markdown rendering pipeline: CommonMark + GFM + footnotes + front matter,
 * extended with wikilink syntax. Pure application logic; wiki behavior is
 * injected through the WikiTitleParser and PageLinkRenderer ports.
 */
final class MarkdownRenderer {

	private const WIKI_LINK_PARSER_PRIORITY = 100;
	private const TEMPLATE_CALL_PARSER_PRIORITY = 100;
	// Above league's CloseBracketParser (30) so spaced wiki targets get a chance
	// before it rejects them; it still handles every case this parser defers.
	private const SPACED_LINK_PARSER_PRIORITY = 60;
	private const RENDERER_OVERRIDE_PRIORITY = 10;

	/**
	 * Upper bound on `{{...}}` expansions per page render. Each expansion is a
	 * separate full wikitext parse that resets MediaWiki's per-parse anti-DoS
	 * budgets (post-expand include size, expensive-function count, node count,
	 * expansion depth), so without a cap the number of calls on a single page
	 * multiplies those per-page limits, bounded only by the article size. 100 is
	 * generous for real pages -- heavily-templated prose rarely reaches a few
	 * dozen calls -- while turning the worst case into a fixed number of parses.
	 */
	private const MAX_TEMPLATE_EXPANSIONS_PER_RENDER = 100;

	/**
	 * Upper bound on syntax-highlight attempts per page render. Each attempt can
	 * shell out to an external highlighter (pygmentize), and unlike wikitext's
	 * `<syntaxhighlight>` there is no wikitext-parser expensive-function budget
	 * bounding it in the ContentHandler context, so the count is capped here. 100
	 * is generous for real pages while turning the worst case into a fixed number
	 * of invocations; blocks past the cap render with the default escaped renderer.
	 */
	private const MAX_HIGHLIGHTED_BLOCKS_PER_RENDER = 100;

	private MarkdownParser $parser;
	private HtmlRenderer $htmlRenderer;
	private FrontMatterParser $frontMatterParser;
	private FrontMatterGuard $frontMatterGuard;
	private ExternalUrlDetector $externalUrlDetector;
	private MarkdownLinkTargetResolver $linkTargetResolver;

	/**
	 * @param string[] $urlProtocols The wiki's $wgUrlProtocols, used to tell an
	 *   external markdown-link target from a wiki page title.
	 */
	public function __construct(
		private readonly WikiTitleParser $titleParser,
		private readonly PageLinkRenderer $pageLinkRenderer,
		private readonly FileEmbedRenderer $fileEmbedRenderer,
		private readonly CodeHighlighter $codeHighlighter,
		bool $allowExternalImages,
		int $maxNestingLevel,
		private readonly ?string $tocPlaceholderHtml,
		bool $noFollowExternalLinks,
		bool $templateTransclusion,
		array $urlProtocols
	) {
		$this->externalUrlDetector = new ExternalUrlDetector( $urlProtocols );
		$this->linkTargetResolver = new MarkdownLinkTargetResolver( $this->titleParser, $this->externalUrlDetector );

		$environment = $this->newEnvironment(
			$allowExternalImages,
			$maxNestingLevel,
			$noFollowExternalLinks,
			$templateTransclusion
		);

		$this->parser = new MarkdownParser( $environment );
		$this->htmlRenderer = new HtmlRenderer( $environment );
		$this->frontMatterParser = new FrontMatterParser( new SymfonyYamlFrontMatterParser() );
		$this->frontMatterGuard = new FrontMatterGuard();
	}

	private function newEnvironment(
		bool $allowExternalImages,
		int $maxNestingLevel,
		bool $noFollowExternalLinks,
		bool $templateTransclusion
	): Environment {
		$environment = new Environment( [
			'html_input' => HtmlFilter::ESCAPE,
			'allow_unsafe_links' => false,
			'max_nesting_level' => $maxNestingLevel,
			// Markdown has no syntax for CSS classes, so tables get MediaWiki's
			// standard wikitable styling to match how wikitext tables look.
			'default_attributes' => [
				Table::class => [ 'class' => 'wikitable' ],
			],
		] );

		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );
		$environment->addExtension( new FootnoteExtension() );
		$environment->addExtension( new DefaultAttributesExtension() );

		$environment->addInlineParser( new WikiLinkParser(), self::WIKI_LINK_PARSER_PRIORITY );
		$environment->addInlineParser(
			new SpacedLinkParser( $this->linkTargetResolver ),
			self::SPACED_LINK_PARSER_PRIORITY
		);
		$environment->addRenderer( WikiLinkNode::class, new WikiLinkNodeRenderer( $this->pageLinkRenderer ) );
		$environment->addRenderer( FileEmbedNode::class, new FileEmbedNodeRenderer( $this->fileEmbedRenderer ) );
		$environment->addRenderer( TocPlaceholder::class, new TocPlaceholderRenderer() );
		// Runs above league's default FencedCodeRenderer, but is a no-op unless the
		// pipeline stashed highlighted HTML on the node, in which case it returns
		// that; otherwise it returns null and the default renderer takes over.
		$environment->addRenderer( FencedCode::class, new HighlightedCodeRenderer(), self::RENDERER_OVERRIDE_PRIORITY );
		$environment->addRenderer(
			Link::class,
			new MarkdownLinkRenderer( $this->pageLinkRenderer, $this->externalUrlDetector, $noFollowExternalLinks ),
			self::RENDERER_OVERRIDE_PRIORITY
		);

		if ( !$allowExternalImages ) {
			$environment->addRenderer(
				Image::class,
				new ImageLinkRenderer( $this->externalUrlDetector, $noFollowExternalLinks ),
				self::RENDERER_OVERRIDE_PRIORITY
			);
		}

		// Registered only when the feature is on, so with it off `{{...}}` is
		// never recognized and output stays identical to plain markdown.
		if ( $templateTransclusion ) {
			$templateRenderer = new TemplateCallRenderer();

			$environment->addInlineParser( new TemplateCallParser(), self::TEMPLATE_CALL_PARSER_PRIORITY );
			$environment->addBlockStartParser( new TemplateCallBlockStartParser() );
			$environment->addRenderer( TemplateCallNode::class, $templateRenderer );
			$environment->addRenderer( TemplateCallBlock::class, $templateRenderer );
		}

		return $environment;
	}

	/**
	 * Total over content: any input produces a result. When the markdown
	 * machinery cannot handle the input at all, the source is shown escaped
	 * instead of failing. Infrastructure failures (database errors from the
	 * link and file adapters) propagate — falling back on those would cache
	 * degraded output and empty the page's link tables.
	 */
	public function render(
		string $markdown,
		bool $generateHtml,
		?TemplateExpander $templateExpander = null
	): RenderedMarkdown {
		try {
			return $this->renderDocument( $markdown, $generateHtml, $templateExpander );
		} catch ( CommonMarkException ) {
			return $this->escapedSourceFallback( $markdown, $generateHtml );
		}
	}

	private function renderDocument(
		string $markdown,
		bool $generateHtml,
		?TemplateExpander $templateExpander
	): RenderedMarkdown {
		[ $frontMatter, $content ] = $this->extractFrontMatter( $markdown );

		$document = $this->parser->parse( $content );

		[ $links, $categories, $files ] = $this->resolveWikiLinks( $document );
		$links = array_merge( $links, $this->resolveMarkdownLinks( $document ) );

		// Runs regardless of $generateHtml: expansion records each template's
		// wiki dependencies, and a metadata-only parse that skipped it would
		// leave the link tables incomplete and break template-edit invalidation.
		if ( $templateExpander !== null ) {
			$this->expandTemplateCalls( $document, $templateExpander );
		}

		$this->preloadLinkExistence( $links );
		$this->preloadFileLookups( $files, $generateHtml );
		$sections = $this->processHeadings( $document );

		// Only while rendering HTML: a metadata-only parse needs no highlighting,
		// and skipping it avoids the highlighter's cost when the HTML is discarded.
		$highlighted = $generateHtml && $this->highlightCodeBlocks( $document );

		// Likewise HTML-only: a thumbnail's client-side module matters only once the
		// thumbnail is actually rendered, which a metadata-only parse never does.
		$rendersThumbnail = $generateHtml && $this->hasThumbnailEmbed( $files );

		return new RenderedMarkdown(
			html: $generateHtml ? $this->renderHtml( $document, $sections ) : '',
			links: $links,
			categories: $categories,
			files: $files,
			sections: $sections,
			externalLinks: $this->collectExternalLinks( $document ),
			frontMatter: $frontMatter,
			modules: array_merge(
				$highlighted ? $this->codeHighlighter->modules() : [],
				$rendersThumbnail ? $this->fileEmbedRenderer->modules() : []
			),
			styleModules: $highlighted ? $this->codeHighlighter->styleModules() : []
		);
	}

	/**
	 * Delegates each fenced block that has an info string to the code highlighter,
	 * up to MAX_HIGHLIGHTED_BLOCKS_PER_RENDER attempts, and stashes each non-null
	 * result on its node for HighlightedCodeRenderer to emit. Blocks with no info
	 * string, blocks the highlighter declines, and every block past the cap keep
	 * the default escaped rendering.
	 *
	 * @return bool Whether at least one block was highlighted, so the caller
	 *   registers the highlighter's ResourceLoader modules only when they matter.
	 */
	private function highlightCodeBlocks( Document $document ): bool {
		$attemptCount = 0;
		$anyHighlighted = false;

		foreach ( $this->nodesOfType( $document, FencedCode::class ) as $node ) {
			$language = $node->getInfoWords()[0] ?? '';

			if ( $language === '' ) {
				continue;
			}

			if ( $attemptCount >= self::MAX_HIGHLIGHTED_BLOCKS_PER_RENDER ) {
				break;
			}

			$attemptCount++;

			$html = $this->codeHighlighter->highlight( $node->getLiteral(), $language );

			if ( $html !== null ) {
				$node->data->set( HighlightedCodeRenderer::HIGHLIGHTED_HTML_KEY, $html );
				$anyHighlighted = true;
			}
		}

		return $anyHighlighted;
	}

	/**
	 * @param FileEmbed[] $files
	 */
	private function hasThumbnailEmbed( array $files ): bool {
		foreach ( $files as $embed ) {
			if ( $embed->thumbnail ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replaces the raw wikitext of each template call with the expander's HTML,
	 * up to MAX_TEMPLATE_EXPANSIONS_PER_RENDER calls. Unbalanced block calls, and
	 * every call once the cap is reached, are skipped: with a null expandedHtml
	 * the renderer shows them as escaped literal text rather than expanding them.
	 */
	private function expandTemplateCalls( Document $document, TemplateExpander $templateExpander ): void {
		$expansionCount = 0;

		foreach ( $this->templateCallNodes( $document ) as $node ) {
			if ( $node instanceof TemplateCallBlock && !$node->balanced ) {
				continue;
			}

			if ( $expansionCount >= self::MAX_TEMPLATE_EXPANSIONS_PER_RENDER ) {
				break;
			}

			$node->expandedHtml = $templateExpander->expand(
				new TemplateCall( $node->wikitext, $node instanceof TemplateCallBlock )
			);

			$expansionCount++;
		}
	}

	/**
	 * @return array<TemplateCallNode|TemplateCallBlock>
	 */
	private function templateCallNodes( Document $document ): array {
		$nodes = [];

		foreach ( $document->iterator() as $node ) {
			if ( $node instanceof TemplateCallNode || $node instanceof TemplateCallBlock ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * The visible words of the document for the search index: markdown and wiki
	 * markup removed, front matter and category assignments excluded. No HTML is
	 * rendered and no page-existence lookups happen.
	 */
	public function extractPlainText( string $markdown ): string {
		try {
			[ , $content ] = $this->extractFrontMatter( $markdown );
			$document = $this->parser->parse( $content );
			$this->resolveWikiLinks( $document );

			return $this->documentPlainText( $document );
		} catch ( CommonMarkException ) {
			// The parser rejects input the YAML front matter parser also cannot
			// read (e.g. invalid UTF-8), so strip the front matter block directly.
			return $this->collapseWhitespace( $this->stripLeadingFrontMatterBlock( $markdown ) );
		}
	}

	private function stripLeadingFrontMatterBlock( string $markdown ): string {
		// Byte-oriented (no /u) so it survives invalid UTF-8 in the fallback path.
		// \R mirrors the delimiter grammar of league's FrontMatterParser, so the
		// fallback strips exactly the block the normal path would have hidden.
		if ( preg_match( '/\A---\R.*?\R---\R?/s', $markdown, $matches ) === 1 ) {
			return substr( $markdown, strlen( $matches[0] ) );
		}

		return $markdown;
	}

	private function documentPlainText( Document $document ): string {
		$text = '';

		foreach ( $document->iterator() as $node ) {
			// Block boundaries become whitespace so adjacent blocks stay separate words.
			$separator = $node instanceof AbstractBlock ? ' ' : '';
			$text .= $separator . $this->nodeSearchText( $node );
		}

		return $this->collapseWhitespace( $text );
	}

	/**
	 * Differs deliberately from the heading-text rules in plainText(): raw HTML
	 * is escaped by the renderer and thus visible, so its text is searchable,
	 * and labels get padding so they never fuse with neighboring words.
	 * A new inline node type needs an arm in both methods.
	 */
	private function nodeSearchText( Node $node ): string {
		return match ( true ) {
			$node instanceof WikiLinkNode => ' ' . $node->displayLabel() . ' ',
			$node instanceof FileEmbedNode => ' ' . ( $node->embed->caption ?? $node->embed->altText ?? '' ) . ' ',
			$node instanceof TemplateCallNode => '',
			$node instanceof TemplateCallBlock => '',
			$node instanceof StringContainerInterface => $node->getLiteral(),
			$node instanceof Newline => ' ',
			default => ''
		};
	}

	private function collapseWhitespace( string $text ): string {
		$pattern = mb_check_encoding( $text, 'UTF-8' ) ? '/\s+/u' : '/\s+/';

		return trim( preg_replace( $pattern, ' ', $text ) ?? $text );
	}

	private function escapedSourceFallback( string $markdown, bool $generateHtml ): RenderedMarkdown {
		return new RenderedMarkdown(
			html: $generateHtml
				? '<pre>' . htmlspecialchars( $markdown, ENT_QUOTES | ENT_SUBSTITUTE ) . "</pre>\n"
				: '',
			links: [],
			categories: [],
			files: [],
			sections: [],
			externalLinks: [],
			frontMatter: null,
			modules: [],
			styleModules: []
		);
	}

	/**
	 * Existence lookups happen in one batch instead of one query per link.
	 * This also warms the cache used when the wiki records the links.
	 *
	 * @param WikiTitle[] $links
	 */
	private function preloadLinkExistence( array $links ): void {
		$localPages = array_values( array_filter(
			$links,
			static fn ( WikiTitle $link ) => !$link->isInterwiki()
		) );

		if ( $localPages !== [] ) {
			$this->pageLinkRenderer->preloadExistence( $localPages );
		}
	}

	/**
	 * Files are only looked up while rendering HTML, so metadata-only parses
	 * skip the preload entirely.
	 *
	 * @param FileEmbed[] $files
	 */
	private function preloadFileLookups( array $files, bool $generateHtml ): void {
		if ( $generateHtml && $files !== [] ) {
			$this->fileEmbedRenderer->preloadFiles(
				array_map( static fn ( FileEmbed $embed ) => $embed->title, $files )
			);
		}
	}

	/**
	 * @param Section[] $sections
	 */
	private function renderHtml( Document $document, array $sections ): string {
		$this->promoteSolitaryThumbnailEmbeds( $document );

		if ( $this->tocPlaceholderHtml !== null && $sections !== [] ) {
			$this->insertTocPlaceholder( $document );
		}

		return $this->htmlRenderer->renderDocument( $document )->getContent();
	}

	/**
	 * A thumbnail embed renders a block-level `<figure>`, which is invalid inside
	 * the phrasing-only `<p>` that CommonMark wraps a solitary inline node in:
	 * browsers auto-close the paragraph, shipping invalid HTML5 with a stray empty
	 * `<p>` behind it. When such an embed is a paragraph's only content, replace the
	 * paragraph with the embed so its `<figure>` becomes a sibling of the
	 * surrounding blocks, the way wikitext emits a standalone thumbnail. Only the
	 * solitary case is handled: a thumbnail that shares its paragraph with text or a
	 * second embed is left wrapped, outside this fix's scope.
	 */
	private function promoteSolitaryThumbnailEmbeds( Document $document ): void {
		foreach ( $this->nodesOfType( $document, Paragraph::class ) as $paragraph ) {
			$embed = $this->solitaryThumbnailEmbed( $paragraph );

			if ( $embed !== null ) {
				$paragraph->replaceWith( $embed );
			}
		}
	}

	/**
	 * The thumbnail embed that is a paragraph's sole content, ignoring surrounding
	 * whitespace, or null when the paragraph holds anything else: other text, a
	 * second embed, or a non-thumbnail embed (which renders inline and needs no
	 * promotion).
	 */
	private function solitaryThumbnailEmbed( Paragraph $paragraph ): ?FileEmbedNode {
		$embed = null;

		foreach ( $paragraph->children() as $child ) {
			if ( $child instanceof FileEmbedNode && $child->embed->thumbnail ) {
				if ( $embed !== null ) {
					return null;
				}

				$embed = $child;
				continue;
			}

			if ( !$this->isBlankInline( $child ) ) {
				return null;
			}
		}

		return $embed;
	}

	/**
	 * Puts the ToC placeholder before the top-level block containing the first
	 * heading, mirroring where the wikitext parser places the table of contents.
	 */
	private function insertTocPlaceholder( Document $document ): void {
		$firstHeading = $this->nodesOfType( $document, Heading::class )[0] ?? null;

		if ( $firstHeading === null ) {
			return;
		}

		$this->topLevelAncestor( $document, $firstHeading )
			?->insertBefore( new TocPlaceholder( $this->tocPlaceholderHtml ?? '' ) );
	}

	private function topLevelAncestor( Document $document, Node $node ): ?Node {
		for ( $ancestor = $node; $ancestor !== null; $ancestor = $ancestor->parent() ) {
			if ( $ancestor->parent() === $document ) {
				return $ancestor;
			}
		}

		return null;
	}

	/**
	 * @return array{0: array<int|string, mixed>|null, 1: string}
	 */
	private function extractFrontMatter( string $markdown ): array {
		$normalized = $this->withTrailingNewline( $markdown );

		$rejectedBlock = $this->frontMatterGuard->rejectedBlock( $normalized );
		if ( $rejectedBlock !== null ) {
			// A YAML alias bomb: reject it before the parser can expand it, and
			// drop exactly the block the guard matched, so none of the hostile
			// front matter leaks into the body. Treated as no front matter.
			return [ null, substr( $normalized, strlen( $rejectedBlock ) ) ];
		}

		try {
			$input = $this->frontMatterParser->parse( $normalized );
		} catch ( \Exception ) {
			return [ null, $markdown ];
		}

		$frontMatter = $input->getFrontMatter();

		if ( !is_array( $frontMatter ) ) {
			return [ null, $markdown ];
		}

		return [ $frontMatter, $input->getContent() ];
	}

	/**
	 * MediaWiki's pre-save transform trims trailing whitespace, but the front
	 * matter parser requires a newline after the closing delimiter.
	 */
	private function withTrailingNewline( string $markdown ): string {
		return str_ends_with( $markdown, "\n" ) ? $markdown : $markdown . "\n";
	}

	/**
	 * Resolves all wikilink nodes: invalid targets degrade to literal text,
	 * category assignments are collected and removed from the document,
	 * file targets become embeds, and remaining links are collected for
	 * registration.
	 *
	 * @return array{0: WikiTitle[], 1: Category[], 2: FileEmbed[]}
	 */
	private function resolveWikiLinks( Document $document ): array {
		$links = [];
		$categories = [];
		$files = [];

		foreach ( $this->nodesOfType( $document, WikiLinkNode::class ) as $node ) {
			$title = $this->titleParser->parse( $node->target );

			if ( $title === null ) {
				$node->replaceWith( new Text( $node->rawSource ) );
				continue;
			}

			if ( $title->isCategory() && !$node->hasLeadingColon ) {
				$categories[] = new Category( $title, $node->label ?? '' );
				$this->removeCategoryNode( $node );
				continue;
			}

			if ( $this->hasLinkAncestor( $node ) ) {
				$node->resolvedTitle = $title;
				$node->replaceWith( new Text( $this->nestedLinkText( $node, $title ) ) );
				continue;
			}

			if ( $this->isEmbeddableFile( $title, $node ) ) {
				$embed = FileEmbed::fromTitleAndParams( $title, $node->label );
				$files[] = $embed;
				$node->replaceWith( new FileEmbedNode( $embed ) );
				continue;
			}

			$node->resolvedTitle = $title;

			if ( !$title->isSamePageAnchor() ) {
				$links[] = $title;
			}
		}

		return [ $links, $categories, $files ];
	}

	private function isEmbeddableFile( WikiTitle $title, WikiLinkNode $node ): bool {
		return $title->isFile() && !$title->isInterwiki() && !$node->hasLeadingColon;
	}

	/**
	 * Wikilinks nested inside a markdown link cannot render as anchors, so they
	 * degrade to plain text here, before registration: the link tables must not
	 * record links or embeds the rendered page does not contain.
	 */
	private function hasLinkAncestor( Node $node ): bool {
		for ( $ancestor = $node->parent(); $ancestor !== null; $ancestor = $ancestor->parent() ) {
			if ( $ancestor instanceof Link ) {
				return true;
			}
		}

		return false;
	}

	private function nestedLinkText( WikiLinkNode $node, WikiTitle $title ): string {
		if ( $this->isEmbeddableFile( $title, $node ) ) {
			return FileEmbed::fromTitleAndParams( $title, $node->label )->plainTextLabel();
		}

		return $node->displayLabel();
	}

	/**
	 * Detaches the category node, then removes enclosing blocks the removal
	 * emptied, so category assignments leave no blank markup behind. Table
	 * cells stay: removing one would shift the row's columns, so an emptied
	 * cell renders empty, as in wikitext.
	 */
	private function removeCategoryNode( WikiLinkNode $node ): void {
		$container = $node->parent();
		$node->detach();

		while (
			$container instanceof AbstractBlock
			&& $this->isRemovableWhenBlank( $container )
			&& $this->isBlank( $container )
		) {
			/** @var Node|null $parent */
			$parent = $container->parent();
			$container->detach();
			$container = $parent;
		}
	}

	private function isRemovableWhenBlank( AbstractBlock $container ): bool {
		return !$container instanceof Document && !$container instanceof TableCell;
	}

	private function isBlank( Node $container ): bool {
		foreach ( $container->children() as $child ) {
			if ( !$this->isBlankInline( $child ) ) {
				return false;
			}
		}

		return true;
	}

	private function isBlankInline( Node $node ): bool {
		return $node instanceof Newline
			|| ( $node instanceof Text && trim( $node->getLiteral() ) === '' );
	}

	/**
	 * Resolves standard markdown `[label](target)` links whose target names a wiki
	 * page rather than a URL. The node is marked with its resolved title so the
	 * renderer emits an internal link (red/blue styling, section fragments), and the
	 * title is returned for registration in the link tables. Targets that are
	 * external URLs, unsafe schemes, in-page anchors or path references are left
	 * untouched for the link renderer to emit verbatim.
	 *
	 * @return WikiTitle[]
	 */
	private function resolveMarkdownLinks( Document $document ): array {
		$links = [];

		foreach ( $this->nodesOfType( $document, Link::class ) as $link ) {
			$title = $this->resolvedTitleFor( $link );

			if ( $title === null ) {
				continue;
			}

			$link->data->set( MarkdownLinkRenderer::INTERNAL_TITLE_KEY, $title );

			if ( !$title->isSamePageAnchor() ) {
				$links[] = $title;
			}
		}

		return $links;
	}

	/**
	 * The wiki page a markdown link points to, or null if it is not an internal
	 * link. SpacedLinkParser resolves the title while parsing and leaves it on the
	 * node, so reuse that instead of parsing the target a second time.
	 */
	private function resolvedTitleFor( Link $link ): ?WikiTitle {
		/** @var WikiTitle|null $stashed */
		$stashed = $link->data->get( MarkdownLinkRenderer::INTERNAL_TITLE_KEY, null );

		return $stashed ?? $this->linkTargetResolver->resolve( $link->getUrl() );
	}

	/**
	 * @return string[]
	 */
	private function collectExternalLinks( Document $document ): array {
		$urls = [];

		foreach ( $this->nodesOfType( $document, Link::class ) as $link ) {
			if ( $this->externalUrlDetector->isExternalUrl( $link->getUrl() ) ) {
				$urls[] = $link->getUrl();
			}
		}

		return $urls;
	}

	/**
	 * @return Section[]
	 */
	private function processHeadings( Document $document ): array {
		$anchorBuilder = new HeadingAnchorBuilder();
		$sections = [];

		foreach ( $this->nodesOfType( $document, Heading::class ) as $heading ) {
			$text = $this->plainText( $heading );
			$anchor = $anchorBuilder->buildAnchor( $text );

			if ( $anchor !== '' ) {
				$heading->data->set( 'attributes/id', $anchor );
			}

			$sections[] = new Section( $heading->getLevel(), $text, $anchor );
		}

		return $sections;
	}

	/**
	 * Heading text for anchors and the ToC. Stricter than nodeSearchText():
	 * raw HTML and file embeds contribute nothing, matching how core builds
	 * heading ids. A new inline node type needs an arm in both methods.
	 */
	private function plainText( Node $node ): string {
		$text = '';

		foreach ( $node->iterator() as $child ) {
			$text .= match ( true ) {
				$child instanceof WikiLinkNode => $child->displayLabel(),
				$child instanceof FileEmbedNode => '',
				$child instanceof TemplateCallNode => '',
				$child instanceof RawMarkupContainerInterface => '',
				$child instanceof StringContainerInterface => $child->getLiteral(),
				$child instanceof Newline => ' ',
				default => ''
			};
		}

		return $text;
	}

	/**
	 * @template T of Node
	 * @param class-string<T> $class
	 * @return T[]
	 */
	private function nodesOfType( Document $document, string $class ): array {
		$nodes = [];

		foreach ( $document->iterator() as $node ) {
			if ( $node instanceof $class ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

}
