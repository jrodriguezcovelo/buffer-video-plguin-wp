<?php
/**
 * Video Helper — shared video retrieval and validation.
 *
 * Every component (REST endpoint, shortcode, Elementor widget) calls this class
 * for video validation. This enforces a single security boundary for path
 * traversal prevention, file existence checks, and MIME type validation.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Video_Helper {

	/**
	 * Allowed video MIME types for streaming.
	 *
	 * We restrict to these three common web video formats. MP4 (H.264) has the
	 * broadest browser support; WebM and Ogg are included as open alternatives.
	 *
	 * @since 1.0.0
	 * @return string[] Allowed MIME types.
	 */
	public static function get_allowed_mime_types() {
		return array(
			'video/mp4',
			'video/webm',
			'video/ogg',
		);
	}

	/**
	 * Validate and retrieve video information by attachment ID.
	 *
	 * SECURITY NOTES:
	 *
	 * 1. PATH TRAVERSAL PREVENTION: We use realpath() on the resolved file path
	 *    and the uploads directory, then verify the file's real path starts with
	 *    the uploads directory's real path. This prevents directory traversal
	 *    attacks (e.g. ../../wp-config.php) even if get_attached_file() returned
	 *    a manipulated path.
	 *
	 * 2. We use get_attached_file() from WordPress core, which resolves the
	 *    attachment metadata — never hardcode paths.
	 *
	 * 3. MIME type is validated against a strict allowlist of web video formats.
	 *
	 * 4. File existence is checked with file_exists() after path validation.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $attachment_id The WordPress attachment ID.
	 * @return array|WP_Error {
	 *     Array of video data on success, WP_Error on failure.
	 *
	 *     @type string $path       Absolute filesystem path to the video file.
	 *     @type string $url        Public URL of the media file.
	 *     @type string $mime_type  MIME type of the video.
	 *     @type bool   $exists     Whether the file exists on disk.
	 *     @type bool   $valid      Whether the file passed all validation checks.
	 *     @type int    $size       File size in bytes (only when valid).
	 * }
	 */
	public static function get_video_by_attachment_id( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return new WP_Error(
				'vsb_invalid_id',
				__( 'Invalid attachment ID.', 'video-stream-buffer' ),
				array( 'status' => 400 )
			);
		}

		// Retrieve the filesystem path using WordPress API.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! is_string( $file_path ) ) {
			return new WP_Error(
				'vsb_file_not_found',
				__( 'Video file not found in media library.', 'video-stream-buffer' ),
				array( 'status' => 404 )
			);
		}

		// Resolve real paths to prevent path traversal.
		$real_file_path = realpath( $file_path );

		// Get the uploads directory real path for the containment check.
		$upload_dir     = wp_upload_dir();
		$upload_basedir = realpath( $upload_dir['basedir'] );

		// PATH TRAVERSAL DEFENSE: Ensure the resolved file path is within the
		// uploads directory. realpath() returns false if the file doesn't exist
		// or the path is invalid, so this also catches nonexistent files.
		if ( false === $real_file_path || false === $upload_basedir ) {
			return new WP_Error(
				'vsb_path_error',
				__( 'Could not resolve file path.', 'video-stream-buffer' ),
				array( 'status' => 500 )
			);
		}

		// Verify the file lives inside the uploads directory.
		if ( 0 !== strpos( $real_file_path, $upload_basedir . DIRECTORY_SEPARATOR )
			&& $real_file_path !== $upload_basedir
		) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[Video Stream Buffer] Path traversal attempt blocked for attachment %d. File: %s',
						$attachment_id,
						esc_html( $file_path )
					)
				);
			}
			return new WP_Error(
				'vsb_path_traversal',
				__( 'Invalid file location.', 'video-stream-buffer' ),
				array( 'status' => 403 )
			);
		}

		// Check file existence.
		if ( ! file_exists( $real_file_path ) ) {
			return new WP_Error(
				'vsb_file_missing',
				__( 'Video file does not exist on disk.', 'video-stream-buffer' ),
				array( 'status' => 404 )
			);
		}

		// Validate MIME type.
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime_type, self::get_allowed_mime_types(), true ) ) {
			return new WP_Error(
				'vsb_invalid_mime',
				sprintf(
					/* translators: %s: detected MIME type */
					__( 'Unsupported video format: %s', 'video-stream-buffer' ),
					$mime_type
				),
				array( 'status' => 415 )
			);
		}

		$file_size = filesize( $real_file_path );
		$file_url  = wp_get_attachment_url( $attachment_id );

		// Return success array — used by all consumers.
		return array(
			'path'      => $real_file_path,
			'url'       => $file_url,
			'mime_type' => $mime_type,
			'exists'    => true,
			'valid'     => true,
			'size'      => $file_size,
		);
	}
}
