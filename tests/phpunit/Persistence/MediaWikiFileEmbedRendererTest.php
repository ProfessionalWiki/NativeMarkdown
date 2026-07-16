<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Persistence;

use File;
use MediaTransformError;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\Application\FileEmbed;
use ProfessionalWiki\NativeMarkdown\Application\WikiTitle;
use ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiFileEmbedRenderer;
use RepoGroup;
use ThumbnailImage;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Persistence\MediaWikiFileEmbedRenderer
 * @group Database
 */
class MediaWikiFileEmbedRendererTest extends MediaWikiIntegrationTestCase {

	private function renderEmbed( File $file, ?int $width = 300 ): string {
		$repoGroup = $this->createStub( RepoGroup::class );
		$repoGroup->method( 'findFile' )->willReturn( $file );

		return $this->newRendererWithRepoGroup( $repoGroup )->renderEmbed( new FileEmbed(
			title: $this->chartTitle(),
			width: $width,
			altText: 'A chart',
			caption: null,
			thumbnail: false
		) );
	}

	public function testFailedTransformOfExistingFileLinksToTheFilePage(): void {
		$html = $this->renderEmbed( $this->newFileWithFailingTransform() );

		$this->assertStringNotContainsString( 'Special:Upload', $html );
		$this->assertStringContainsString( 'File:Chart.png', $html );
	}

	public function testFailedTransformShowsTheTransformErrorText(): void {
		$html = $this->renderEmbed( $this->newFileWithFailingTransform() );

		$this->assertStringContainsString( 'Error creating thumbnail', $html );
	}

	public function testEmbeddedImageGetsResponsiveSrcset(): void {
		$this->overrideConfigValue( MainConfigNames::ResponsiveImages, true );

		$html = $this->renderEmbed( $this->newFileRenderingThumbnails() );

		$this->assertStringContainsString( 'srcset', $html );
		$this->assertStringContainsString( '450px-Chart.png', $html );
	}

	public function testThumbnailRendersFramedFigureWithVisibleCaption(): void {
		$html = $this->renderThumbnail( $this->newFileRenderingThumbnails(), caption: 'Quarterly revenue' );

		$this->assertStringContainsString( 'typeof="mw:File/Thumb"', $html );
		$this->assertStringContainsString( '<figcaption>Quarterly revenue</figcaption>', $html );
	}

	public function testThumbnailCaptionIsHtmlEscaped(): void {
		$html = $this->renderThumbnail( $this->newFileRenderingThumbnails(), caption: '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function testThumbnailWithoutExplicitWidthUsesConfiguredDefault(): void {
		$html = $this->renderThumbnail( $this->newFileRenderingThumbnails(), defaultThumbnailWidth: 250 );

		$this->assertStringContainsString( '250px-Chart.png', $html );
	}

	public function testThumbnailUsesExplicitWidthOverConfiguredDefault(): void {
		$html = $this->renderThumbnail(
			$this->newFileRenderingThumbnails(),
			width: 120,
			defaultThumbnailWidth: 250
		);

		$this->assertStringContainsString( '120px-Chart.png', $html );
		$this->assertStringNotContainsString( '250px-Chart.png', $html );
	}

	public function testThumbnailUsesExplicitAltTextForImageWhileShowingCaption(): void {
		$html = $this->renderThumbnail(
			$this->newFileRenderingThumbnails(),
			altText: 'Bar chart of revenue',
			caption: 'Quarterly revenue'
		);

		$this->assertStringContainsString( 'alt="Bar chart of revenue"', $html );
		$this->assertStringContainsString( '<figcaption>Quarterly revenue</figcaption>', $html );
	}

	public function testMissingThumbnailRendersFramedBoxWithCaptionAndUploadLink(): void {
		$html = $this->renderMissingFile( thumbnail: true, caption: 'Quarterly revenue' );

		$this->assertStringContainsString( 'typeof="mw:Error mw:File/Thumb"', $html );
		$this->assertStringContainsString( '<figcaption>Quarterly revenue</figcaption>', $html );
		$this->assertStringContainsString( 'Special:Upload', $html );
	}

	public function testMissingThumbnailCaptionIsHtmlEscaped(): void {
		$html = $this->renderMissingFile( thumbnail: true, caption: '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function testMissingInlineFileStaysPlainBrokenLinkWithoutFrame(): void {
		$html = $this->renderMissingFile( thumbnail: false, caption: 'Quarterly revenue' );

		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringContainsString( 'Special:Upload', $html );
	}

	public function testPreloadedFileRendersWithoutPerEmbedLookup(): void {
		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'findFiles' )->willReturn( [ 'Chart.png' => $this->newFileRenderingThumbnails() ] );
		$repoGroup->expects( $this->never() )->method( 'findFile' );

		$renderer = $this->newRendererWithRepoGroup( $repoGroup );
		$renderer->preloadFiles( [ $this->chartTitle() ] );

		$this->assertStringContainsString( '<img', $renderer->renderEmbed( $this->chartEmbed() ) );
	}

	public function testPreloadedMissingFileRendersUploadLinkWithoutPerEmbedLookup(): void {
		// The upload red link only appears where uploads are enabled; vanilla
		// MediaWiki defaults them off, so pin it for a deterministic assertion.
		$this->overrideConfigValue( MainConfigNames::EnableUploads, true );

		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'findFiles' )->willReturn( [] );
		$repoGroup->expects( $this->never() )->method( 'findFile' );

		$renderer = $this->newRendererWithRepoGroup( $repoGroup );
		$renderer->preloadFiles( [ $this->chartTitle() ] );

		$this->assertStringContainsString( 'Special:Upload', $renderer->renderEmbed( $this->chartEmbed() ) );
	}

