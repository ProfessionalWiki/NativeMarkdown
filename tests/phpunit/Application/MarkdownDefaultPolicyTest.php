<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy
 */
class MarkdownDefaultPolicyTest extends TestCase {

	private const HELP_NAMESPACE = 12;
	private const PROJECT_NAMESPACE = 4;
	private const MAIN_NAMESPACE = 0;

	public function testAppliesInConfiguredNamespace(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::PROJECT_NAMESPACE, self::HELP_NAMESPACE ],
			everywhere: false,
			suffixDetection: false
		);

		$this->assertTrue( $policy->appliesTo( self::HELP_NAMESPACE, false, 'Whatever', pageExists: false ) );
	}

	public function testDoesNotApplyInUnconfiguredNamespace(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::HELP_NAMESPACE ],
			everywhere: false,
			suffixDetection: false
		);

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Whatever', pageExists: false ) );
	}

	public function testEverywhereAppliesInContentNamespace(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: true, suffixDetection: false );

		$this->assertTrue( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Whatever', pageExists: false ) );
	}

	public function testEverywhereDoesNotApplyOutsideContentNamespaces(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: true, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::HELP_NAMESPACE, false, 'Whatever', pageExists: false ) );
	}

	public function testSuffixDetectionMatchesMdTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: true );

		$this->assertTrue( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Release Notes.md', pageExists: false ) );
	}

	public function testSuffixDetectionIgnoresOtherTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: true );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Release Notes.txt', pageExists: false ) );
	}

	public function testDisabledSuffixDetectionIgnoresMdTitles(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Release Notes.md', pageExists: false ) );
	}

	public function testNothingConfiguredNeverApplies(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Whatever', pageExists: false ) );
	}

	public function testNeverAppliesToExistingPages(): void {
		$policy = new MarkdownDefaultPolicy(
			namespaces: [ self::MAIN_NAMESPACE ],
			everywhere: true,
			suffixDetection: true
		);

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Page.md', pageExists: true ) );
	}

	public function testJavascriptPageTitleNeverDefaultsToMarkdown(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [ self::MAIN_NAMESPACE ], everywhere: true, suffixDetection: true );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'User/common.js', pageExists: false ) );
	}

	public function testCssPageTitleNeverDefaultsToMarkdown(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [ self::MAIN_NAMESPACE ], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Site.css', pageExists: false ) );
	}

	public function testJsonPageTitleNeverDefaultsToMarkdown(): void {
		$policy = new MarkdownDefaultPolicy( namespaces: [ self::MAIN_NAMESPACE ], everywhere: false, suffixDetection: false );

		$this->assertFalse( $policy->appliesTo( self::MAIN_NAMESPACE, true, 'Config.json', pageExists: false ) );
	}

}
