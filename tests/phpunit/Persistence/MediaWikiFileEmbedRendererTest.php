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

		$renderer = new MediaWikiFileEmbedRenderer(
			repoGroup: $repoGroup,
			linkRenderer: $this->getServiceContainer()->getLinkRenderer()
		);

		return $renderer->renderEmbed( new FileEmbed(
			title: new WikiTitle( namespace: NS_FILE, dbKey: 'Chart.png', prefixedText: 'File:Chart.png' ),
			width: $width,
			altText: 'A chart',
			caption: null
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

	public function testPreloadedFileRendersWithoutPerEmbedLookup(): void {
		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'findFiles' )->willReturn( [ 'Chart.png' => $this->newFileRenderingThumbnails() ] );
		$repoGroup->expects( $this->never() )->method( 'findFile' );

		$renderer = $this->newRendererWithRepoGroup( $repoGroup );
		$renderer->preloadFiles( [ $this->chartTitle() ] );

		$this->assertStringContainsString( '<img', $renderer->renderEmbed( $this->chartEmbed() ) );
	}

	public function testPreloadedMissingFileRendersUploadLinkWithoutPerEmbedLookup(): void {
		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'findFiles' )->willReturn( [] );
		$repoGroup->expects( $this->never() )->method( 'findFile' );

		$renderer = $this->newRendererWithRepoGroup( $repoGroup );
		$renderer->preloadFiles( [ $this->chartTitle() ] );

		$this->assertStringContainsString( 'Special:Upload', $renderer->renderEmbed( $this->chartEmbed() ) );
	}

	private function newRendererWithRepoGroup( RepoGroup $repoGroup ): MediaWikiFileEmbedRenderer {
		return new MediaWikiFileEmbedRenderer(
			repoGroup: $repoGroup,
			linkRenderer: $this->getServiceContainer()->getLinkRenderer()
		);
	}

	private function chartTitle(): WikiTitle {
		return new WikiTitle( namespace: NS_FILE, dbKey: 'Chart.png', prefixedText: 'File:Chart.png' );
	}

	private function chartEmbed(): FileEmbed {
		return new FileEmbed( title: $this->chartTitle(), width: 300, altText: 'A chart', caption: null );
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
