<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application\CommonMark;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Accumulates the lines of a block-level `{{...}}` call until the braces
 * balance, then stores the raw wikitext on the TemplateCallBlock. A call that
 * never closes before the end of input is marked unbalanced and later degrades
 * to escaped literal text rather than being expanded.
 */
final class TemplateCallBlockParser extends AbstractBlockContinueParser {

	private TemplateCallBlock $block;

	/** @var string[] */
	private array $lines = [];
	private int $depth = 0;
	private bool $finished = false;

	public function __construct() {
		$this->block = new TemplateCallBlock();
	}

	public function getBlock(): TemplateCallBlock {
		return $this->block;
	}

	public function tryContinue( Cursor $cursor, BlockContinueParserInterface $activeBlockParser ): ?BlockContinue {
		if ( $this->finished ) {
			return BlockContinue::none();
		}

		return BlockContinue::at( $cursor );
	}

	public function addLine( string $line ): void {
		$this->lines[] = $line;
		$this->depth += TemplateBraces::depthDelta( $line );

		if ( $this->depth <= 0 ) {
			$this->finished = true;
		}
	}

	public function closeBlock(): void {
		$this->block->wikitext = trim( implode( "\n", $this->lines ) );
		$this->block->balanced = $this->depth === 0;
	}

}
