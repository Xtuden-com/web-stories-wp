<?php
/**
 * Class SVG.
 *
 * @package   Google\Web_Stories
 * @copyright 2020 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://github.com/google/web-stories-wp
 */

/**
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Web_Stories;

use SimpleXMLElement;

/**
 * Class SVG
 *
 * @package Google\Web_Stories
 */
class SVG {
	/**
	 * File extenstion.
	 *
	 * @var string
	 */
	const EXT = 'svg';

	/**
	 * Mime type.
	 *
	 * @var string
	 */
	const MINE_TYPE = 'image/svg+xml';

	/**
	 * Array of SimpleXMLElements.
	 *
	 * @var array
	 */
	protected $svgs = [];

	/**
	 * Experiments instance.
	 *
	 * @var Experiments Experiments instance.
	 */
	private $experiments;

	/**
	 * SVG constructor.
	 *
	 * @param Experiments $experiments Experiments instance.
	 *
	 * @return void
	 */
	public function __construct( Experiments $experiments ) {
		$this->experiments = $experiments;
	}

	/**
	 * Register filters and actions.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! $this->experiments->is_experiment_enabled( 'enableSVG' ) ) {
			return;
		}
		add_filter( 'upload_mimes', [ $this, 'upload_mimes_add_svg' ] ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
		add_filter( 'mime_types', [ $this, 'mime_types_add_svg' ] );
		add_filter( 'web_stories_allowed_mime_types', [ $this, 'web_stories_allowed_mime_types' ] );
		add_filter( 'wp_handle_upload', [ $this, 'wp_handle_upload' ] );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'wp_generate_attachment_metadata' ], 10, 3 );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'wp_check_filetype_and_ext' ], 10, 5 );
		add_filter( 'site_option_upload_filetypes', [ $this, 'filter_list_of_allowed_filetypes' ] );
	}

	/**
	 * Enable SVG upload.
	 *
	 * @param array $mime_types    Mime types keyed by the file extension regex corresponding to those types.
	 *
	 * @return mixed
	 */
	public function upload_mimes_add_svg( array $mime_types ) {
		// allow SVG file upload.
		$mime_types['svg']  = self::MINE_TYPE;
		$mime_types['svgz'] = self::MINE_TYPE;

		return $mime_types;
	}

	/**
	 * Add SVG to allowed mime types.
	 *
	 * @param string[] $mime_types Mime types keyed by the file extension regex
	 *                                 corresponding to those types.
	 *
	 * @return array
	 */
	public function mime_types_add_svg( array $mime_types ) {
		// allow SVG files.
		$mime_types['svg'] = self::MINE_TYPE;

		return $mime_types;
	}

	/**
	 * Add SVG to allowed mime types.
	 *
	 * @param array $mime_types Associative array of allowed mime types per media type (image, audio, video).
	 *
	 * @return array
	 */
	public function web_stories_allowed_mime_types( array $mime_types ) {
		$mime_types['image'][] = self::MINE_TYPE;

		return $mime_types;
	}

	/**
	 * Add svg file type to allow file in multisite.
	 *
	 * @param string $value List of allowed file types.
	 * @return string List of allowed file types.
	 */
	public function filter_list_of_allowed_filetypes( $value ) {
		$filetypes = explode( ' ', $value );
		if ( ! in_array( self::EXT, $filetypes, true ) ) {
			$filetypes[] = self::EXT;
			$value       = implode( ' ', $filetypes );
		}

		return $value;
	}

	/**
	 * Hook into metadata generation and get height and width for SVG file.
	 *
	 * @param array  $metadata      An array of attachment meta data.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $context       Additional context. Can be 'create' when metadata was initially created for new attachment.
	 *
	 * @return array
	 */
	public function wp_generate_attachment_metadata( $metadata, $attachment_id, $context ) {
		if ( 'create' !== $context ) {
			return $metadata;
		}
		$attachment = get_post( $attachment_id );
		$mime_type  = get_post_mime_type( $attachment );

		if ( self::MINE_TYPE !== $mime_type ) {
			return $metadata;
		}
		$file = get_attached_file( $attachment_id );
		if ( false === $file ) {
			return $metadata;
		}
		// Default image meta.
		$size = $this->get_svg_size( $file );

		return [
			'width'    => $size['width'],
			'height'   => $size['height'],
			'file'     => _wp_relative_upload_path( $file ),
			'filesize' => (int) filesize( $file ),
			'sizes'    => [],
		];
	}

