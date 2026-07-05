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
	 * @return bool|void
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ) {
		$namespaceIsContent = MediaWikiServices::getInstance()->getNamespaceInfo()
			->isContent( $title->getNamespace() );

		$applies = NativeMarkdownExtension::getInstance()->newMarkdownDefaultPolicy()->appliesTo(
			$title->getNamespace(),
			$namespaceIsContent,
			$title->getText(),
			$title->exists()
		);

		if ( $applies ) {
			$model = NativeMarkdownExtension::CONTENT_MODEL;
			return false;
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
