<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests\TestDoubles;

use ProfessionalWiki\NativeMarkdown\Application\CodeHighlighter;

/**
 * Returns canned HTML and records the code and language of every call, so tests
 * can assert the pipeline injects the highlighter's HTML, passes the right
 * language and code, and reports its modules, without a real Pygments install.
 * Constructed with a null html to simulate a highlighter declining a block.
 */
final class FakeCodeHighlighter implements CodeHighlighter {

	/** @var array<array{code: string, language: string}> */
	public array $calls = [];

	/**
	 * @param string[] $modules
	 * @param string[] $styleModules
	 */
	public function __construct(
		private readonly ?string $html = '<div class="fake-highlight">HIGHLIGHTED</div>',
		private readonly array $modules = [ 'test.pygments.view' ],
		private readonly array $styleModules = [ 'test.pygments' ]
	) {
	}

	public function highlight( string $code, string $language ): ?string {
		$this->calls[] = [ 'code' => $code, 'language' => $language ];

		return $this->html;
	}

	public function modules(): array {
		return $this->modules;
	}

	public function styleModules(): array {
		return $this->styleModules;
	}

}
