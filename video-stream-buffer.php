<?php
/**
 * Video Stream Buffer
 *
 * @package           VideoStreamBuffer
 * @author            Video Stream Buffer Team
 * @copyright         2026
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Video Stream Buffer
 * Plugin URI:        https://example.com/video-stream-buffer
 * Description:       Delivers real video streaming via HTTP Range Requests from the WordPress Media Library, with HLS adaptive bitrate streaming, FFmpeg compression, quality selector, native Elementor widget, and shortcode support.
 * Version:           2.0.0
 * Requires at least: 7.0.2
 * Requires PHP:      8.3.32
 * Author:            Video Stream Buffer Team
 * Author URI:        https://example.com
 * Text Domain:       video-stream-buffer
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'VSB_VERSION', '2.0.0' );
define( 'VSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// Autoload includes
// -----------------------------------------------------------------------------

// Always load the video helper — it's the shared core used by all components.
require_once VSB_PLUGIN_DIR . 'includes/class-video-helper.php';

// Load core bootstrap.
require_once VSB_PLUGIN_DIR . 'includes/class-core.php';

// Load stream handler (REST endpoint for single-file streaming).
require_once VSB_PLUGIN_DIR . 'includes/class-stream-handler.php';

// Load HLS handler (REST endpoints for HLS manifests and segments) — v2.0.0.
require_once VSB_PLUGIN_DIR . 'includes/class-hls-handler.php';

// Load compressor (FFmpeg HLS compression engine) — v2.0.0.
require_once VSB_PLUGIN_DIR . 'includes/class-compressor.php';

// Load shortcode.
require_once VSB_PLUGIN_DIR . 'includes/class-shortcode.php';

// Load admin settings.
require_once VSB_PLUGIN_DIR . 'includes/class-admin-settings.php';

// Elementor widget loading is deferred to class-core.php (via 'plugins_loaded' hook)
// to ensure Elementor itself has loaded before we try to include the widget class.
// The widget file extends \Elementor\Widget_Base, so it can only be loaded after Elementor.

// -----------------------------------------------------------------------------
// Activation / Deactivation hooks
// -----------------------------------------------------------------------------

/**
 * Activation hook: flush rewrite rules so the REST API routes are discoverable.
 *
 * Also creates the vsb-compressed directory under uploads.
 */
function vsb_activate() {
	// Flush rewrite rules so our REST routes are available immediately.
	flush_rewrite_rules();

	// Create the compressed output directory if it doesn't exist.
	if ( class_exists( 'VSB_Compressor' ) ) {
		VSB_Compressor::get_output_base_dir();
	}

	// Set a transient to notify the admin of next steps.
	set_transient( 'vsb_activation_notice', true, 30 );
}
register_activation_hook( __FILE__, 'vsb_activate' );

/**
 * Deactivation hook: flush rewrite rules to clean up.
 */
function vsb_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'vsb_deactivate' );

// -----------------------------------------------------------------------------
// Bootstrap the plugin
// -----------------------------------------------------------------------------
VSB_Core::init();
