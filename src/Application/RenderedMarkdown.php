<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * The outcome of rendering a markdown document: the HTML plus everything
 * the wiki needs to register about the page.
 */
final class RenderedMarkdown {

	/**
	 * @param WikiTitle[] $links Internal pages this document links to
	 * @param Category[] $categories
	 * @param FileEmbed[] $files Files this document embeds
	 * @param Section[] $sections
	 * @param string[] $externalLinks External URLs this document links to
	 * @param array<int|string, mixed>|null $frontMatter
	 * @param string[] $modules ResourceLoader modules the rendered HTML needs
	 * @param string[] $styleModules ResourceLoader style modules the rendered HTML needs
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $links,
		public readonly array $categories,
		public readonly array $files,
		public readonly array $sections,
		public readonly array $externalLinks,
		public readonly ?array $frontMatter,
		public readonly array $modules,
		public readonly array $styleModules
	) {
	}

}
