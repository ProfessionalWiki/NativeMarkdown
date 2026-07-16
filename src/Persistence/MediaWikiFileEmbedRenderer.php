<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Persistence;

use File;
use MediaTransformError;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbed;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbedRenderer;
use RepoGroup;

/**
 * Renders embedded files the way wikitext does: an inline (unframed) linked
 * image by default, or a framed thumbnail with a visible caption when the embed
 * requests `thumb`. Missing files become an upload red link, and media that
 * cannot display inline becomes a plain file page link.
 */
final class MediaWikiFileEmbedRenderer implements FileEmbedRenderer {

	/** @var array<string, File|false> */
	private array $preloadedFiles = [];

	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly LinkRenderer $linkRenderer,
		private readonly int $defaultThumbnailWidth
	) {
	}

	/**
	 * One batched query for the whole document. RepoGroup::findFiles() does not
	 * warm the cache findFile() reads, so the result is kept here, with looked-up
	 * but missing files marked, keeping them off the per-embed query path too.
	 */
	public function preloadFiles( array $titles ): void {
		$dbKeys = array_map( static fn ( $title ) => $title->dbKey, $titles );
		/** @var array<string, File> $found */
		$found = $this->repoGroup->findFiles( $dbKeys );

		foreach ( $dbKeys as $dbKey ) {
			$this->preloadedFiles[$dbKey] = $found[$dbKey] ?? false;
		}
	}

	/**
	 * Core's Linker registers this for a thumbnail only when passed a Parser, which
	 * the ContentHandler render path has none of, so the caller adds it instead.
	 */
	public function modules(): array {
		return [ 'mediawiki.page.media' ];
	}

	public function renderEmbed( FileEmbed $embed ): string {
		$file = $this->findFile( $embed->title->dbKey );

		if ( $file === false || !$file->exists() ) {
			return $this->missingFileHtml( $embed );
		}

		if ( !$file->allowInlineDisplay() ) {
			return $this->linkRenderer->makeLink( $this->fileTitle( $embed ), $embed->caption );
		}

		if ( $embed->thumbnail ) {
			return $this->thumbnailFrameHtml( $embed, $file );
		}

		return $this->embeddedImageHtml( $embed, $file );
	}

	/**
	 * Delegates to core so the framed markup, magnify link and responsive
	 * variants match a wikitext `|thumb` exactly. For a missing file (a false
	 * `$file`), core frames a broken-thumb box with the same visible caption and an
	 * upload link inside, the way wikitext renders a missing thumbnail. A width is
	 * always passed: the requested one, or the wiki's default thumbnail size, since
	 * makeThumbLink2 would otherwise fall back to a smaller built-in default.
	 *
	 * @param File|false $file
	 */
	private function thumbnailFrameHtml( FileEmbed $embed, $file ): string {
		return Linker::makeThumbLink2(
			$this->fileTitle( $embed ),
			$file,
			$this->thumbnailFrameParams( $embed ),
			[ 'width' => $embed->width ?? $this->defaultThumbnailWidth ]
		);
	}

	/**
	 * The caption becomes the visible figcaption, which the thumbnail markup
	 * emits as raw HTML, so it is escaped here. An explicit alt sets the image
	 * alt; a thumbnail's caption is not reused as the alt, matching how wikitext
	 * treats a visible caption.
	 *
	 * @return array<string, string>
	 */
	private function thumbnailFrameParams( FileEmbed $embed ): array {
		$frameParams = [ 'caption' => htmlspecialchars( $embed->caption ?? '', ENT_QUOTES ) ];

		if ( $embed->altText !== null ) {
			$frameParams['alt'] = $embed->altText;
		}

		return $frameParams;
	}

	private function embeddedImageHtml( FileEmbed $embed, File $file ): string {
		$handlerParams = [ 'width' => $embed->width ?? $file->getWidth() ];
		$thumbnail = $file->transform( $handlerParams );

		if ( !$thumbnail ) {
			return $this->missingFileHtml( $embed );
		}

		if ( $thumbnail instanceof MediaTransformError ) {
			return $this->transformErrorHtml( $embed, $thumbnail );
		}

		Linker::processResponsiveImages( $file, $thumbnail, $handlerParams );

		return $this->wrap(
			$thumbnail->toHtml( $this->thumbnailOptions( $embed ) ),
			$embed,
			'mw:File'
		);
	}

	/**
	 * The file exists but cannot be transformed (too large, corrupt metadata):
	 * link to its file page with the error text, as wikitext does. The upload
	 * red link stays reserved for files that are actually missing.
	 */
	private function transformErrorHtml( FileEmbed $embed, MediaTransformError $error ): string {
		return $this->wrap(
			Linker::makeBrokenImageLinkObj(
				$this->fileTitle( $embed ),
				(string)$error->toText(),
				'', '', '', false,
				$embed->width === null ? [] : [ 'width' => $embed->width ],
				true
			),
			$embed,
			'mw:Error mw:File'
		);
	}

	/**
	 * Matches how wikitext presents inline images: the caption becomes the
	 * link tooltip and, absent an explicit alt text, also the alt text.
	 *
	 * @return array<string, mixed>
	 */
	private function thumbnailOptions( FileEmbed $embed ): array {
		$options = [
			'desc-link' => true,
			'img-class' => 'mw-file-element',
		];

		$altText = $embed->altText ?? $embed->caption;

		if ( $altText !== null ) {
			$options['alt'] = $altText;
		}

		if ( $embed->caption !== null ) {
			$options['title'] = $embed->caption;
		}

		return $options;
	}

	/**
	 * A `thumb` on a missing file frames a broken-thumb box that keeps the caption
	 * visible, as wikitext does; without it, the embed is a bare upload red link
	 * labelled with the alt text, and the caption would be silently dropped.
	 */
	private function missingFileHtml( FileEmbed $embed ): string {
		if ( $embed->thumbnail ) {
			return $this->thumbnailFrameHtml( $embed, false );
		}

		return $this->wrap(
			Linker::makeBrokenImageLinkObj(
				$this->fileTitle( $embed ),
				$embed->altText ?? '',
				'', '', '', false,
				$embed->width === null ? [] : [ 'width' => $embed->width ]
			),
			$embed,
			'mw:Error mw:File'
		);
	}

	private function wrap( string $innerHtml, FileEmbed $embed, string $rdfaType ): string {
		return Html::rawElement(
			'span',
			[
				'class' => $embed->width === null ? 'mw-default-size' : false,
				'typeof' => $rdfaType,
			],
			$innerHtml
		);
	}

	private function fileTitle( FileEmbed $embed ): TitleValue {
		return new TitleValue( $embed->title->namespace, $embed->title->dbKey );
	}

	/**
	 * @return File|false
	 */
	private function findFile( string $dbKey ) {
		return $this->preloadedFiles[$dbKey] ?? $this->repoGroup->findFile( $dbKey );
	}

}
