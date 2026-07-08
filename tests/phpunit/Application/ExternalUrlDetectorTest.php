<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector;

/**
 * @covers \ProfessionalWiki\NativeMarkdown\Application\ExternalUrlDetector
 */
class ExternalUrlDetectorTest extends TestCase {

	private function newDetector(): ExternalUrlDetector {
		return new ExternalUrlDetector( [ '//', 'http://', 'https://', 'ftp://', 'mailto:' ] );
	}

	public function testHttpsUrlIsExternal(): void {
		$this->assertTrue( $this->newDetector()->isExternalUrl( 'https://example.com/page' ) );
	}

	public function testProtocolRelativeUrlIsExternal(): void {
		$this->assertTrue( $this->newDetector()->isExternalUrl( '//example.com/page' ) );
	}

	public function testColonSchemeProtocolWithoutSlashesIsExternal(): void {
		$this->assertTrue( $this->newDetector()->isExternalUrl( 'mailto:person@example.com' ) );
	}

	public function testSchemeIsMatchedCaseInsensitively(): void {
		$this->assertTrue( $this->newDetector()->isExternalUrl( 'HTTPS://example.com' ) );
	}

	public function testUnlistedSchemeWithSlashesIsStillExternal(): void {
		$this->assertTrue( $this->newDetector()->isExternalUrl( 'gopher://example.com' ) );
	}

	public function testNamespacedTitleIsNotExternalDespiteColon(): void {
		$this->assertFalse( $this->newDetector()->isExternalUrl( 'Help:Example' ) );
	}

	public function testPlainPageTitleIsNotExternal(): void {
		$this->assertFalse( $this->newDetector()->isExternalUrl( 'Some_Page' ) );
	}

	public function testRootRelativePathIsNotExternal(): void {
		$this->assertFalse( $this->newDetector()->isExternalUrl( '/wiki/Some_Page' ) );
	}

	public function testEmptyStringIsNotExternal(): void {
		$this->assertFalse( $this->newDetector()->isExternalUrl( '' ) );
	}

}
