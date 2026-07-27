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
 * Description:       Delivers real video streaming via HTTP Range Requests from the WordPress Media Library, with a native Elementor widget and shortcode support.
 * Version:           1.1.0
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
define( 'VSB_VERSION', '1.1.0' );
define( 'VSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// Autoload includes
// -----------------------------------------------------------------------------

// Always load the video helper — it's the shared core used by all components.
require_once VSB_PLUGIN_DIR . 'includes/class-video-helper.php';

// Load core bootstrap.
require_once VSB_PLUGIN_DIR . 'includes/class-core.php';

// Load stream handler (REST endpoint).
require_once VSB_PLUGIN_DIR . 'includes/class-stream-handler.php';

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
 */
function vsb_activate() {
    // Flush rewrite rules so our REST route is available immediately.
    flush_rewrite_rules();

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
