<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Tests;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use Wikimedia\Parsoid\Core\LinkTarget;

/**
 * The link targets a ParserOutput records for the link tables, shared by the
 * tests that check what a render registers.
 */
final class RecordedLinks {

	/**
	 * @return LinkTarget[]
	 */
	public static function pages( ParserOutput $output ): array {
		return array_column( $output->getLinkList( ParserOutputLinkTypes::LOCAL ), 'link' );
	}

	/**
	 * @return LinkTarget[]
	 */
	public static function files( ParserOutput $output ): array {
		return array_column( $output->getLinkList( ParserOutputLinkTypes::MEDIA ), 'link' );
	}

	/**
	 * @return LinkTarget[]
	 */
	public static function templates( ParserOutput $output ): array {
		return array_column( $output->getLinkList( ParserOutputLinkTypes::TEMPLATE ), 'link' );
	}

	/**
	 * @return LinkTarget[]
	 */
	public static function interwikiPages( ParserOutput $output ): array {
		return array_column( $output->getLinkList( ParserOutputLinkTypes::INTERWIKI ), 'link' );
	}

}
