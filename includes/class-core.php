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

        // Elementor widget registration — hooks registered unconditionally.
        // The callbacks self-guard: they check for Widget_Base and include the
        // widget file on demand. If Elementor is not active, these hooks simply
        // never fire, so there's no overhead.
        add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
        add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_elementor_category' ) );
    }

    /**
     * Register the Video Stream Buffer widget with Elementor.
     *
     * Self-guarding: only proceeds if Widget_Base is available (i.e. Elementor
     * is the one firing this hook). Includes the widget file on demand.
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
     * Self-guarding: only proceeds if Widget_Base is available.
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
