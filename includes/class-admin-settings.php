<?php
/**
 * Admin Settings page.
 *
 * Provides a settings page under Settings > Video Stream Buffer with options
 * to restrict streaming to logged-in users and configure the streaming chunk
 * size.
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

		// --- Settings section ---
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

	/**
	 * Render the section description text.
	 *
	 * @since 1.0.0
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configure how Video Stream Buffer delivers video content to your visitors.', 'video-stream-buffer' ) . '</p>';
	}

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
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page() {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'video-stream-buffer' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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

			<h2><?php esc_html_e( 'How to Test Streaming', 'video-stream-buffer' ); ?></h2>
			<p><?php esc_html_e( 'Once you have uploaded a video to the Media Library, you can test the streaming endpoint with:', 'video-stream-buffer' ); ?></p>
			<code>curl -I "<?php echo esc_url( rest_url( 'video-stream/v1/play/123' ) ); ?>"</code>
			<p><?php esc_html_e( 'Replace 123 with your attachment ID. You should see a 200 OK response with Accept-Ranges: bytes header.', 'video-stream-buffer' ); ?></p>
			<p><?php esc_html_e( 'To test a Range request:', 'video-stream-buffer' ); ?></p>
			<code>curl -I -H "Range: bytes=0-1023" "<?php echo esc_url( rest_url( 'video-stream/v1/play/123' ) ); ?>"</code>
			<p><?php esc_html_e( 'This should return HTTP 206 Partial Content with Content-Range and Content-Length headers.', 'video-stream-buffer' ); ?></p>
		</div>
		<?php
	}
}
