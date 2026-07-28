<?php
/**
 * Elementor Widget — Video Stream Buffer.
 *
 * Provides a native Elementor widget that integrates with the Video Stream
 * Buffer plugin. Site builders can drop streamed videos into any page through
 * the visual editor.
 *
 * As of v2.0.0, the widget supports HLS adaptive bitrate streaming with a
 * quality selector when videos have been compressed.
 *
 * SECURITY / DESIGN NOTES:
 *
 * - The widget checks for Elementor's existence at class load time and in
 *   register() — if Elementor is absent, the widget silently skips
 *   registration so the plugin continues to function as shortcode-only.
 *
 * - The widget reuses VSB_Video_Helper for validation and the same REST
 *   endpoint URL as the shortcode. No streaming logic is duplicated.
 *
 * - Uses the modern `elementor/widgets/register` hook (Elementor 3.5+)
 *   rather than the deprecated `elementor/widgets/widgets_registered`.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * VSB Elementor Widget.
 *
 * Only loaded when Elementor is active (see main plugin file).
 */
class VSB_Widget_Video_Stream extends \Elementor\Widget_Base {

    /**
     * Get widget unique name/slug.
     *
     * @since  1.0.0
     * @return string Widget slug.
     */
    public function get_name() {
        return 'vsb_video_stream';
    }

    /**
     * Get widget title (shown in Elementor panel).
     *
     * @since  1.0.0
     * @return string Widget title.
     */
    public function get_title() {
        return __( 'Video Stream Buffer', 'video-stream-buffer' );
    }

    /**
     * Get widget icon.
     *
     * @since  1.0.0
     * @return string Elementor icon class.
     */
    public function get_icon() {
        return 'eicon-video-camera';
    }

    /**
     * Get widget categories.
     *
     * @since  1.0.0
     * @return string[] Category slugs.
     */
    public function get_categories() {
        return array( 'vsb_video_stream_category' );
    }

    /**
     * Get widget keywords for search in the Elementor panel.
     *
     * @since  1.0.0
     * @return string[] Keywords.
     */
    public function get_keywords() {
        return array( 'video', 'stream', 'buffer', 'mp4', 'media', 'play', 'hls', 'compression', 'quality' );
    }

    /**
     * Register a custom Elementor category for this widget.
     *
     * Called via `elementor/elements/categories_registered`.
     *
     * @since  1.0.0
     * @param  \Elementor\Elements_Manager $elements_manager Elementor elements manager.
     */
    public static function register_category( $elements_manager ) {
        $elements_manager->add_category(
            'vsb_video_stream_category',
            array(
                'title' => __( 'Video Stream Buffer', 'video-stream-buffer' ),
                'icon'  => 'fa fa-play',
            )
        );
    }

    /**
     * Register the widget with Elementor.
     *
     * Guard clause: only registers if Elementor is loaded. Called via the
     * modern `elementor/widgets/register` hook.
     *
     * @since  1.0.0
     * @param  \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
     */
    public static function register( $widgets_manager ) {
        // Double-check Elementor is available before registering.
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        $widgets_manager->register( new self() );
    }

    // -------------------------------------------------------------------------
    // Controls Registration
    // -------------------------------------------------------------------------

