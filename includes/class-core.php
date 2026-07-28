<?php
/**
 * Core plugin bootstrap.
 *
 * Hooks all components into WordPress lifecycle events. This is the central
 * orchestrator — every subsystem (REST, shortcode, admin, Elementor widget,
 * HLS handler, compressor cron) is registered from here.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Core {

	/**
	 * Initialize the plugin.
	 *
	 * Hooks into WordPress lifecycle actions to register all components.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Added HLS handler, compressor cron, AJAX handlers.
	 */
	public static function init() {
		// Register the shortcode early (init).
		add_action( 'init', array( 'VSB_Shortcode', 'register' ) );

		// Register REST API routes (streaming + HLS).
		add_action( 'rest_api_init', array( 'VSB_Stream_Handler', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'VSB_HLS_Handler', 'register_routes' ) );

		// Register frontend assets (enqueued only when shortcode/widget is present).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		// Admin settings page.
		add_action( 'admin_menu', array( 'VSB_Admin_Settings', 'add_settings_page' ) );
		add_action( 'admin_init', array( 'VSB_Admin_Settings', 'register_settings' ) );

		// Settings link on the Plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( VSB_PLUGIN_DIR . 'video-stream-buffer.php' ),
			array( __CLASS__, 'add_plugin_action_links' )
		);

		// Elementor widget registration — hooks registered unconditionally.
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_elementor_category' ) );

		// -----------------------------------------------------------------
		// v2.0.0: Compression cron hook and AJAX handlers.
		// -----------------------------------------------------------------

		// Cron hook for async compression.
		add_action( VSB_Compressor::CRON_HOOK, array( 'VSB_Compressor', 'run_compression' ) );

		// AJAX handler for Media Library compress action.
		add_action( 'wp_ajax_vsb_compress_video', array( __CLASS__, 'ajax_compress_video' ) );

		// Media Library: add compress action to attachment rows.
		add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_action' ), 10, 2 );

		// Media Library: add compress button in attachment details sidebar.
		add_action( 'attachment_submitbox_misc_actions', array( __CLASS__, 'add_attachment_details_button' ) );

		// Admin AJAX: check compression status.
		add_action( 'wp_ajax_vsb_check_status', array( __CLASS__, 'ajax_check_status' ) );

		// Admin notices.
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 * Register the Video Stream Buffer widget with Elementor.
	 *
	 * @since 1.0.3
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_elementor_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once VSB_PLUGIN_DIR . 'includes/elementor/class-widget-video-stream.php';

		if ( class_exists( 'VSB_Widget_Video_Stream' ) ) {
			$widgets_manager->register( new \VSB_Widget_Video_Stream() );
		}
	}

	/**
	 * Register the Video Stream Buffer category with Elementor.
	 *
	 * @since 1.0.3
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_elementor_category( $elements_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once VSB_PLUGIN_DIR . 'includes/elementor/class-widget-video-stream.php';

		if ( class_exists( 'VSB_Widget_Video_Stream' ) ) {
			\VSB_Widget_Video_Stream::register_category( $elements_manager );
		}
	}

	/**
	 * Register frontend assets so they can be enqueued on demand by the
	 * shortcode or widget.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Added hls.js registration.
	 */
	public static function register_assets() {
		wp_register_style(
			'vsb-video-stream',
			VSB_PLUGIN_URL . 'assets/css/video-stream.css',
			array(),
			VSB_VERSION
		);

		wp_register_script(
			'vsb-video-stream',
			VSB_PLUGIN_URL . 'assets/js/video-stream.js',
			array(),
			VSB_VERSION,
			true
		);

		// hls.js — registered but only enqueued when HLS is available.
		wp_register_script(
			'vsb-hls-js',
			VSB_PLUGIN_URL . 'assets/js/hls.min.js',
			array(),
			VSB_VERSION,
			true
		);
	}

	/**
	 * Add a "Settings" link on the Plugins page.
	 *
	 * @since  1.0.0
	 * @param  string[] $links Existing plugin action links.
	 * @return string[] Modified links.
	 */
	public static function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'options-general.php?page=vsb-settings' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'video-stream-buffer' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	// -------------------------------------------------------------------------
	// Media Library integration (v2.0.0)
	// -------------------------------------------------------------------------

	/**
	 * Add "Compress for Streaming" link to Media Library row actions.
	 *
	 * @since  2.0.0
	 * @param  string[] $actions Existing actions.
	 * @param  WP_Post  $post    Attachment post object.
	 * @return string[] Modified actions.
	 */
	public static function add_media_row_action( $actions, $post ) {
		// Only show for video attachments.
		if ( ! wp_attachment_is( 'video', $post ) ) {
			return $actions;
		}

		// Check capability.
		if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$attachment_id = $post->ID;
		$status        = VSB_Compressor::get_compression_status( $attachment_id );

		$nonce  = wp_create_nonce( 'vsb_compress_' . $attachment_id );
		$url    = admin_url( 'admin-ajax.php?action=vsb_compress_video&attachment_id=' . $attachment_id . '&_wpnonce=' . $nonce );

		if ( 'complete' === $status ) {
			$actions['vsb_compress'] = sprintf(
				'<a href="%s" class="vsb-compress-action">%s</a>',
				esc_url( $url ),
				esc_html__( 'Recompress for Streaming', 'video-stream-buffer' )
			);
		} elseif ( 'compressing' === $status || 'pending' === $status ) {
			$actions['vsb_compress'] = sprintf(
				'<span style="color:orange;">%s</span>',
				esc_html__( 'Compressing...', 'video-stream-buffer' )
			);
		} else {
			$actions['vsb_compress'] = sprintf(
				'<a href="%s" class="vsb-compress-action">%s</a>',
				esc_url( $url ),
				esc_html__( 'Compress for Streaming', 'video-stream-buffer' )
			);
		}

		return $actions;
	}

	/**
	 * Add compression status and button to the attachment details sidebar.
	 *
	 * @since 2.0.0
	 */
	public static function add_attachment_details_button() {
		global $post;

		if ( ! $post || ! wp_attachment_is( 'video', $post ) ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$attachment_id = $post->ID;
		$status        = VSB_Compressor::get_compression_status( $attachment_id );

		echo '<div class="misc-pub-section vsb-compression-status">';
		echo '<strong>' . esc_html__( 'Video Stream Buffer', 'video-stream-buffer' ) . '</strong><br>';

		echo VSB_Compressor::get_status_html( $attachment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 'complete' !== $status && 'compressing' !== $status && 'pending' !== $status ) {
			$nonce = wp_create_nonce( 'vsb_compress_' . $attachment_id );
			$url   = admin_url( 'admin-ajax.php?action=vsb_compress_video&attachment_id=' . $attachment_id . '&_wpnonce=' . $nonce );
			echo '<br><a href="' . esc_url( $url ) . '" class="button button-small" style="margin-top:4px;">' . esc_html__( 'Compress for Streaming', 'video-stream-buffer' ) . '</a>';
		} elseif ( 'complete' === $status ) {
			$resolutions = VSB_Compressor::get_available_resolutions( $attachment_id );
			if ( ! empty( $resolutions ) ) {
				echo '<br><small>' . esc_html( implode( ', ', $resolutions ) ) . '</small>';
			}
			$nonce = wp_create_nonce( 'vsb_compress_' . $attachment_id );
			$url   = admin_url( 'admin-ajax.php?action=vsb_compress_video&attachment_id=' . $attachment_id . '&_wpnonce=' . $nonce );
			echo '<br><a href="' . esc_url( $url ) . '" class="button button-small" style="margin-top:4px;">' . esc_html__( 'Recompress', 'video-stream-buffer' ) . '</a>';
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers (v2.0.0)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: schedule compression for a video.
	 *
	 * Triggered from the Media Library "Compress for Streaming" link.
	 *
	 * @since 2.0.0
	 */
	public static function ajax_compress_video() {
		// Capability check.
		if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'video-stream-buffer' ), '', array( 'response' => 403 ) );
		}

		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;

		// Nonce check.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'vsb_compress_' . $attachment_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'video-stream-buffer' ), '', array( 'response' => 403 ) );
		}

		if ( ! $attachment_id ) {
			wp_die( esc_html__( 'Invalid attachment ID.', 'video-stream-buffer' ), '', array( 'response' => 400 ) );
		}

		$result = VSB_Compressor::schedule_compression( $attachment_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
		}

		// Redirect back to the referring page.
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'upload.php' );
		}

		wp_safe_redirect( add_query_arg( 'vsb_compressed', $attachment_id, $redirect ) );
		exit;
	}

	/**
	 * AJAX handler: check compression status.
	 *
	 * @since 2.0.0
	 */
	public static function ajax_check_status() {
		if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;

		if ( ! $attachment_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'vsb_check_status_' . $attachment_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 400 );
		}

		$status = VSB_Compressor::get_compression_status( $attachment_id );

		wp_send_json_success(
			array(
				'status'      => $status,
				'resolutions' => VSB_Compressor::get_available_resolutions( $attachment_id ),
				'html'        => VSB_Compressor::get_status_html( $attachment_id ),
			)
		);
	}

	/**
	 * Admin notices for compression actions.
	 *
	 * @since 2.0.0
	 */
	public static function admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Show "compression scheduled" notice on the Media Library page.
		if ( 'upload' === $screen->base && isset( $_GET['vsb_compressed'] ) ) {
			$attachment_id = absint( $_GET['vsb_compressed'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: attachment ID */
					esc_html__( 'Compression has been scheduled for video #%d. It will be processed in the background.', 'video-stream-buffer' ),
					$attachment_id
				)
			);
		}
	}
}
