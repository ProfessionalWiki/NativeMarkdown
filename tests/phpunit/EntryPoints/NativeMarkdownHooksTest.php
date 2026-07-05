<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\EntryPoints;

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NativeMarkdown\EntryPoints\NativeMarkdownHooks;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\EntryPoints\NativeMarkdownHooks
 * @group Database
 */
class NativeMarkdownHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * Pins all activation settings so the surrounding wiki's configuration
	 * cannot leak into these tests.
	 *
	 * @param int[] $namespaces
	 */
	private function configureActivation(
		array $namespaces = [],
		bool $everywhere = false,
		bool $suffixDetection = false
	): void {
		$this->overrideConfigValues( [
			'NativeMarkdownNamespaces' => $namespaces,
			'NativeMarkdownEverywhere' => $everywhere,
			'NativeMarkdownSuffixDetection' => $suffixDetection,
		] );
	}

	public function testNewPageInConfiguredNamespaceDefaultsToMarkdown(): void {
		$this->configureActivation( namespaces: [ NS_HELP ] );

		$title = Title::makeTitle( NS_HELP, 'Native Markdown Fresh Page' );

		$this->assertSame( 'markdown', $title->getContentModel() );
	}

	public function testNewPageOutsideConfiguredNamespacesDefaultsToWikitext(): void {
		$this->configureActivation( namespaces: [ NS_HELP ] );

		$title = Title::makeTitle( NS_MAIN, 'Native Markdown Fresh Page' );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $title->getContentModel() );
	}

	public function testMdSuffixDefaultsToMarkdownWhenDetectionEnabled(): void {
		$this->configureActivation( suffixDetection: true );

		$title = Title::makeTitle( NS_MAIN, 'Release Notes.md' );

		$this->assertSame( 'markdown', $title->getContentModel() );
	}

	public function testMdSuffixKeepsWikitextWhenDetectionDisabled(): void {
		$this->configureActivation( suffixDetection: false );

		$title = Title::makeTitle( NS_MAIN, 'Release Notes.md' );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $title->getContentModel() );
	}

	public function testEverywhereDefaultsMainNamespaceToMarkdown(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			'markdown',
			Title::makeTitle( NS_MAIN, 'Native Markdown Fresh Page' )->getContentModel()
		);
	}

	public function testEverywhereDefaultsHelpNamespaceToMarkdown(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			'markdown',
			Title::makeTitle( NS_HELP, 'Editing Guide' )->getContentModel()
		);
	}

	public function testEverywhereDefaultsUserNamespaceToMarkdown(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			'markdown',
			Title::makeTitle( NS_USER, 'Jeroen' )->getContentModel()
		);
	}

	public function testEverywhereLeavesTalkNamespacesWikitext(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_TALK, 'An Article' )->getContentModel()
		);
	}

	public function testEverywhereLeavesTemplateNamespaceWikitext(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_TEMPLATE, 'Infobox' )->getContentModel()
		);
	}

	public function testEverywhereLeavesMediaWikiNamespaceWikitext(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_MEDIAWIKI, 'Sidebar' )->getContentModel()
		);
	}

	public function testEverywhereRespectsExplicitlyConfiguredNamespaceModel(): void {
		$this->overrideConfigValue(
			MainConfigNames::NamespaceContentModels,
			[ NS_HELP => CONTENT_MODEL_JSON ]
		);
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			CONTENT_MODEL_JSON,
			Title::makeTitle( NS_HELP, 'Config Page' )->getContentModel()
		);
	}

	public function testUserScriptSubpageKeepsJavascriptModelUnderEverywhere(): void {
		$this->configureActivation( everywhere: true );

		$this->assertSame(
			CONTENT_MODEL_JAVASCRIPT,
			Title::makeTitle( NS_USER, 'SomeUser/common.js' )->getContentModel()
		);
	}

	public function testExistingPageKeepsItsContentModel(): void {
		$this->configureActivation();
		$this->editPage( Title::makeTitle( NS_HELP, 'Existing Wikitext Page' ), 'some wikitext' );

		$this->configureActivation( namespaces: [ NS_HELP ] );

		$title = Title::makeTitle( NS_HELP, 'Existing Wikitext Page' );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $title->getContentModel() );
	}

	public function testCodeEditorUsesMarkdownHighlightingForMarkdownModel(): void {
		$language = null;
		NativeMarkdownHooks::onCodeEditorGetPageLanguage(
			Title::makeTitle( NS_MAIN, 'Whatever' ),
			$language,
			'markdown',
			CONTENT_FORMAT_TEXT
		);

		$this->assertSame( 'markdown', $language );
	}

	public function testCodeEditorLanguageUntouchedForOtherModels(): void {
		$language = null;
		NativeMarkdownHooks::onCodeEditorGetPageLanguage(
			Title::makeTitle( NS_MAIN, 'Whatever' ),
			$language,
			CONTENT_MODEL_WIKITEXT,
			CONTENT_FORMAT_WIKITEXT
		);

		$this->assertNull( $language );
	}

}
