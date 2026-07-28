<?php
/**
 * Stream Handler — REST API endpoint with HTTP Range Request support.
 *
 * Registers a custom REST route that streams video files directly from the
 * WordPress Media Library. Supports HTTP 206 Partial Content responses for
 * proper browser video seeking and buffering.
 *
 * As of v2.0.0, the endpoint also auto-detects HLS-compressed videos and
 * can redirect to the HLS master playlist, or serve single-file streams
 * for backward compatibility.
 *
 * SECURITY / PERFORMANCE NOTES:
 *
 * - The endpoint calls VSB_Video_Helper::get_video_by_attachment_id() to
 *   validate every request through the same security boundary (path traversal,
 *   MIME type, existence checks).
 *
 * - set_time_limit(0) is called inside the streaming callback to prevent PHP
 *   timeout during large file transfers. The default PHP max_execution_time
 *   (often 30s) would kill the process mid-stream for files larger than a few
 *   MB at moderate bandwidth. This is safe because the callback only runs for
 *   authenticated REST requests and exits after streaming.
 *
 * - Files are read in configurable chunks (default 256 KB) to balance I/O
 *   overhead against memory usage. Smaller chunks = more fread() syscalls;
 *   larger chunks = more memory per request.
 *
 * - The ETag header (based on file size + mtime) enables conditional requests
 *   and improves cache efficiency.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Stream_Handler {

	/**
	 * Register the REST API route.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes() {
		register_rest_route(
			'video-stream/v1',
			'/play/(?P<attachment_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'stream_video' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'attachment_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return absint( $param ) > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'format' => array(
						'required'          => false,
						'default'           => 'auto',
						'enum'              => array( 'auto', 'hls', 'mp4' ),
						'sanitize_callback' => function ( $param ) {
							return in_array( $param, array( 'auto', 'hls', 'mp4' ), true ) ? $param : 'auto';
						},
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the streaming endpoint.
	 *
	 * By default, videos are publicly accessible. If the admin has enabled
	 * "restrict to logged-in users" in settings, only authenticated users can
	 * stream.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The current request.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public static function check_permission( $request ) {
		$restrict = get_option( 'vsb_restrict_logged_in', false );

		if ( $restrict && ! is_user_logged_in() ) {
			return new WP_Error(
				'vsb_restricted',
				__( 'You must be logged in to stream videos.', 'video-stream-buffer' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Stream the video file — with HLS auto-detection (v2.0.0).
	 *
	 * This is the main handler. It:
	 * 1. Validates the attachment via VSB_Video_Helper.
	 * 2. Checks for HLS-compressed versions and format preference.
	 * 3. If HLS is requested/available: redirects to the HLS master playlist.
	 * 4. Otherwise: serves the single file via HTTP Range Requests (legacy).
	 *
	 * @since  1.0.0
	 * @since  2.0.0 Added HLS auto-detection and `?format=` parameter.
	 *
	 * @param  WP_REST_Request $request The REST request.
	 * @return WP_Error|void WP_Error on failure, or exits on success.
	 */
	public static function stream_video( $request ) {
		$attachment_id = $request->get_param( 'attachment_id' );
		$format        = $request->get_param( 'format' );

		// Validate the video using the shared helper.
		$video = VSB_Video_Helper::get_video_by_attachment_id( $attachment_id );
		if ( is_wp_error( $video ) ) {
			return $video;
		}

		// -----------------------------------------------------------------
		// HLS auto-detection (v2.0.0).
		//
		// If the `format` parameter is 'hls', or if it's 'auto' and the
		// video has been compressed, redirect to the HLS master playlist.
		// Otherwise, serve the single file as before (backward compatible).
		// -----------------------------------------------------------------
		if ( class_exists( 'VSB_Compressor' ) ) {
			$has_hls  = VSB_Compressor::is_compressed( $attachment_id );
			$want_hls = ( 'hls' === $format ) || ( 'auto' === $format && $has_hls );

			if ( $want_hls && $has_hls ) {
				// Redirect to the HLS master playlist endpoint.
				$hls_url = rest_url( 'video-stream/v1/hls/' . absint( $attachment_id ) . '/master.m3u8' );
				wp_redirect( $hls_url, 302 );
				exit;
			}
		}

		// -----------------------------------------------------------------
		// Fall through: serve single file (backward compatible).
		// -----------------------------------------------------------------
		$file_path = $video['path'];
		$mime_type = $video['mime_type'];
		$file_size = $video['size'];

		// Open the file for binary reading.
		$file_handle = fopen( $file_path, 'rb' );
		if ( ! $file_handle ) {
			return new WP_Error(
				'vsb_fopen_failed',
				__( 'Could not open video file for streaming.', 'video-stream-buffer' ),
				array( 'status' => 500 )
			);
		}

		// Prevent PHP timeout during long transfers.
		set_time_limit( 0 );

		// Generate ETag from file metadata.
		$file_mtime = filemtime( $file_path );
		$etag       = sprintf( '"%x-%x"', $file_size, $file_mtime );

		// Read chunk size from settings (default: 256 KB).
		$chunk_size_kb = absint( get_option( 'vsb_chunk_size_kb', 256 ) );
		$chunk_size    = max( 8, min( $chunk_size_kb, 8192 ) ) * 1024; // Clamp 8KB–8MB.

		// Handle HTTP Range requests.
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$range_header = $_SERVER['HTTP_RANGE'];

			// Parse "bytes=X-Y" or "bytes=X-"
			if ( preg_match( '/bytes=(\d+)-(\d*)/', $range_header, $matches ) ) {
				$range_start = intval( $matches[1] );
				$range_end   = ( '' !== $matches[2] ) ? intval( $matches[2] ) : ( $file_size - 1 );

				// Sanity-check the range values.
				if ( $range_start > $range_end || $range_start >= $file_size ) {
					header( 'HTTP/1.1 416 Range Not Satisfiable' );
					header( 'Content-Range: bytes */' . $file_size );
					fclose( $file_handle );
					exit;
				}

				// Clamp end to file size.
				if ( $range_end >= $file_size ) {
					$range_end = $file_size - 1;
				}

				$content_length = $range_end - $range_start + 1;

				// Seek to the start of the requested range.
				fseek( $file_handle, $range_start );

				// Send 206 Partial Content headers.
				header( 'HTTP/1.1 206 Partial Content' );
				header( 'Content-Type: ' . $mime_type );
				header( 'Content-Length: ' . $content_length );
				header( 'Content-Range: bytes ' . $range_start . '-' . $range_end . '/' . $file_size );
				header( 'Accept-Ranges: bytes' );
				header( 'Cache-Control: public, max-age=86400' );
				header( 'ETag: ' . $etag );
				header( 'X-Content-Type-Options: nosniff' );

				// Stream the requested range in chunks.
				$bytes_remaining = $content_length;
				while ( $bytes_remaining > 0 && ! feof( $file_handle ) ) {
					$read_size = min( $chunk_size, $bytes_remaining );
					$buffer    = fread( $file_handle, $read_size );
					if ( false === $buffer ) {
						break;
					}
					echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — binary video data.
					$bytes_remaining -= strlen( $buffer );

					if ( ob_get_level() ) {
						ob_flush();
					}
					flush();
				}
			} else {
				// Malformed Range header — fall through to full file.
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: ' . $mime_type );
				header( 'Content-Length: ' . $file_size );
				header( 'Accept-Ranges: bytes' );
				header( 'Cache-Control: public, max-age=86400' );
				header( 'ETag: ' . $etag );
				header( 'X-Content-Type-Options: nosniff' );

				while ( ! feof( $file_handle ) ) {
					$buffer = fread( $file_handle, $chunk_size );
					if ( false === $buffer ) {
						break;
					}
					echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					if ( ob_get_level() ) {
						ob_flush();
					}
					flush();
				}
			}
		} else {
			// No Range header — serve full file (HTTP 200).
			header( 'HTTP/1.1 200 OK' );
			header( 'Content-Type: ' . $mime_type );
			header( 'Content-Length: ' . $file_size );
			header( 'Accept-Ranges: bytes' );
			header( 'Cache-Control: public, max-age=86400' );
			header( 'ETag: ' . $etag );
			header( 'X-Content-Type-Options: nosniff' );

			// Stream the entire file in chunks.
			while ( ! feof( $file_handle ) ) {
				$buffer = fread( $file_handle, $chunk_size );
				if ( false === $buffer ) {
					break;
				}
				echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( ob_get_level() ) {
					ob_flush();
				}
				flush();
			}
		}

		fclose( $file_handle );
		exit;
	}
}