	private function newRendererWithRepoGroup(
		RepoGroup $repoGroup,
		int $defaultThumbnailWidth = 300
	): MediaWikiFileEmbedRenderer {
		return new MediaWikiFileEmbedRenderer(
			repoGroup: $repoGroup,
			linkRenderer: $this->getServiceContainer()->getLinkRenderer(),
			defaultThumbnailWidth: $defaultThumbnailWidth
		);
	}

	private function chartTitle(): WikiTitle {
		return new WikiTitle( namespace: NS_FILE, dbKey: 'Chart.png', prefixedText: 'File:Chart.png' );
	}

	private function chartEmbed(): FileEmbed {
		return new FileEmbed(
			title: $this->chartTitle(),
			width: 300,
			altText: 'A chart',
			caption: null,
			thumbnail: false
		);
	}

	private function renderThumbnail(
		File $file,
		?int $width = null,
		?string $altText = null,
		?string $caption = null,
		int $defaultThumbnailWidth = 300
	): string {
		$repoGroup = $this->createStub( RepoGroup::class );
		$repoGroup->method( 'findFile' )->willReturn( $file );

		return $this->newRendererWithRepoGroup( $repoGroup, $defaultThumbnailWidth )->renderEmbed(
			new FileEmbed(
				title: $this->chartTitle(),
				width: $width,
				altText: $altText,
				caption: $caption,
				thumbnail: true
			)
		);
	}

	private function renderMissingFile(
		bool $thumbnail,
		?string $caption,
		?string $altText = null
	): string {
		// The upload red link only appears where uploads are enabled; vanilla
		// MediaWiki defaults them off, so pin it for a deterministic assertion.
		$this->overrideConfigValue( MainConfigNames::EnableUploads, true );

		$repoGroup = $this->createStub( RepoGroup::class );
		$repoGroup->method( 'findFile' )->willReturn( false );

		return $this->newRendererWithRepoGroup( $repoGroup )->renderEmbed(
			new FileEmbed(
				title: $this->chartTitle(),
				width: null,
				altText: $altText,
				caption: $caption,
				thumbnail: $thumbnail
			)
		);
	}

	private function newFileWithFailingTransform(): File {
		$file = $this->newExistingInlineFile();
		$file->method( 'transform' )->willReturn(
			new MediaTransformError( 'thumbnail_error', 300, 200 )
		);

		return $file;
	}

	private function newFileRenderingThumbnails(): File {
		$file = $this->newExistingInlineFile();
		$file->method( 'transform' )->willReturnCallback(
			static fn ( array $params ) => new ThumbnailImage(
				$file,
				'https://wiki.example/images/' . $params['width'] . 'px-Chart.png',
				false,
				[ 'width' => $params['width'], 'height' => (int)( $params['width'] * 2 / 3 ) ]
			)
		);

		return $file;
	}

	/**
	 * @return File&\PHPUnit\Framework\MockObject\Stub
	 */
	private function newExistingInlineFile() {
		$file = $this->createStub( File::class );
		$file->method( 'exists' )->willReturn( true );
		$file->method( 'allowInlineDisplay' )->willReturn( true );
		$file->method( 'getWidth' )->willReturn( 1200 );
		$file->method( 'getTitle' )->willReturn(
			$this->getServiceContainer()->getTitleFactory()->makeTitle( NS_FILE, 'Chart.png' )
		);

		return $file;
	}

}
