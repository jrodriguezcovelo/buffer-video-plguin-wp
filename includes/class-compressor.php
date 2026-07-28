<?php
/**
 * Compressor — FFmpeg HLS adaptive bitrate compression engine.
 *
 * Handles FFmpeg detection, asynchronous video compression, HLS segment
 * generation, master playlist creation, and compressed file management.
 *
 * Compression is triggered on-demand for existing Media Library videos via
 * the admin UI (Media Library action + settings bulk compress). A WP Cron
 * one-shot event runs the actual FFmpeg process in the background so the
 * admin doesn't block waiting for it.
 *
 * @package VideoStreamBuffer
 * @since   2.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Compressor {

	/**
	 * Cron hook name for async compression.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const CRON_HOOK = 'vsb_compress_video';

	/**
	 * Base directory for compressed HLS output, relative to uploads basedir.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const OUTPUT_DIR = 'vsb-compressed';

	/**
	 * Supported resolution presets with labels and FFmpeg scale params.
	 *
	 * Bandwidth values are rough estimates used in the master playlist
	 * #EXT-X-STREAM-INF BANDWIDTH field. They include video + audio.
	 *
	 * @since 2.0.0
	 * @return array<string, array>
	 */
	public static function get_resolution_presets() {
		return array(
			'1080p' => array(
				'label'      => '1080p',
				'width'      => 1920,
				'height'     => 1080,
				'bandwidth'  => 5500000,
				'video_bitrate' => '5000k',
				'maxrate'       => '5350k',
				'bufsize'       => '7500k',
			),
			'720p'  => array(
				'label'      => '720p',
				'width'      => 1280,
				'height'     => 720,
				'bandwidth'  => 3000000,
				'video_bitrate' => '2800k',
				'maxrate'       => '2996k',
				'bufsize'       => '4200k',
			),
			'480p'  => array(
				'label'      => '480p',
				'width'      => 854,
				'height'     => 480,
				'bandwidth'  => 1600000,
				'video_bitrate' => '1400k',
				'maxrate'       => '1498k',
				'bufsize'       => '2100k',
			),
			'360p'  => array(
				'label'      => '360p',
				'width'      => 640,
				'height'     => 360,
				'bandwidth'  => 900000,
				'video_bitrate' => '800k',
				'maxrate'       => '856k',
				'bufsize'       => '1200k',
			),
		);
	}

	/**
	 * Get the base output directory for compressed files.
	 *
	 * Creates the directory if it doesn't exist and adds an index.php
	 * and .htaccess for directory listing prevention.
	 *
	 * @since  2.0.0
	 * @return string|WP_Error Absolute path or error.
	 */
	public static function get_output_base_dir() {
		$upload_dir = wp_upload_dir();
		$base       = trailingslashit( $upload_dir['basedir'] ) . self::OUTPUT_DIR;

		if ( ! is_dir( $base ) ) {
			if ( ! wp_mkdir_p( $base ) ) {
				return new WP_Error(
					'vsb_dir_create_failed',
					__( 'Could not create compressed video output directory. Please check uploads directory permissions.', 'video-stream-buffer' )
				);
			}
			// Prevent directory listing.
			file_put_contents( $base . '/index.php', '<?php // Silence is golden.' );
			file_put_contents( $base . '/.htaccess', 'Options -Indexes' );
		}

		return $base;
	}

	/**
	 * Get the output directory for a specific attachment.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return string|WP_Error Absolute path or error.
	 */
	public static function get_attachment_output_dir( $attachment_id ) {
		$base = self::get_output_base_dir();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$dir = trailingslashit( $base ) . absint( $attachment_id );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'vsb_attachment_dir_failed',
					__( 'Could not create output directory for this video.', 'video-stream-buffer' )
				);
			}
		}

		return $dir;
	}

	/**
	 * Get the public URL for an attachment's compressed output.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return string|false URL or false on failure.
	 */
	public static function get_output_base_url() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . self::OUTPUT_DIR;
	}

	/**
	 * Detect FFmpeg and validate it runs.
	 *
	 * @since  2.0.0
	 * @param  string|null $binary_path Optional custom path to check.
	 * @return string|WP_Error Path to valid FFmpeg binary, or error.
	 */
	public static function detect_ffmpeg( $binary_path = null ) {
		$path = ( null !== $binary_path ) ? $binary_path : get_option( 'vsb_ffmpeg_path', 'ffmpeg' );

		// Basic sanitization: only allow alphanumeric, slashes, dashes, dots, underscores.
		if ( preg_match( '/[^a-zA-Z0-9\/\.\-_]/', $path ) ) {
			return new WP_Error(
				'vsb_ffmpeg_invalid_path',
				__( 'FFmpeg binary path contains invalid characters.', 'video-stream-buffer' )
			);
		}

		// Use `command -v` for PATH lookup, or direct check for absolute paths.
		if ( false === strpos( $path, '/' ) ) {
			// Bare command name — use `command -v` to resolve.
			$resolved = trim( shell_exec( 'command -v ' . escapeshellarg( $path ) . ' 2>/dev/null' ) );
			if ( empty( $resolved ) ) {
				return new WP_Error(
					'vsb_ffmpeg_not_found',
					sprintf(
						/* translators: %s: the configured FFmpeg path */
						__( 'FFmpeg not found at "%s". Please install FFmpeg or update the path in settings.', 'video-stream-buffer' ),
						esc_html( $path )
					)
				);
			}
			$path = $resolved;
		} else {
			// Absolute or relative path — check directly.
			if ( ! file_exists( $path ) || ! is_executable( $path ) ) {
				return new WP_Error(
					'vsb_ffmpeg_not_found',
					sprintf(
						/* translators: %s: the configured FFmpeg path */
						__( 'FFmpeg not found at "%s". Please install FFmpeg or update the path in settings.', 'video-stream-buffer' ),
						esc_html( $path )
					)
				);
			}
		}

		// Validate by running ffmpeg -version.
		$version_output = shell_exec( escapeshellcmd( $path ) . ' -version 2>&1' );
		if ( empty( $version_output ) || false === strpos( $version_output, 'ffmpeg version' ) ) {
			return new WP_Error(
				'vsb_ffmpeg_invalid',
				sprintf(
					/* translators: %s: the resolved FFmpeg path */
					__( 'The binary at "%s" does not appear to be FFmpeg.', 'video-stream-buffer' ),
					esc_html( $path )
				)
			);
		}

		return $path;
	}

	/**
	 * Get the resolutions enabled in settings.
	 *
	 * @since  2.0.0
	 * @return array Enabled resolution labels (e.g., ['1080p', '720p', '480p', '360p']).
	 */
	public static function get_enabled_resolutions() {
		$all        = self::get_resolution_presets();
		$enabled    = get_option( 'vsb_enabled_resolutions', array_keys( $all ) );
		$enabled    = is_array( $enabled ) ? $enabled : array_keys( $all );
		$valid_keys = array_keys( $all );

		return array_values( array_intersect( $enabled, $valid_keys ) );
	}

	/**
	 * Get the configured audio bitrate.
	 *
	 * @since  2.0.0
	 * @return string Audio bitrate string (e.g., "128k").
	 */
	public static function get_audio_bitrate() {
		$bitrate = get_option( 'vsb_audio_bitrate', '128k' );
		// Validate format.
		if ( ! preg_match( '/^\d+k$/', $bitrate ) ) {
			$bitrate = '128k';
		}
		return $bitrate;
	}

	/**
	 * Get the configured HLS segment duration.
	 *
	 * @since  2.0.0
	 * @return int Segment duration in seconds (clamped 2–10).
	 */
	public static function get_segment_duration() {
		$duration = absint( get_option( 'vsb_hls_segment_duration', 6 ) );
		return max( 2, min( 10, $duration ) );
	}

	/**
	 * Check if a video has been compressed.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_compressed( $attachment_id ) {
		$compressed = get_post_meta( absint( $attachment_id ), '_vsb_compressed', true );
		return ( '1' === $compressed || true === $compressed );
	}

	/**
	 * Get the compression status for an attachment.
	 *
	 * Returns 'none', 'pending', 'compressing', 'complete', or 'error'.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_compression_status( $attachment_id ) {
		$status = get_post_meta( absint( $attachment_id ), '_vsb_compression_status', true );
		if ( empty( $status ) ) {
			return 'none';
		}
		return $status;
	}

	/**
	 * Get available resolutions for a compressed video.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return array Resolution labels or empty array.
	 */
	public static function get_available_resolutions( $attachment_id ) {
		$resolutions = get_post_meta( absint( $attachment_id ), '_vsb_resolutions', true );
		if ( ! is_array( $resolutions ) ) {
			return array();
		}
		return $resolutions;
	}

	/**
	 * Get the HLS master playlist URL for a compressed video.
	 *
	 * Uses the REST API endpoint so HLS manifests are served through the
	 * proper security boundary.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return string|false Master playlist URL or false if not compressed.
	 */
	public static function get_hls_url( $attachment_id ) {
		if ( ! self::is_compressed( $attachment_id ) ) {
			return false;
		}
		return rest_url( 'video-stream/v1/hls/' . absint( $attachment_id ) . '/master.m3u8' );
	}

	/**
	 * Schedule async compression for a video.
	 *
	 * This schedules a single WP Cron event. When fired, the cron callback
	 * runs the actual FFmpeg compression. The attachment meta is set to
	 * 'pending' immediately so the UI updates.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return bool|WP_Error True if scheduled, WP_Error on failure.
	 */
	public static function schedule_compression( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		// Validate attachment exists and is video.
		$video = VSB_Video_Helper::get_video_by_attachment_id( $attachment_id );
		if ( is_wp_error( $video ) ) {
			return $video;
		}

		// Check FFmpeg is available.
		$ffmpeg = self::detect_ffmpeg();
		if ( is_wp_error( $ffmpeg ) ) {
			return $ffmpeg;
		}

		// Set status to pending.
		update_post_meta( $attachment_id, '_vsb_compression_status', 'pending' );
		delete_post_meta( $attachment_id, '_vsb_compression_error' );

		// Schedule the cron event.
		$scheduled = wp_schedule_single_event(
			time() + 5, // Run in 5 seconds so the admin request finishes first.
			self::CRON_HOOK,
			array( $attachment_id )
		);

		if ( false === $scheduled ) {
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', __( 'Could not schedule compression task.', 'video-stream-buffer' ) );
			return new WP_Error(
				'vsb_schedule_failed',
				__( 'Could not schedule compression task.', 'video-stream-buffer' )
			);
		}

		return true;
	}

	/**
	 * Run FFmpeg compression for a video.
	 *
	 * This is the actual compression callback, triggered by WP Cron.
	 * It builds and executes the FFmpeg command, then updates attachment
	 * meta with the result status.
	 *
	 * @since 2.0.0
	 * @param int $attachment_id Attachment ID.
	 */
	public static function run_compression( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		// Mark as compressing.
		update_post_meta( $attachment_id, '_vsb_compression_status', 'compressing' );

		// Validate the video.
		$video = VSB_Video_Helper::get_video_by_attachment_id( $attachment_id );
		if ( is_wp_error( $video ) ) {
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', $video->get_error_message() );
			return;
		}

		// Detect FFmpeg.
		$ffmpeg = self::detect_ffmpeg();
		if ( is_wp_error( $ffmpeg ) ) {
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', $ffmpeg->get_error_message() );
			return;
		}

		// Get output directory.
		$output_dir = self::get_attachment_output_dir( $attachment_id );
		if ( is_wp_error( $output_dir ) ) {
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', $output_dir->get_error_message() );
			return;
		}

		// Get enabled resolutions, sorted from highest to lowest quality.
		$all_resolutions    = self::get_resolution_presets();
		$enabled_labels     = self::get_enabled_resolutions();
		$enabled_resolutions = array();
		foreach ( $all_resolutions as $label => $preset ) {
			if ( in_array( $label, $enabled_labels, true ) ) {
				$enabled_resolutions[ $label ] = $preset;
			}
		}

		if ( empty( $enabled_resolutions ) ) {
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', __( 'No resolutions enabled in settings.', 'video-stream-buffer' ) );
			return;
		}

		$labels         = array_keys( $enabled_resolutions );
		$count          = count( $labels );
		$segment_time   = self::get_segment_duration();
		$audio_bitrate  = self::get_audio_bitrate();
		$source_path    = $video['path'];

		// -----------------------------------------------------------------
		// Build the FFmpeg filter_complex string.
		//
		// We split the video stream N ways, scale each to the target
		// resolution, and use force_original_aspect_ratio=decrease to
		// avoid stretching non-16:9 content.
		// -----------------------------------------------------------------
		$filter_parts = array();
		$filter_parts[] = "[0:v]split={$count}" . implode( '', array_map( function( $i ) { return "[v{$i}]"; }, range( 0, $count - 1 ) ) );

		$resolution_map       = array();
		$var_stream_map_parts = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$label  = $labels[ $i ];
			$preset = $enabled_resolutions[ $label ];
			$w      = $preset['width'];
			$h      = $preset['height'];
			$filter_parts[] = "[v{$i}]scale=w={$w}:h={$h}:force_original_aspect_ratio=decrease[vout{$i}]";
			$resolution_map[] = $label;
			$var_stream_map_parts[] = "v:{$i},a:{$i},name:{$label}";
		}

		$filter_complex = implode( ';', $filter_parts );
		$var_stream_map = implode( ' ', $var_stream_map_parts );

		// -----------------------------------------------------------------
		// Build map, codec, and bitrate arguments.
		//
		// We map each video output from the filter and its corresponding
		// audio stream. Each variant gets its own bitrate.
		// -----------------------------------------------------------------
		$map_args  = array();
		$codec_args = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$preset = $enabled_resolutions[ $labels[ $i ] ];
			$map_args[] = "-map \"[vout{$i}]\"";
			$map_args[] = "-c:v:{$i} libx264";
			$map_args[] = "-b:v:{$i} " . escapeshellarg( $preset['video_bitrate'] );
			$map_args[] = "-maxrate:v:{$i} " . escapeshellarg( $preset['maxrate'] );
			$map_args[] = "-bufsize:v:{$i} " . escapeshellarg( $preset['bufsize'] );
			$map_args[] = "-map a:0";
			$map_args[] = "-c:a:{$i} aac";
			$map_args[] = "-b:a:{$i} " . escapeshellarg( $audio_bitrate );
			$map_args[] = "-ac:a:{$i} 2";
		}

		// -----------------------------------------------------------------
		// Assemble and execute the command.
		// -----------------------------------------------------------------
		$segment_pattern = escapeshellarg( $output_dir . '/vsb-%v-%03d.ts' );
		$playlist_pattern = escapeshellarg( $output_dir . '/vsb-%v.m3u8' );
		$master_name     = escapeshellarg( 'master.m3u8' );

		$cmd = escapeshellcmd( $ffmpeg ) . ' -y'
			. ' -i ' . escapeshellarg( $source_path )
			. ' -filter_complex ' . escapeshellarg( $filter_complex )
			. ' ' . implode( ' ', $map_args )
			. ' -f hls'
			. ' -hls_time ' . intval( $segment_time )
			. ' -hls_list_size 0'
			. ' -hls_segment_filename ' . $segment_pattern
			. ' -var_stream_map ' . escapeshellarg( $var_stream_map )
			. ' -master_pl_name ' . $master_name
			. ' ' . $playlist_pattern
			. ' 2>&1';

		// Increase time limit — FFmpeg can take a while.
		set_time_limit( 0 );

		$output    = array();
		$exit_code = 0;
		exec( $cmd, $output, $exit_code );

		// Check results.
		$master_playlist = $output_dir . '/master.m3u8';
		if ( 0 !== $exit_code || ! file_exists( $master_playlist ) ) {
			$error_message = __( 'FFmpeg compression failed.', 'video-stream-buffer' );
			if ( ! empty( $output ) ) {
				// Capture the last few lines for debugging.
				$error_message .= ' ' . implode( ' ', array_slice( $output, -5 ) );
			}
			update_post_meta( $attachment_id, '_vsb_compression_status', 'error' );
			update_post_meta( $attachment_id, '_vsb_compression_error', $error_message );
			return;
		}

		// -----------------------------------------------------------------
		// Generate master playlist with bandwidth info.
		//
		// FFmpeg's auto-generated master playlist may not include bandwidth
		// values perfectly. We regenerate it for accuracy.
		// -----------------------------------------------------------------
		self::generate_master_playlist( $attachment_id, $output_dir, $enabled_resolutions, $labels );

		// Mark as complete.
		update_post_meta( $attachment_id, '_vsb_compressed', '1' );
		update_post_meta( $attachment_id, '_vsb_compression_status', 'complete' );
		update_post_meta( $attachment_id, '_vsb_resolutions', $labels );
		update_post_meta( $attachment_id, '_vsb_hls_dir', $output_dir );
		delete_post_meta( $attachment_id, '_vsb_compression_error' );
	}

	/**
	 * Generate a master playlist with correct bandwidth values.
	 *
	 * FFmpeg's auto-generated master playlist bandwidth values are estimates
	 * and may not match our configured bitrates exactly. We regenerate it
	 * with our known bandwidth values.
	 *
	 * @since 2.0.0
	 * @param int   $attachment_id  Attachment ID.
	 * @param string $output_dir    Output directory path.
	 * @param array $resolutions    Resolution presets indexed by label.
	 * @param array $label_order    Ordered list of labels.
	 */
	private static function generate_master_playlist( $attachment_id, $output_dir, $resolutions, $label_order ) {
		$lines   = array();
		$lines[] = '#EXTM3U';
		$lines[] = '#EXT-X-VERSION:3';

		foreach ( $label_order as $label ) {
			if ( ! isset( $resolutions[ $label ] ) ) {
				continue;
			}
			$preset        = $resolutions[ $label ];
			$playlist_file = 'vsb-' . $label . '.m3u8';

			// Only include if the playlist file exists.
			if ( ! file_exists( $output_dir . '/' . $playlist_file ) ) {
				continue;
			}

			$lines[] = sprintf(
				'#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%dx%d,NAME="%s"',
				$preset['bandwidth'],
				$preset['width'],
				$preset['height'],
				$label
			);
			$lines[] = $playlist_file;
		}

		file_put_contents( $output_dir . '/master.m3u8', implode( "\n", $lines ) . "\n" );
	}

	/**
	 * Clean up compressed files for an attachment.
	 *
	 * @since 2.0.0
	 * @param int $attachment_id Attachment ID.
	 */
	public static function cleanup( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		// Get the output directory from meta or construct it.
		$output_dir = get_post_meta( $attachment_id, '_vsb_hls_dir', true );
		if ( empty( $output_dir ) ) {
			$base       = self::get_output_base_dir();
			if ( is_wp_error( $base ) ) {
				return;
			}
			$output_dir = trailingslashit( $base ) . $attachment_id;
		}

		if ( is_dir( $output_dir ) ) {
			// Recursively delete the directory.
			self::rmdir_recursive( $output_dir );
		}

		// Clean up attachment meta.
		delete_post_meta( $attachment_id, '_vsb_compressed' );
		delete_post_meta( $attachment_id, '_vsb_compression_status' );
		delete_post_meta( $attachment_id, '_vsb_compression_error' );
		delete_post_meta( $attachment_id, '_vsb_resolutions' );
		delete_post_meta( $attachment_id, '_vsb_hls_dir' );
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @since 2.0.0
	 * @param string $dir Directory path.
	 */
	private static function rmdir_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::rmdir_recursive( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Get the compression status HTML indicator for the admin.
	 *
	 * @since  2.0.0
	 * @param  int $attachment_id Attachment ID.
	 * @return string HTML status badge.
	 */
	public static function get_status_html( $attachment_id ) {
		$status = self::get_compression_status( $attachment_id );

		switch ( $status ) {
			case 'complete':
				return '<span class="vsb-status vsb-status-complete" style="color:green;">&#10003; ' . esc_html__( 'Compressed', 'video-stream-buffer' ) . '</span>';
			case 'compressing':
				return '<span class="vsb-status vsb-status-compressing" style="color:orange;">&#8635; ' . esc_html__( 'Compressing...', 'video-stream-buffer' ) . '</span>';
			case 'pending':
				return '<span class="vsb-status vsb-status-pending" style="color:gray;">&#8987; ' . esc_html__( 'Pending', 'video-stream-buffer' ) . '</span>';
			case 'error':
				$error = get_post_meta( $attachment_id, '_vsb_compression_error', true );
				return '<span class="vsb-status vsb-status-error" style="color:red;" title="' . esc_attr( $error ) . '">&#10007; ' . esc_html__( 'Error', 'video-stream-buffer' ) . '</span>';
			default:
				return '<span class="vsb-status vsb-status-none" style="color:gray;">—</span>';
		}
	}
}
