<?php
/**
 * Core plugin bootstrap.
 *
 * Hooks all components into WordPress lifecycle events. This is the central
 * orchestrator — every subsystem (REST, shortcode, admin, Elementor widget)
 * is registered from here.
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
     */
    public static function init() {
        // Register the shortcode early (init).
        add_action( 'init', array( 'VSB_Shortcode', 'register' ) );

        // Register REST API routes.
        add_action( 'rest_api_init', array( 'VSB_Stream_Handler', 'register_routes' ) );

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

        // Elementor integration — deferred to 'plugins_loaded' so Elementor
        // is guaranteed to be loaded before we try to include the widget class
        // which extends \Elementor\Widget_Base.
        add_action( 'plugins_loaded', array( __CLASS__, 'init_elementor' ) );
    }

    /**
     * Initialize Elementor integration.
     *
     * Loads the widget file and registers hooks only if Elementor is active.
     * This runs on 'plugins_loaded' (priority 20, after Elementor loads at 10)
     * to ensure \Elementor\Widget_Base exists before our widget class is parsed.
     *
     * @since 1.0.1
     */
    public static function init_elementor() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        // Now safe to include — Elementor is loaded.
        require_once VSB_PLUGIN_DIR . 'includes/elementor/class-widget-video-stream.php';

        // Register Elementor hooks only when the widget class now exists.
        add_action( 'elementor/widgets/register', array( 'VSB_Widget_Video_Stream', 'register' ) );
        add_action( 'elementor/elements/categories_registered', array( 'VSB_Widget_Video_Stream', 'register_category' ) );
    }

    /**
     * Register frontend assets so they can be enqueued on demand by the
     * shortcode or widget.
     *
     * @since 1.0.0
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
}
