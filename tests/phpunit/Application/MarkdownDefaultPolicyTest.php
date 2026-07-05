<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy
 */
class MarkdownDefaultPolicyTest extends TestCase {

	private const MAIN_NAMESPACE = 0;
	private const TALK_NAMESPACE = 1;
	private const USER_NAMESPACE = 2;
	private const PROJECT_NAMESPACE = 4;
	private const FILE_NAMESPACE = 6;
	private const MEDIAWIKI_NAMESPACE = 8;
	private const TEMPLATE_NAMESPACE = 10;
	private const HELP_NAMESPACE = 12;
	private const CATEGORY_NAMESPACE = 14;

	public function testAppliesInConfiguredNamespace(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::PROJECT_NAMESPACE, self::HELP_NAMESPACE ],
			everywhere: false,
			suffixDetection: false
		);

		$this->assertTrue( $policy->appliesTo( self::HELP_NAMESPACE, isTalkNamespace: false, titleText: 'Whatever', pageExists: false ) );
	}

	public function testDoesNotApplyInUnconfiguredNamespace(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::HELP_NAMESPACE ],
			everywhere: false,
			suffixDetection: false
		);

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Whatever', pageExists: false ) );
	}

	public function testEverywhereAppliesInMainNamespace(): void {
		$this->assertTrue( $this->everywherePolicy()->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'An Article', pageExists: false ) );
	}

	public function testEverywhereAppliesInUserNamespace(): void {
		$this->assertTrue( $this->everywherePolicy()->appliesTo( self::USER_NAMESPACE, isTalkNamespace: false, titleText: 'Jeroen', pageExists: false ) );
	}

	public function testEverywhereAppliesInHelpNamespace(): void {
		$this->assertTrue( $this->everywherePolicy()->appliesTo( self::HELP_NAMESPACE, isTalkNamespace: false, titleText: 'Editing', pageExists: false ) );
	}

	public function testEverywhereAppliesInProjectFileAndCategoryNamespaces(): void {
		$policy = $this->everywherePolicy();

		$this->assertTrue( $policy->appliesTo( self::PROJECT_NAMESPACE, isTalkNamespace: false, titleText: 'About', pageExists: false ) );
		$this->assertTrue( $policy->appliesTo( self::FILE_NAMESPACE, isTalkNamespace: false, titleText: 'Logo.png', pageExists: false ) );
		$this->assertTrue( $policy->appliesTo( self::CATEGORY_NAMESPACE, isTalkNamespace: false, titleText: 'Guides', pageExists: false ) );
	}

	public function testEverywhereExcludesTalkNamespaces(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::TALK_NAMESPACE, isTalkNamespace: true, titleText: 'An Article', pageExists: false ) );
	}

	public function testEverywhereExcludesTemplateNamespace(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::TEMPLATE_NAMESPACE, isTalkNamespace: false, titleText: 'Infobox', pageExists: false ) );
	}

	public function testEverywhereExcludesMediaWikiNamespace(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::MEDIAWIKI_NAMESPACE, isTalkNamespace: false, titleText: 'Sidebar', pageExists: false ) );
	}

	public function testExplicitNamespaceListOptsInEvenToOtherwiseExcludedNamespaces(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::TEMPLATE_NAMESPACE ],
			everywhere: false,
			suffixDetection: false
		);

		$this->assertTrue( $policy->appliesTo( self::TEMPLATE_NAMESPACE, isTalkNamespace: false, titleText: 'Infobox', pageExists: false ) );
	}

	public function testSuffixDetectionMatchesMdTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: true );

		$this->assertTrue( $policy->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Release Notes.md', pageExists: false ) );
	}

	public function testSuffixDetectionIgnoresOtherTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: true );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Release Notes.txt', pageExists: false ) );
	}

	public function testDisabledSuffixDetectionIgnoresMdTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Release Notes.md', pageExists: false ) );
	}

	public function testNothingConfiguredNeverApplies(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Whatever', pageExists: false ) );
	}

	public function testNeverAppliesToExistingPages(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'An Article', pageExists: true ) );
	}

	public function testJavascriptPageTitleNeverDefaultsToMarkdown(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::USER_NAMESPACE, isTalkNamespace: false, titleText: 'Someone/common.js', pageExists: false ) );
	}

	public function testCssPageTitleNeverDefaultsToMarkdown(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Site.css', pageExists: false ) );
	}

	public function testJsonPageTitleNeverDefaultsToMarkdown(): void {
		$this->assertFalse( $this->everywherePolicy()->appliesTo( self::MAIN_NAMESPACE, isTalkNamespace: false, titleText: 'Config.json', pageExists: false ) );
	}

	private function everywherePolicy(): MarkdownDefaultPolicy {
		return new MarkdownDefaultPolicy( namespaces: [], everywhere: true, suffixDetection: false );
	}

}
