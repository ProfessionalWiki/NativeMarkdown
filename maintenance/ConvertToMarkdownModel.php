<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
/** @psalm-suppress UnresolvableInclude */
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NativeMarkdown\Application\MarkdownDefaultPolicy;
use ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;

/**
 * Converts existing wikitext pages to the Markdown content model.
 *
 * The activation settings ($wgNativeMarkdownSuffixDetection, $wgNativeMarkdownNamespaces) only
 * default *new* pages to Markdown; pages that already exist keep their stored model. This script
 * is their retroactive counterpart: it selects existing pages the same way those settings would,
 * reusing MarkdownDefaultPolicy so the two cannot drift, and switches them to the Markdown model.
 *
 * It changes the content model, not the page text: a page's stored wikitext is reinterpreted as
 * Markdown, which can render differently, so --dry-run lists what would be converted first.
 */
final class ConvertToMarkdownModel extends Maintenance {

	private const EDIT_SUMMARY = 'Converting to the Markdown content model';

	private const DEFAULT_BATCH_SIZE = 100;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Convert existing wikitext pages to the Markdown content model. This changes the content ' .
			'model, not the page text, so run with --dry-run first to review the selection.'
		);

		$this->addOption(
			'md-suffix',
			'Select pages the live .md suffix detection would default to Markdown: titles ending in ' .
			'.md, outside the Template and MediaWiki namespaces. Combined with --namespace it instead ' .
			'selects the .md titles within that one namespace (which may be Template or MediaWiki).'
		);
		$this->addOption(
			'namespace',
			'Select every wikitext page in this namespace ID, mirroring $wgNativeMarkdownNamespaces. An ' .
			'explicit namespace is a deliberate choice, so this works for any namespace, code pages aside.',
			false,
			true
		);
		$this->addOption( 'dry-run', 'List the pages that would be converted without changing anything.' );

		$this->setBatchSize( self::DEFAULT_BATCH_SIZE );

		$this->requireExtension( 'Native Markdown' );
	}

	public function execute(): void {
		if ( !$this->hasOption( 'md-suffix' ) && !$this->hasOption( 'namespace' ) ) {
			$this->fatalError( 'Select pages to convert with --md-suffix, --namespace <id>, or both.' );
		}

		if ( $this->hasOption( 'namespace' ) && !ctype_digit( (string)$this->getOption( 'namespace' ) ) ) {
			// Reject a name where an ID is expected, so a typo like --namespace Help does not
			// silently fall back to namespace 0 and convert the main namespace.
			$this->fatalError( 'The --namespace value must be a namespace ID (a non-negative integer).' );
		}

		$dryRun = $this->hasOption( 'dry-run' );
		$policy = $this->newSelectionPolicy();
		$performer = $dryRun ? null : $this->newPerformer();

		$candidateCount = 0;
		$failureCount = 0;
		$lastPageId = 0;
		$batchSize = max( 1, $this->getBatchSize() ?? self::DEFAULT_BATCH_SIZE );

		do {
			$rows = $this->selectBatch( $lastPageId, $batchSize );
			$batchRowCount = $rows->numRows();

			foreach ( $rows as $row ) {
				/** @var \stdClass $row */
				$lastPageId = (int)$row->page_id;
				$title = Title::newFromRow( $row );

				if ( !$this->isConvertible( $title, $policy ) ) {
					continue;
				}

				$candidateCount++;

				if ( $performer !== null && !$this->convertPage( $title, $performer ) ) {
					$failureCount++;
					continue;
				}

				$this->output( $title->getPrefixedText() . "\n" );
			}

			$this->waitForReplication();
		} while ( $batchRowCount === $batchSize );

		$this->reportSummary( $dryRun, $candidateCount, $candidateCount - $failureCount );

		if ( $failureCount > 0 ) {
			$this->fatalError( "$failureCount pages could not be converted; see the errors above." );
		}
	}

	/**
	 * The policy that decides, config-independently, which pages this run selects. It is built
	 * directly rather than from live wiki config, so the selectors mean the same on every wiki.
	 */
	private function newSelectionPolicy(): MarkdownDefaultPolicy {
		if ( $this->hasOption( 'namespace' ) ) {
			// Namespace mode, alone or combined: mirror $wgNativeMarkdownNamespaces for the one
			// requested namespace. Combined mode layers the .md title check on top (isConvertible).
			return new MarkdownDefaultPolicy(
				namespaces: [ $this->namespaceOption() ],
				everywhere: false,
				suffixDetection: false
			);
		}

		// --md-suffix alone: mirror the live suffix detection, namespace rules and all.
		return new MarkdownDefaultPolicy(
			namespaces: [],
			everywhere: false,
			suffixDetection: true
		);
	}

	private function namespaceOption(): int {
		return (int)$this->getOption( 'namespace' );
	}

	private function newPerformer(): User {
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

		if ( $user === null ) {
			$this->fatalError( 'Could not obtain the maintenance system user to attribute conversions to.' );
		}

		return $user;
	}

	/**
	 * A coarse batch of pages that could be candidates: current model wikitext (stored as such or
	 * left at the default), not a redirect, matching the selected namespace and/or .md suffix. The
	 * authoritative per-page checks live in isConvertible(); this only narrows the scan cheaply.
	 */
	private function selectBatch( int $afterPageId, int $batchSize ): IResultWrapper {
		$db = $this->getReplicaDB();

		$builder = $db->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_content_model' => [ null, $this->wikitextModel() ],
				'page_is_redirect' => 0,
			] )
			->andWhere( $db->buildComparison( '>', [ 'page_id' => $afterPageId ] ) )
			->orderBy( 'page_id' )
			->limit( $batchSize )
			->caller( __METHOD__ );

		if ( $this->hasOption( 'namespace' ) ) {
			$builder->andWhere( [ 'page_namespace' => $this->namespaceOption() ] );
		}

		if ( $this->hasOption( 'md-suffix' ) ) {
			// page_title is the underscored DB key, but a trailing .md is unaffected by that.
			$builder->andWhere(
				$db->expr( 'page_title', IExpression::LIKE, new LikeValue( $db->anyString(), '.md' ) )
			);
		}

		return $builder->fetchResultSet();
	}

	/**
	 * @psalm-suppress UndefinedConstant CONTENT_MODEL_WIKITEXT is a MediaWiki global constant psalm
	 *   cannot resolve from the scanned core files.
	 */
	private function wikitextModel(): string {
		return (string)CONTENT_MODEL_WIKITEXT;
	}

	/**
	 * Whether the page really qualifies, checked authoritatively rather than trusting the coarse
	 * query: its resolved current model must be wikitext (page_content_model can be NULL, which
	 * resolves through the default model), and the selection policy must accept it.
	 */
	private function isConvertible( Title $title, MarkdownDefaultPolicy $policy ): bool {
		if ( $title->getContentModel() !== $this->wikitextModel() ) {
			return false;
		}

		$isTalkNamespace = $this->getServiceContainer()->getNamespaceInfo()->isTalk( $title->getNamespace() );

		// pageExists is passed false deliberately: appliesTo() rejects every existing page, so we
		// ask whether the title *would* default to Markdown if freshly created.
		$applies = $policy->appliesTo( $title->getNamespace(), $isTalkNamespace, $title->getText(), false );

		if ( !$applies ) {
			return false;
		}

		return !$this->requiresMdSuffixTitle() || str_ends_with( $title->getText(), '.md' );
	}

	/**
	 * In combined mode the explicit namespace is the deliberate namespace choice, so --md-suffix
	 * contributes only its title condition: the page must also end in .md.
	 */
	private function requiresMdSuffixTitle(): bool {
		return $this->hasOption( 'md-suffix' ) && $this->hasOption( 'namespace' );
	}

	private function convertPage( Title $title, User $performer ): bool {
		$change = $this->getServiceContainer()
			->getContentModelChangeFactory()
			->newContentModelChange( $performer, $title, NativeMarkdownExtension::CONTENT_MODEL );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$context->setUser( $performer );

		$status = $change->doContentModelChange( $context, self::EDIT_SUMMARY, true );

		if ( $status->isGood() ) {
			return true;
		}

		$this->error( 'Failed to convert ' . $title->getPrefixedText() . ': ' . $this->describeStatus( $status ) );
		return false;
	}

	private function describeStatus( Status $status ): string {
		return trim(
			$this->getServiceContainer()->getFormatterFactory()
				->getStatusFormatter( RequestContext::getMain() )
				->getWikiText( $status )
		);
	}

	private function reportSummary( bool $dryRun, int $candidateCount, int $convertedCount ): void {
		if ( $dryRun ) {
			$this->output( "$candidateCount pages would be converted.\n" );
			return;
		}

		$this->output( "Converted $convertedCount of $candidateCount candidate pages.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = ConvertToMarkdownModel::class;
/** @psalm-suppress UndefinedConstant, UnresolvableInclude */
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
