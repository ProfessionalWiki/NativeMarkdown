<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\EntryPoints;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NativeMarkdown\NativeMarkdownExtension;

final class NativeMarkdownHooks {

	/**
	 * Makes new pages default to the markdown content model where the wiki's
	 * configuration says so. Existing pages always keep their stored model.
	 *
	 * Sets $model without aborting the hook chain, so a namespace whose content model
	 * is set by a handler that runs after ours (such as Scribunto's for Module) still
	 * wins, independent of extension load order.
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		// Only fill in where MediaWiki would otherwise fall back to wikitext. A
		// namespace with an explicitly configured model (Scribunto, JSON, ...) already
		// has $model set here, and must be left untouched.
		if ( $model !== null ) {
			return;
		}

		$isTalkNamespace = MediaWikiServices::getInstance()->getNamespaceInfo()
			->isTalk( $title->getNamespace() );

		$applies = NativeMarkdownExtension::getInstance()->newMarkdownDefaultPolicy()->appliesTo(
			$title->getNamespace(),
			$isTalkNamespace,
			$title->getText(),
			$title->exists()
		);

		if ( $applies ) {
			$model = NativeMarkdownExtension::CONTENT_MODEL;
		}
	}

	/**
	 * Gives markdown pages markdown syntax highlighting in CodeEditor.
	 *
	 * @return bool|void
	 */
	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ) {
		if ( $model === NativeMarkdownExtension::CONTENT_MODEL ) {
			$lang = 'markdown';
			return false;
		}
	}

}