	/**
	 * Hook into upload and error if size could not be generated.
	 *
	 * @param array $upload {
	 *    Array of upload data.
	 *
	 *     @type string $file Filename of the newly-uploaded file.
	 *     @type string $url  URL of the newly-uploaded file.
	 *     @type string $type Mime type of the newly-uploaded file.
	 * }
	 *
	 * @return string[]
	 */
	public function wp_handle_upload( $upload ) {
		if ( self::MINE_TYPE !== $upload['type'] ) {
			return $upload;
		}

		$size = $this->get_svg_size( $upload['file'] );
		if ( ! $size['width'] || ! $size['height'] ) {
			return [ 'error' => __( 'Unable to generate SVG image size.', 'web-stories' ) ];
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$has_script = $this->has_script_tags( $upload['file'] );
			if ( $has_script ) {
				return [ 'error' => __( 'You are unable to upload svgs that contains scripts tags.', 'web-stories' ) ];
			}
		}

		return $upload;
	}


	/**
	 * Get SVG image size.
	 *
	 * @param string $file Path to SVG file.
	 *
	 * @return array
	 */
	protected function get_svg_size( $file ) {
		$svg = $this->get_svg_data( $file );

		$width  = 0;
		$height = 0;
		if ( false !== $svg ) {
			$attributes = $svg->attributes();
			$width      = isset( $attributes->width ) ? (int) $attributes->width : 0;
			$height     = isset( $attributes->height ) ? (int) $attributes->height : 0;
			$view_box   = isset( $attributes->viewBox ) ? (string) $attributes->viewBox : '';
			if ( empty( $view_box ) ) {
				$view_box = isset( $attributes->viewbox ) ? (string) $attributes->viewbox : '';
			}

			if ( ( ! $width || ! $height ) && $view_box ) {
				$pieces = explode( ' ', $view_box );
				if ( count( $pieces ) === 4 ) {
					$width  = $pieces[2];
					$height = $pieces[3];
				}
			}
		}

		return compact( 'width', 'height' );
	}

	/**
	 * Check to see if an SVG file has a script tag.
	 *
	 * @param string $file Path to file.
	 *
	 * @return bool
	 */
	public function has_script_tags( $file ) {
		$svg   = $this->get_svg_data( $file );
		$count = 0;
		if ( false !== $svg ) {
			$count = count( $svg->script );
		}

		return ( $count > 0 );
	}

	/**
	 * Work around for incorrect mime type.
	 *
	 * @param array       $wp_check_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 * @param string      $file                      Full path to the file.
	 * @param string      $filename                  The name of the file (may differ from $file due to
	 *                                               $file being in a tmp directory).
	 * @param string[]    $mimes                     Array of mime types keyed by their file extension regex.
	 * @param string|bool $real_mime                 The actual mime type or false if the type cannot be determined.
	 *
	 * @return array
	 */
	public function wp_check_filetype_and_ext( $wp_check_filetype_and_ext, $file, $filename, $mimes, $real_mime ) {
		if ( 'image/svg' === $real_mime ) {
			$wp_check_filetype_and_ext = [
				'ext'             => self::EXT,
				'type'            => self::MINE_TYPE,
				'proper_filename' => false,
			];
		}

		return $wp_check_filetype_and_ext;
	}

	/**
	 * Get SVG data.
	 *
	 * @param string $file File path.
	 *
	 * @return SimpleXMLElement
	 */
	protected function get_svg_data( $file ) {
		$key = md5( $file );
		if ( ! isset( $this->svgs[ $key ] ) ) {
			$this->svgs[ $key ] = simplexml_load_file( $file );
		}

		return $this->svgs[ $key ];
	}
}