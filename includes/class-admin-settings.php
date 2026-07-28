<?php
/**
 * Admin Settings page.
 *
 * Provides a settings page under Settings > Video Stream Buffer with options
 * to restrict streaming to logged-in users, configure streaming chunk size,
 * and (as of v2.0.0) manage FFmpeg compression and HLS settings.
 *
 * Uses the WordPress Settings API for proper nonce handling, capability
 * checks, and data sanitization.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Admin_Settings {

	/**
	 * Option group name used by the Settings API.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_GROUP = 'vsb_settings_group';

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'vsb-settings';

	/**
	 * Add the settings submenu page.
	 *
	 * @since 1.0.0
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Video Stream Buffer Settings', 'video-stream-buffer' ),
			__( 'Video Stream Buffer', 'video-stream-buffer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Added compression settings section.
	 */
	public static function register_settings() {
		// --- Restrict to logged-in users ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_restrict_logged_in',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// --- Chunk size in KB ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_chunk_size_kb',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_chunk_size' ),
				'default'           => 256,
			)
		);

		// =====================================================================
		// Compression Settings (v2.0.0)
		// =====================================================================

		// --- FFmpeg Binary Path ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_ffmpeg_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_ffmpeg_path' ),
				'default'           => 'ffmpeg',
			)
		);

		// --- Enabled Resolutions (stored as array) ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_enabled_resolutions',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_resolutions' ),
				'default'           => array( '1080p', '720p', '480p', '360p' ),
			)
		);

		// --- HLS Segment Duration ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_hls_segment_duration',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_segment_duration' ),
				'default'           => 6,
			)
		);

		// --- Audio Bitrate ---
		register_setting(
			self::OPTION_GROUP,
			'vsb_audio_bitrate',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_audio_bitrate' ),
				'default'           => '128k',
			)
		);

		// --- Streaming Configuration section ---
		add_settings_section(
			'vsb_main_section',
			__( 'Streaming Configuration', 'video-stream-buffer' ),
			array( __CLASS__, 'render_section_description' ),
			self::PAGE_SLUG
		);

		// Field: Restrict to logged-in users.
		add_settings_field(
			'vsb_restrict_logged_in',
			__( 'Restrict Access', 'video-stream-buffer' ),
			array( __CLASS__, 'render_restrict_field' ),
			self::PAGE_SLUG,
			'vsb_main_section'
		);

		// Field: Chunk size.
		add_settings_field(
			'vsb_chunk_size_kb',
			__( 'Streaming Chunk Size (KB)', 'video-stream-buffer' ),
			array( __CLASS__, 'render_chunk_size_field' ),
			self::PAGE_SLUG,
			'vsb_main_section'
		);

		// --- Compression Settings section (v2.0.0) ---
		add_settings_section(
			'vsb_compression_section',
			__( 'Compression Settings', 'video-stream-buffer' ),
			array( __CLASS__, 'render_compression_section_description' ),
			self::PAGE_SLUG
		);

		// Field: FFmpeg path.
		add_settings_field(
			'vsb_ffmpeg_path',
			__( 'FFmpeg Binary Path', 'video-stream-buffer' ),
			array( __CLASS__, 'render_ffmpeg_path_field' ),
			self::PAGE_SLUG,
			'vsb_compression_section'
		);

		// Field: Enabled resolutions.
		add_settings_field(
			'vsb_enabled_resolutions',
			__( 'Enabled Resolutions', 'video-stream-buffer' ),
			array( __CLASS__, 'render_resolutions_field' ),
			self::PAGE_SLUG,
			'vsb_compression_section'
		);

		// Field: HLS segment duration.
		add_settings_field(
			'vsb_hls_segment_duration',
			__( 'HLS Segment Duration', 'video-stream-buffer' ),
			array( __CLASS__, 'render_segment_duration_field' ),
			self::PAGE_SLUG,
			'vsb_compression_section'
		);

		// Field: Audio bitrate.
		add_settings_field(
			'vsb_audio_bitrate',
			__( 'Audio Bitrate', 'video-stream-buffer' ),
			array( __CLASS__, 'render_audio_bitrate_field' ),
			self::PAGE_SLUG,
			'vsb_compression_section'
		);
	}

	/**
	 * Sanitize the FFmpeg path.
	 *
	 * Validates the binary exists and runs correctly on save.
	 *
	 * @since  2.0.0
	 * @param  mixed $value Raw input.
	 * @return string Sanitized path.
	 */
	public static function sanitize_ffmpeg_path( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( empty( $value ) ) {
			$value = 'ffmpeg';
		}

		// Validate the FFmpeg binary.
		if ( class_exists( 'VSB_Compressor' ) ) {
			$result = VSB_Compressor::detect_ffmpeg( $value );
			if ( is_wp_error( $result ) ) {
				add_settings_error(
					'vsb_ffmpeg_path',
					'vsb_ffmpeg_invalid',
					$result->get_error_message(),
					'error'
				);
			}
		}

		return $value;
	}

	/**
	 * Sanitize the resolutions array.
	 *
	 * @since  2.0.0
	 * @param  mixed $value Raw input.
	 * @return array Valid resolution labels.
	 */
	public static function sanitize_resolutions( $value ) {
		if ( ! is_array( $value ) ) {
			return array( '1080p', '720p', '480p', '360p' );
		}
		$valid   = array( '1080p', '720p', '480p', '360p' );
		$cleaned = array_values( array_intersect( $value, $valid ) );
		if ( empty( $cleaned ) ) {
			$cleaned = array( '720p', '480p' );
		}
		return $cleaned;
	}

	/**
	 * Sanitize the HLS segment duration.
	 *
	 * @since  2.0.0
	 * @param  mixed $value Raw input.
	 * @return int Clamped 2–10.
	 */
	public static function sanitize_segment_duration( $value ) {
		$value = absint( $value );
		return max( 2, min( 10, $value ) );
	}

	/**
	 * Sanitize the audio bitrate.
	 *
	 * @since  2.0.0
	 * @param  mixed $value Raw input.
	 * @return string Valid bitrate string like "128k".
	 */
	public static function sanitize_audio_bitrate( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( ! preg_match( '/^\d+k$/', $value ) ) {
			$value = '128k';
		}
		return $value;
	}

	/**
	 * Sanitize the chunk size value.
	 *
	 * Clamps the value between 8 KB and 8192 KB (8 MB) to prevent
	 * unreasonable configurations.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Raw input value.
	 * @return int          Sanitized chunk size in KB.
	 */
	public static function sanitize_chunk_size( $value ) {
		$value = absint( $value );
		return max( 8, min( $value, 8192 ) );
	}

	// =========================================================================
	// Section descriptions
	// =========================================================================

	/**
	 * Render the section description text.
	 *
	 * @since 1.0.0
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configure how Video Stream Buffer delivers video content to your visitors.', 'video-stream-buffer' ) . '</p>';
	}

	/**
	 * Render the compression section description.
	 *
	 * @since 2.0.0
	 */
	public static function render_compression_section_description() {
		echo '<p>' . esc_html__( 'Configure on-the-fly video compression and HLS adaptive bitrate streaming. Videos must be compressed individually through the Media Library after these settings are saved.', 'video-stream-buffer' ) . '</p>';

		// Show FFmpeg detection status.
		if ( class_exists( 'VSB_Compressor' ) ) {
			$result = VSB_Compressor::detect_ffmpeg();
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'FFmpeg not detected:', 'video-stream-buffer' ) . '</strong> ' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'FFmpeg detected:', 'video-stream-buffer' ) . '</strong> ' . esc_html( $result ) . '</p></div>';
			}
		}

		echo '<div class="notice notice-info inline" style="margin-top:8px;"><p>' . esc_html__( 'Compression is CPU-intensive and may take several minutes for large videos. Videos are processed asynchronously in the background.', 'video-stream-buffer' ) . '</p></div>';
	}

	// =========================================================================
	// Field renders
	// =========================================================================

	/**
	 * Render the "Restrict to logged-in users" checkbox field.
	 *
	 * @since 1.0.0
	 */
	public static function render_restrict_field() {
		$value = get_option( 'vsb_restrict_logged_in', false );
		printf(
			'<label>
				<input type="checkbox" name="%s" value="1" %s />
				%s
			</label>
			<p class="description">%s</p>',
			esc_attr( 'vsb_restrict_logged_in' ),
			checked( $value, true, false ),
			esc_html__( 'Only allow logged-in users to stream videos', 'video-stream-buffer' ),
			esc_html__( 'When enabled, the streaming endpoint returns a 401 error for unauthenticated visitors. Videos embedded via shortcode or widget will still display but will fail to load unless the visitor is logged in.', 'video-stream-buffer' )
		);
	}

	/**
	 * Render the "Chunk Size" number input field.
	 *
	 * @since 1.0.0
	 */
	public static function render_chunk_size_field() {
		$value = get_option( 'vsb_chunk_size_kb', 256 );
		printf(
			'<input type="number" name="%s" value="%s" min="8" max="8192" step="1" class="small-text" />
			<p class="description">%s</p>',
			esc_attr( 'vsb_chunk_size_kb' ),
			esc_attr( $value ),
			esc_html__( 'Size of each chunk read from disk during streaming (8–8192 KB). Default: 256 KB. Smaller values reduce memory usage but increase disk I/O. Larger values are faster for high-bandwidth connections.', 'video-stream-buffer' )
		);
	}

	/**
	 * Render the "FFmpeg Binary Path" text input.
	 *
	 * @since 2.0.0
	 */
	public static function render_ffmpeg_path_field() {
		$value = get_option( 'vsb_ffmpeg_path', 'ffmpeg' );
		printf(
			'<input type="text" name="%s" value="%s" class="regular-text" placeholder="ffmpeg" />
			<p class="description">%s</p>',
			esc_attr( 'vsb_ffmpeg_path' ),
			esc_attr( $value ),
			esc_html__( 'Path to the FFmpeg binary. A bare name (e.g., "ffmpeg") will be resolved via the system PATH. Use an absolute path if FFmpeg is installed in a custom location.', 'video-stream-buffer' )
		);
	}

	/**
	 * Render the "Enabled Resolutions" checkboxes.
	 *
	 * @since 2.0.0
	 */
	public static function render_resolutions_field() {
		$enabled = get_option( 'vsb_enabled_resolutions', array( '1080p', '720p', '480p', '360p' ) );
		if ( ! is_array( $enabled ) ) {
			$enabled = array( '1080p', '720p', '480p', '360p' );
		}

		$available = array( '1080p', '720p', '480p', '360p' );
		echo '<fieldset>';
		foreach ( $available as $res ) {
			printf(
				'<label style="margin-right:16px;">
					<input type="checkbox" name="%s[]" value="%s" %s />
					%s
				</label><br />',
				esc_attr( 'vsb_enabled_resolutions' ),
				esc_attr( $res ),
				checked( in_array( $res, $enabled, true ), true, false ),
				esc_html( $res )
			);
		}
		echo '<p class="description">' . esc_html__( 'Select which resolutions to generate during compression. At least one must be selected. 1080p requires significantly more processing time and disk space.', 'video-stream-buffer' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render the "HLS Segment Duration" number input.
	 *
	 * @since 2.0.0
	 */
	public static function render_segment_duration_field() {
		$value = get_option( 'vsb_hls_segment_duration', 6 );
		printf(
			'<input type="number" name="%s" value="%s" min="2" max="10" step="1" class="small-text" /> %s
			<p class="description">%s</p>',
			esc_attr( 'vsb_hls_segment_duration' ),
			esc_attr( $value ),
			esc_html__( 'seconds', 'video-stream-buffer' ),
			esc_html__( 'Duration of each HLS segment (2–10 seconds). Default: 6. Shorter segments allow faster quality switching but create more files.', 'video-stream-buffer' )
		);
	}

	/**
	 * Render the "Audio Bitrate" text input.
	 *
	 * @since 2.0.0
	 */
	public static function render_audio_bitrate_field() {
		$value = get_option( 'vsb_audio_bitrate', '128k' );
		printf(
			'<input type="text" name="%s" value="%s" class="small-text" placeholder="128k" />
			<p class="description">%s</p>',
			esc_attr( 'vsb_audio_bitrate' ),
			esc_attr( $value ),
			esc_html__( 'Audio bitrate for compressed streams (e.g., 96k, 128k, 192k). Default: 128k.', 'video-stream-buffer' )
		);
	}

	// =========================================================================
	// Page render
	// =========================================================================

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Added compression status table and bulk compress button.
	 */
	public static function render_page() {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'video-stream-buffer' ) );
		}

		// Handle bulk compress action.
		if ( isset( $_POST['vsb_bulk_compress'] ) && check_admin_referer( 'vsb_bulk_compress', 'vsb_bulk_compress_nonce' ) ) {
			self::handle_bulk_compress();
		}

		// Handle single compress action.
		if ( isset( $_GET['vsb_compress'] ) && isset( $_GET['_wpnonce'] ) ) {
			$attachment_id = absint( $_GET['vsb_compress'] );
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'vsb_compress_' . $attachment_id ) ) {
				self::handle_single_compress( $attachment_id );
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				// Output nonces and hidden fields for the registered option group.
				settings_fields( self::OPTION_GROUP );

				// Output the settings section and its fields.
				do_settings_sections( self::PAGE_SLUG );

				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Compression Status', 'video-stream-buffer' ); ?></h2>
			<p><?php esc_html_e( 'The table below shows all videos in your Media Library and their compression status. Click "Compress" to generate HLS adaptive bitrate streams for a video.', 'video-stream-buffer' ); ?></p>

			<?php self::render_compression_table(); ?>

			<hr />

			<h2><?php esc_html_e( 'How to Test Streaming', 'video-stream-buffer' ); ?></h2>
			<p><?php esc_html_e( 'Once you have uploaded a video to the Media Library, you can test the streaming endpoint with:', 'video-stream-buffer' ); ?></p>
			<code>curl -I "<?php echo esc_url( rest_url( 'video-stream/v1/play/123' ) ); ?>"</code>
			<p><?php esc_html_e( 'Replace 123 with your attachment ID. You should see a 200 OK response with Accept-Ranges: bytes header.', 'video-stream-buffer' ); ?></p>
			<p><?php esc_html_e( 'To test a Range request:', 'video-stream-buffer' ); ?></p>
			<code>curl -I -H "Range: bytes=0-1023" "<?php echo esc_url( rest_url( 'video-stream/v1/play/123' ) ); ?>"</code>
			<p><?php esc_html_e( 'This should return HTTP 206 Partial Content with Content-Range and Content-Length headers.', 'video-stream-buffer' ); ?></p>

			<h3><?php esc_html_e( 'HLS Streaming (v2.0.0)', 'video-stream-buffer' ); ?></h3>
			<p><?php esc_html_e( 'After compressing a video, the HLS master playlist is available at:', 'video-stream-buffer' ); ?></p>
			<code><?php echo esc_url( rest_url( 'video-stream/v1/hls/123/master.m3u8' ) ); ?></code>
			<p><?php esc_html_e( 'You can also force HLS mode via the streaming endpoint:', 'video-stream-buffer' ); ?></p>
			<code>curl "<?php echo esc_url( rest_url( 'video-stream/v1/play/123?format=hls' ) ); ?>"</code>
		</div>
		<?php
	}

	/**
	 * Render the compression status table listing all videos.
	 *
	 * @since 2.0.0
	 */
	private static function render_compression_table() {
		// Get all video attachments.
		$videos = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'video',
				'post_status'    => 'inherit',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $videos ) ) {
			echo '<p>' . esc_html__( 'No videos found in the Media Library. Upload a video to get started.', 'video-stream-buffer' ) . '</p>';
			return;
		}

		?>
		<form method="post" style="margin-bottom:12px;">
			<?php wp_nonce_field( 'vsb_bulk_compress', 'vsb_bulk_compress_nonce' ); ?>
			<input type="submit" name="vsb_bulk_compress" class="button button-secondary" value="<?php esc_attr_e( 'Bulk Compress All Uncompressed', 'video-stream-buffer' ); ?>" />
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Video', 'video-stream-buffer' ); ?></th>
					<th><?php esc_html_e( 'Format', 'video-stream-buffer' ); ?></th>
					<th><?php esc_html_e( 'Status', 'video-stream-buffer' ); ?></th>
					<th><?php esc_html_e( 'Resolutions', 'video-stream-buffer' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'video-stream-buffer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $videos as $video_post ) {
					$attachment_id = $video_post->ID;
					$status        = VSB_Compressor::get_compression_status( $attachment_id );
					$resolutions   = VSB_Compressor::get_available_resolutions( $attachment_id );
					$mime_type     = get_post_mime_type( $attachment_id );
					$title         = get_the_title( $attachment_id );

					$action_url   = add_query_arg(
						array(
							'page'        => self::PAGE_SLUG,
							'vsb_compress' => $attachment_id,
							'_wpnonce'    => wp_create_nonce( 'vsb_compress_' . $attachment_id ),
						),
						admin_url( 'options-general.php' )
					);

					$res_display = ! empty( $resolutions )
						? esc_html( implode( ', ', $resolutions ) )
						: '—';

					echo '<tr>';
					echo '<td><strong>' . esc_html( $title ) . '</strong><br><small>ID: ' . esc_html( $attachment_id ) . '</small></td>';
					echo '<td>' . esc_html( $mime_type ) . '</td>';
					echo '<td>' . VSB_Compressor::get_status_html( $attachment_id ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML from known method.
					echo '<td>' . $res_display . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<td>';

					if ( 'complete' === $status ) {
						echo '<a href="' . esc_url( $action_url ) . '" class="button button-small">' . esc_html__( 'Recompress', 'video-stream-buffer' ) . '</a> ';
					} elseif ( 'compressing' === $status || 'pending' === $status ) {
						echo '<span class="button button-small disabled">' . esc_html__( 'In Progress...', 'video-stream-buffer' ) . '</span> ';
					} else {
						echo '<a href="' . esc_url( $action_url ) . '" class="button button-small button-primary">' . esc_html__( 'Compress', 'video-stream-buffer' ) . '</a> ';
					}

					echo '</td>';
					echo '</tr>';
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handle bulk compress action.
	 *
	 * @since 2.0.0
	 */
	private static function handle_bulk_compress() {
		$videos = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'video',
				'post_status'    => 'inherit',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);

		$count = 0;
		foreach ( $videos as $attachment_id ) {
			if ( ! VSB_Compressor::is_compressed( $attachment_id ) ) {
				$result = VSB_Compressor::schedule_compression( $attachment_id );
				if ( ! is_wp_error( $result ) ) {
					$count++;
				}
			}
		}

		if ( $count > 0 ) {
			add_settings_error(
				'vsb_settings',
				'vsb_bulk_compress',
				sprintf(
					/* translators: %d: number of videos scheduled */
					_n(
						'%d video has been scheduled for compression. It will be processed in the background.',
						'%d videos have been scheduled for compression. They will be processed in the background.',
						$count,
						'video-stream-buffer'
					),
					$count
				),
				'success'
			);
		} else {
			add_settings_error(
				'vsb_settings',
				'vsb_bulk_compress',
				__( 'All videos are already compressed or no videos are available.', 'video-stream-buffer' ),
				'info'
			);
		}
	}

	/**
	 * Handle single compress action.
	 *
	 * @since 2.0.0
	 * @param int $attachment_id Attachment ID.
	 */
	private static function handle_single_compress( $attachment_id ) {
		$result = VSB_Compressor::schedule_compression( $attachment_id );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'vsb_settings',
				'vsb_compress',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'vsb_settings',
				'vsb_compress',
				__( 'Video has been scheduled for compression. It will be processed in the background.', 'video-stream-buffer' ),
				'success'
			);
		}
	}
}
