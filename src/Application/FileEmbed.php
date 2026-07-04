<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NativeMarkdown\Application;

/**
 * A file embedded by the page, such as via `[[File:Cat.png|300px|alt=A cat|Caption]]`.
 * Supports the minimal wikitext parameter subset: width, alt and caption.
 */
final class FileEmbed {

	public function __construct(
		public readonly WikiTitle $title,
		public readonly ?int $width,
		public readonly ?string $altText,
		public readonly ?string $caption
	) {
	}

	/**
	 * The text standing in for the embed where an image cannot render,
	 * such as inside a markdown link: the first non-empty of alt text,
	 * caption and file name.
	 */
	public function plainTextLabel(): string {
		foreach ( [ $this->altText, $this->caption ] as $candidate ) {
			if ( $candidate !== null && $candidate !== '' ) {
				return $candidate;
			}
		}

		return $this->title->prefixedText;
	}

	/**
	 * @param string|null $paramText Pipe-separated parameters, as wikitext does it:
	 * `NNNpx` sets the width, `alt=` sets the alt text, and the last remaining
	 * parameter becomes the caption. Height-only and zero sizes are ignored.
	 */
	public static function fromTitleAndParams( WikiTitle $title, ?string $paramText ): self {
		$width = null;
		$altText = null;
		$caption = null;

		foreach ( explode( '|', $paramText ?? '' ) as $param ) {
			$param = trim( $param );

			if ( $param === '' ) {
				continue;
			}

			if ( preg_match( '/^(\d+)(?:x\d+)?px$/', $param, $matches ) === 1 ) {
				$parsedWidth = (int)$matches[1];

				if ( $parsedWidth > 0 ) {
					$width = $parsedWidth;
				}

				continue;
			}

			if ( preg_match( '/^x\d+px$/', $param ) === 1 ) {
				continue;
			}

			if ( str_starts_with( $param, 'alt=' ) ) {
				$altText = trim( substr( $param, 4 ) );
				continue;
			}

			$caption = $param;
		}

		return new self( title: $title, width: $width, altText: $altText, caption: $caption );
	}

}