    /**
     * Register widget controls (Content + Style tabs).
     *
     * @since 1.0.0
     * @since 1.1.0 Added controls_style selector and Custom Controls style section.
     * @since 2.0.0 Added preferred_quality selector.
     */
    protected function register_controls() {

        // =====================================================================
        // CONTENT TAB — Video Settings
        // =====================================================================
        $this->start_controls_section(
            'vsb_content_section',
            array(
                'label' => __( 'Video Settings', 'video-stream-buffer' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        // Video selection from Media Library (video-only picker).
        $this->add_control(
            'video_attachment',
            array(
                'label'       => __( 'Choose Video', 'video-stream-buffer' ),
                'type'        => \Elementor\Controls_Manager::MEDIA,
                'media_type'  => 'video',
                'description' => __( 'Select a video from the WordPress Media Library. Supported formats: MP4, WebM, Ogg. If the video has been compressed for HLS, adaptive bitrate streaming with a quality selector will be available.', 'video-stream-buffer' ),
            )
        );

        // Autoplay toggle.
        $this->add_control(
            'autoplay',
            array(
                'label'        => __( 'Autoplay', 'video-stream-buffer' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'video-stream-buffer' ),
                'label_off'    => __( 'No', 'video-stream-buffer' ),
                'return_value' => 'yes',
                'default'      => 'no',
                'description'  => __( 'Autoplay the video when the page loads. Note: browsers require muted autoplay.', 'video-stream-buffer' ),
            )
        );

        // Loop toggle.
        $this->add_control(
            'loop',
            array(
                'label'        => __( 'Loop', 'video-stream-buffer' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'video-stream-buffer' ),
                'label_off'    => __( 'No', 'video-stream-buffer' ),
                'return_value' => 'yes',
                'default'      => 'no',
            )
        );

        // Muted toggle.
        $this->add_control(
            'muted',
            array(
                'label'        => __( 'Muted', 'video-stream-buffer' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'video-stream-buffer' ),
                'label_off'    => __( 'No', 'video-stream-buffer' ),
                'return_value' => 'yes',
                'default'      => 'no',
            )
        );

        // Show buffer bar toggle.
        $this->add_control(
            'show_buffer',
            array(
                'label'        => __( 'Show Buffer Bar', 'video-stream-buffer' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'video-stream-buffer' ),
                'label_off'    => __( 'No', 'video-stream-buffer' ),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __( 'Display a visual buffer progress indicator. When using Custom Controls, the buffer is shown on the seek bar.', 'video-stream-buffer' ),
            )
        );

        // Aspect ratio selector.
        $this->add_control(
            'aspect_ratio',
            array(
                'label'   => __( 'Aspect Ratio', 'video-stream-buffer' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '16:9',
                'options' => array(
                    '16:9' => '16:9',
                    '4:3'  => '4:3',
                    '1:1'  => '1:1',
                    'auto' => __( 'Auto (native)', 'video-stream-buffer' ),
                ),
            )
        );

        // Poster image picker.
        $this->add_control(
            'poster',
            array(
                'label'       => __( 'Poster Image', 'video-stream-buffer' ),
                'type'        => \Elementor\Controls_Manager::MEDIA,
                'media_type'  => 'image',
                'description' => __( 'Optional poster image shown before playback starts.', 'video-stream-buffer' ),
            )
        );

        // Controls style selector (new in 1.1.0).
        $this->add_control(
            'controls_style',
            array(
                'label'       => __( 'Controls Style', 'video-stream-buffer' ),
                'type'        => \Elementor\Controls_Manager::SELECT,
                'default'     => 'custom',
                'options'     => array(
                    'custom' => __( 'Custom Controls', 'video-stream-buffer' ),
                    'native' => __( 'Native Browser', 'video-stream-buffer' ),
                ),
                'description' => __( 'Custom Controls provide a branded, consistent playback experience with seek bar, volume slider, speed selector, quality selector (HLS), and fullscreen. Native uses the browser defaults.', 'video-stream-buffer' ),
            )
        );

        // Preferred quality selector (new in 2.0.0).
        $this->add_control(
            'preferred_quality',
            array(
                'label'       => __( 'Preferred Quality', 'video-stream-buffer' ),
                'type'        => \Elementor\Controls_Manager::SELECT,
                'default'     => 'auto',
                'options'     => array(
                    'auto'  => __( 'Auto (adaptive)', 'video-stream-buffer' ),
                    '1080p' => '1080p',
                    '720p'  => '720p',
                    '480p'  => '480p',
                    '360p'  => '360p',
                ),
                'description' => __( 'Preferred starting quality for HLS adaptive bitrate streaming. Only applies when the video has been compressed. "Auto" lets the browser adapt based on network conditions.', 'video-stream-buffer' ),
            )
        );

        $this->end_controls_section();

        // =====================================================================
        // STYLE TAB — Buffer Bar Styles (legacy, for native controls)
        // =====================================================================
        $this->start_controls_section(
            'vsb_style_section',
            array(
                'label' => __( 'Buffer Bar Style', 'video-stream-buffer' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        // Buffer bar (loaded portion) color.
        $this->add_control(
            'buffer_bar_color',
            array(
                'label'     => __( 'Buffer Bar Color', 'video-stream-buffer' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#00aaff',
                'selectors' => array(
                    '{{WRAPPER}} .vsb-buffer-bar-fill' => 'background-color: {{VALUE}};',
                ),
            )
        );

        // Progress bar (played portion) color.
        $this->add_control(
            'progress_bar_color',
            array(
                'label'     => __( 'Progress Bar Color', 'video-stream-buffer' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vsb-buffer-bar' => 'background-color: {{VALUE}};',
                ),
            )
        );

        // Background color.
        $this->add_control(
            'background_color',
            array(
                'label'     => __( 'Background Color', 'video-stream-buffer' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#000000',
                'selectors' => array(
                    '{{WRAPPER}} .vsb-video-wrapper' => 'background-color: {{VALUE}};',
                ),
            )
        );

        // Border radius.
        $this->add_control(
            'border_radius',
            array(
                'label'      => __( 'Border Radius', 'video-stream-buffer' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array(
                    'px' => array(
                        'min'  => 0,
                        'max'  => 20,
                        'step' => 1,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 8,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .vsb-video-wrapper' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
                ),
            )
        );

        $this->end_controls_section();

        // =====================================================================
        // STYLE TAB — Custom Controls Styles (new in 1.1.0)
        // =====================================================================
        $this->start_controls_section(
            'vsb_custom_controls_style',
            array(
                'label' => __( 'Custom Controls', 'video-stream-buffer' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        // Controls background color.
        $this->add_control(
            'custom_controls_bg',
            array(
                'label'   => __( 'Controls Background', 'video-stream-buffer' ),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(0, 0, 0, 0.8)',
            )
        );

        // Progress bar (played) color.
        $this->add_control(
            'custom_progress_color',
            array(
                'label'   => __( 'Progress Bar Color', 'video-stream-buffer' ),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => '#00aaff',
            )
        );

        // Buffer bar color (buffered portion on seek bar).
        $this->add_control(
            'custom_buffered_color',
            array(
                'label'   => __( 'Buffered Bar Color', 'video-stream-buffer' ),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(255, 255, 255, 0.25)',
            )
        );

        // Text/icon color.
        $this->add_control(
            'custom_text_color',
            array(
                'label'   => __( 'Text / Icon Color', 'video-stream-buffer' ),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
            )
        );

        // Controls border radius.
        $this->add_control(
            'custom_controls_radius',
            array(
                'label'      => __( 'Controls Border Radius', 'video-stream-buffer' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range'      => array(
                    'px' => array(
                        'min'  => 0,
                        'max'  => 20,
                        'step' => 1,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 6,
                ),
            )
        );

        $this->end_controls_section();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Render the widget output on the frontend.
     *
     * Reuses the same REST URL generation and HTML structure as the shortcode.
     * Validation is done via VSB_Video_Helper.
     *
     * @since 1.0.0
     * @since 1.1.0 Added custom controls support with CSS custom properties.
     * @since 2.0.0 Added HLS support with preferred_quality.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get the video attachment ID from the Media control.
        $attachment_id = isset( $settings['video_attachment']['id'] )
            ? absint( $settings['video_attachment']['id'] )
            : 0;

        if ( ! $attachment_id ) {
            if ( $this->is_edit_mode() ) {
                echo '<div class="vsb-editor-placeholder">'
                    . esc_html__( 'Please select a video from the Media Library.', 'video-stream-buffer' )
                    . '</div>';
            }
            return;
        }

        // Validate the video via shared helper.
        $video = VSB_Video_Helper::get_video_by_attachment_id( $attachment_id );
        if ( is_wp_error( $video ) ) {
            if ( $this->is_edit_mode() ) {
                echo '<div class="vsb-editor-placeholder vsb-error">'
                    . esc_html( $video->get_error_message() )
                    . '</div>';
            }
            return;
        }

        // Read settings with defaults.
        $autoplay          = 'yes' === $settings['autoplay'];
        $loop              = 'yes' === $settings['loop'];
        $muted             = 'yes' === $settings['muted'];
        $show_buffer       = 'yes' === $settings['show_buffer'];
        $aspect            = isset( $settings['aspect_ratio'] ) ? $settings['aspect_ratio'] : '16:9';
        $controls_style    = isset( $settings['controls_style'] ) ? $settings['controls_style'] : 'custom';
        $preferred_quality = isset( $settings['preferred_quality'] ) ? $settings['preferred_quality'] : 'auto';
        $use_custom        = ( 'custom' === $controls_style );

        // Validate preferred quality.
        $allowed_qualities = array( 'auto', '1080p', '720p', '480p', '360p' );
        if ( ! in_array( $preferred_quality, $allowed_qualities, true ) ) {
            $preferred_quality = 'auto';
        }

        // -----------------------------------------------------------------
        // Browser autoplay policy: browsers block autoplay of audible
        // videos. To ensure autoplay works, force muted when autoplay is on.
        // -----------------------------------------------------------------
        if ( $autoplay ) {
            $muted = true;
        }

        // Build the REST streaming URL (fallback, same as shortcode).
        $stream_url = rest_url( 'video-stream/v1/play/' . $attachment_id );

        // -----------------------------------------------------------------
        // HLS detection (v2.0.0).
        // -----------------------------------------------------------------
        $has_hls  = false;
        $hls_url  = '';
        $available_resolutions = array();

        if ( class_exists( 'VSB_Compressor' ) && VSB_Compressor::is_compressed( $attachment_id ) ) {
            $has_hls               = true;
            $hls_url               = VSB_Compressor::get_hls_url( $attachment_id );
            $available_resolutions = VSB_Compressor::get_available_resolutions( $attachment_id );
        }

        // Poster URL.
        $poster_url = '';
        if ( ! empty( $settings['poster']['id'] ) ) {
            $poster_url = wp_get_attachment_url( absint( $settings['poster']['id'] ) );
        }

        // Build video element attributes.
        $video_attrs = array(
            'src'         => esc_url( $stream_url ),
            'playsinline' => 'playsinline',
            'preload'     => 'metadata',
        );

        // Only add native controls attribute when NOT using custom controls.
        if ( ! $use_custom ) {
            $video_attrs['controls'] = 'controls';
        }

        if ( $autoplay ) {
            $video_attrs['autoplay'] = 'autoplay';
        }
        if ( $loop ) {
            $video_attrs['loop'] = 'loop';
        }
        if ( $muted ) {
            $video_attrs['muted'] = 'muted';
        }
        if ( $poster_url ) {
            $video_attrs['poster'] = esc_url( $poster_url );
        }

        // HLS data attributes (v2.0.0).
        if ( $has_hls ) {
            $video_attrs['data-vsb-hls']              = esc_url( $hls_url );
            $video_attrs['data-vsb-preferred-quality'] = esc_attr( $preferred_quality );

            if ( ! empty( $available_resolutions ) ) {
                $video_attrs['data-vsb-resolutions'] = esc_attr( implode( ',', $available_resolutions ) );
            }
        }

        $attrs_string = '';
        foreach ( $video_attrs as $key => $value ) {
            $attrs_string .= sprintf( ' %s="%s"', $key, $value );
        }

        // Enqueue assets.
        wp_enqueue_style( 'vsb-video-stream' );

        // HLS.js: enqueue first when HLS is available so video-stream.js can depend on it (v2.0.0).
        if ( $has_hls ) {
            wp_enqueue_script(
                'vsb-hls-js',
                VSB_PLUGIN_URL . 'assets/js/hls.min.js',
                array(),
                VSB_VERSION,
                true
            );
        }

        // JS: custom controls. Depend on hls-js when HLS is available.
        $js_deps = $has_hls ? array( 'vsb-hls-js' ) : array();
        wp_enqueue_script(
            'vsb-video-stream',
            VSB_PLUGIN_URL . 'assets/js/video-stream.js',
            $js_deps,
            VSB_VERSION,
            true
        );

        // Build wrapper class.
        $wrapper_class = 'vsb-video-wrapper vsb-aspect-' . sanitize_html_class( str_replace( ':', '-', $aspect ) );
        if ( $show_buffer ) {
            $wrapper_class .= ' vsb-show-buffer';
        }
        if ( $use_custom ) {
            $wrapper_class .= ' vsb-custom-controls';
        }
        if ( $has_hls ) {
            $wrapper_class .= ' vsb-hls-available';
        }

        // Build inline style for CSS custom properties (custom controls theming).
        $inline_style = '';
        if ( $use_custom ) {
            $custom_props = array();

            if ( ! empty( $settings['custom_controls_bg'] ) ) {
                $custom_props[] = '--vsb-controls-bg: ' . esc_attr( $settings['custom_controls_bg'] );
            }
            if ( ! empty( $settings['custom_progress_color'] ) ) {
                $custom_props[] = '--vsb-controls-progress: ' . esc_attr( $settings['custom_progress_color'] );
            }
            if ( ! empty( $settings['custom_buffered_color'] ) ) {
                $custom_props[] = '--vsb-controls-buffered: ' . esc_attr( $settings['custom_buffered_color'] );
            }
            if ( ! empty( $settings['custom_text_color'] ) ) {
                $custom_props[] = '--vsb-controls-text: ' . esc_attr( $settings['custom_text_color'] );
            }
            if ( ! empty( $settings['custom_controls_radius']['size'] ) ) {
                $custom_props[] = '--vsb-controls-radius: ' . esc_attr( $settings['custom_controls_radius']['size'] ) . esc_attr( $settings['custom_controls_radius']['unit'] );
            }

            if ( ! empty( $custom_props ) ) {
                $inline_style = ' style="' . implode( '; ', $custom_props ) . '"';
            }
        }

        // Buffer progress bar overlay (only for native controls).
        $buffer_bar = '';
        if ( $show_buffer && ! $use_custom ) {
            $buffer_bar = '<div class="vsb-buffer-bar"><div class="vsb-buffer-bar-fill"></div></div>';
        }

        // In edit mode, show a placeholder overlay so the widget is visible in
        // the Elementor editor.
        if ( $this->is_edit_mode() ) {
            $edit_wrapper_class = $wrapper_class;
            echo '<div class="' . esc_attr( $edit_wrapper_class ) . '"' . $inline_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — $inline_style is escaped above.
            echo '<div class="vsb-editor-overlay">'
                . '<span class="vsb-editor-label">' . esc_html__( 'Video Stream Buffer', 'video-stream-buffer' ) . '</span>'
                . '<span class="vsb-editor-filename">' . esc_html( basename( $video['path'] ) ) . '</span>';

            if ( $has_hls ) {
                echo '<span class="vsb-editor-filename" style="margin-top:4px;">' . esc_html__( 'HLS Compressed', 'video-stream-buffer' ) . ' — ' . esc_html( implode( ', ', $available_resolutions ) ) . '</span>';
            }

            echo '</div>';
            echo $buffer_bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped.
            echo '<video' . $attrs_string . '></video>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            return;
        }

        // Frontend output.
        printf(
            '<div class="%s"%s>%s<video%s></video></div>',
            esc_attr( $wrapper_class ),
            $inline_style, // Already escaped above.
            $buffer_bar,   // Already escaped.
            $attrs_string  // Already escaped.
        );
    }

    /**
     * Whether we're in Elementor edit/preview mode.
     *
     * @since  1.0.0
     * @return bool
     */
    protected function is_edit_mode() {
        return \Elementor\Plugin::$instance->editor->is_edit_mode()
            || \Elementor\Plugin::$instance->preview->is_preview_mode();
    }
}
