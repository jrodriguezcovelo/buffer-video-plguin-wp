<?php
/**
 * Shortcode handler for [video_stream].
 *
 * Provides a simple shortcode for embedding streamed videos in any post,
 * page, or widget area. Uses the same REST endpoint and validation as the
 * Elementor widget.
 *
 * As of v2.0.0, the shortcode detects HLS-compressed videos and serves
 * adaptive bitrate streaming through hls.js with a quality selector.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

class VSB_Shortcode {

    /**
     * Register the shortcode.
     *
     * @since 1.0.0
     */
    public static function register() {
        add_shortcode( 'video_stream', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the [video_stream] shortcode.
     *
     * Attributes:
     *   - id (int, required):         Attachment ID of the video.
     *   - autoplay (bool):             Autoplay the video. Forces muted for browser policy.
     *   - loop (bool):                 Loop playback.
     *   - muted (bool):                Mute the video.
     *   - show_buffer (bool):          Show the buffer progress bar. Default: true.
     *   - aspect_ratio (string):       16:9, 4:3, 1:1, or auto. Default: 16:9.
     *   - poster (int):                Attachment ID for poster image.
     *   - controls_style (string):     'native' (default) or 'custom'.
     *   - preferred_quality (string):  'auto' (default), '1080p', '720p', '480p', '360p'.
     *
     * @since  1.0.0
     * @since  1.1.0 Added controls_style attribute.
     * @since  2.0.0 Added preferred_quality attribute and HLS support.
     *
     * @param  array  $atts    User-defined shortcode attributes.
     * @param  string $content Enclosed content (not used).
     * @return string          HTML output of the video player.
     */
    public static function render( $atts, $content = '' ) {
        // Parse attributes with defaults.
        $atts = shortcode_atts(
            array(
                'id'                => 0,
                'autoplay'          => false,
                'loop'              => false,
                'muted'             => false,
                'show_buffer'       => true,
                'aspect_ratio'      => '16:9',
                'poster'            => 0,
                'controls_style'    => 'native',
                'preferred_quality' => 'auto',
            ),
            $atts,
            'video_stream'
        );

        // Sanitize attributes.
        $attachment_id     = absint( $atts['id'] );
        $autoplay          = filter_var( $atts['autoplay'], FILTER_VALIDATE_BOOLEAN );
        $loop              = filter_var( $atts['loop'], FILTER_VALIDATE_BOOLEAN );
        $muted             = filter_var( $atts['muted'], FILTER_VALIDATE_BOOLEAN );
        $show_buffer       = filter_var( $atts['show_buffer'], FILTER_VALIDATE_BOOLEAN );
        $poster_id         = absint( $atts['poster'] );

        // Allowed aspect ratios.
        $allowed_ratios = array( '16:9', '4:3', '1:1', 'auto' );
        $aspect_ratio   = in_array( $atts['aspect_ratio'], $allowed_ratios, true )
            ? $atts['aspect_ratio']
            : '16:9';

        // Allowed controls styles: 'native' or 'custom'.
        $allowed_controls = array( 'native', 'custom' );
        $controls_style   = in_array( $atts['controls_style'], $allowed_controls, true )
            ? $atts['controls_style']
            : 'native';

        // Allowed quality preferences: 'auto' or specific resolution.
        $allowed_qualities = array( 'auto', '1080p', '720p', '480p', '360p' );
        $preferred_quality = in_array( $atts['preferred_quality'], $allowed_qualities, true )
            ? $atts['preferred_quality']
            : 'auto';

        // Require a valid attachment ID.
        if ( ! $attachment_id ) {
            return '<p class="vsb-error">'
                . esc_html__( 'Invalid video ID. Please specify a valid attachment ID.', 'video-stream-buffer' )
                . '</p>';
        }

        // Validate the video via the shared helper.
        $video = VSB_Video_Helper::get_video_by_attachment_id( $attachment_id );
        if ( is_wp_error( $video ) ) {
            return '<p class="vsb-error">'
                . esc_html( $video->get_error_message() )
                . '</p>';
        }

        // -----------------------------------------------------------------
        // Build the REST streaming URL (fallback for non-HLS).
        // -----------------------------------------------------------------
        $stream_url = rest_url( 'video-stream/v1/play/' . $attachment_id );

        // -----------------------------------------------------------------
        // Check for HLS compressed version (v2.0.0).
        // -----------------------------------------------------------------
        $has_hls  = false;
        $hls_url  = '';
        $available_resolutions = array();

        if ( class_exists( 'VSB_Compressor' ) && VSB_Compressor::is_compressed( $attachment_id ) ) {
            $has_hls              = true;
            $hls_url              = VSB_Compressor::get_hls_url( $attachment_id );
            $available_resolutions = VSB_Compressor::get_available_resolutions( $attachment_id );
        }

        // -----------------------------------------------------------------
        // Browser autoplay policy: most modern browsers block autoplay of
        // videos with audio unless they are muted. To ensure autoplay
        // actually works, we force the muted attribute when autoplay is on.
        // -----------------------------------------------------------------
        if ( $autoplay ) {
            $muted = true;
        }

        // Resolve poster URL if a poster attachment ID was provided.
        $poster_url = '';
        if ( $poster_id ) {
            $poster_url = wp_get_attachment_url( $poster_id );
        }

        // Determine whether to use custom controls.
        $use_custom_controls = ( 'custom' === $controls_style );

        // Build attributes for the <video> element.
        $video_attrs = array(
            'playsinline' => 'playsinline',
            'preload'     => 'metadata',
        );

        // For HLS: the src should be the fallback (direct MP4) for browsers
        // that don't support hls.js. The HLS URL is set via data attribute.
        $video_attrs['src'] = esc_url( $stream_url );

        // Only add the native controls attribute when NOT using custom controls.
        if ( ! $use_custom_controls ) {
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
            $video_attrs['data-vsb-hls']             = esc_url( $hls_url );
            $video_attrs['data-vsb-preferred-quality'] = esc_attr( $preferred_quality );

            if ( ! empty( $available_resolutions ) ) {
                $video_attrs['data-vsb-resolutions'] = esc_attr( implode( ',', $available_resolutions ) );
            }
        }

        // Assemble the <video> attributes string.
        $attrs_string = '';
        foreach ( $video_attrs as $key => $value ) {
            $attrs_string .= sprintf( ' %s="%s"', $key, $value );
        }

        // Enqueue assets.
        self::enqueue_assets( $has_hls );

        // Build the wrapper with aspect ratio class.
        $wrapper_class = 'vsb-video-wrapper vsb-aspect-' . sanitize_html_class( str_replace( ':', '-', $aspect_ratio ) );

        // Add show-buffer class if buffer bar is enabled.
        if ( $show_buffer ) {
            $wrapper_class .= ' vsb-show-buffer';
        }

        // When custom controls are active, add the custom-controls class.
        if ( $use_custom_controls ) {
            $wrapper_class .= ' vsb-custom-controls';
        }

        // When HLS is available, add an HLS class for JS detection.
        if ( $has_hls ) {
            $wrapper_class .= ' vsb-hls-available';
        }

        // Buffer progress bar (only shown for native controls; custom controls
        // embed the buffer visualization in the progress bar).
        $buffer_bar = '';
        if ( $show_buffer && ! $use_custom_controls ) {
            $buffer_bar = '<div class="vsb-buffer-bar"><div class="vsb-buffer-bar-fill"></div></div>';
        }

        $output = sprintf(
            '<div class="%s">%s<video%s></video></div>',
            esc_attr( $wrapper_class ),
            $buffer_bar,
            $attrs_string // Already escaped above.
        );

        return $output;
    }

    /**
     * Enqueue the plugin's frontend CSS and JS.
     *
     * @since 1.0.0
     * @since 2.0.0 Added conditional hls.js enqueue.
     *
     * @param bool $has_hls Whether this video has HLS available.
     */
    public static function enqueue_assets( $has_hls = false ) {
        // CSS: frontend player styles.
        wp_enqueue_style(
            'vsb-video-stream',
            VSB_PLUGIN_URL . 'assets/css/video-stream.css',
            array(),
            VSB_VERSION
        );

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

        // JS: custom controls and buffer progress bar visualization.
        // When HLS is available, depend on hls-js to ensure Hls is defined.
        $js_deps = $has_hls ? array( 'vsb-hls-js' ) : array();
        wp_enqueue_script(
            'vsb-video-stream',
            VSB_PLUGIN_URL . 'assets/js/video-stream.js',
            $js_deps,
            VSB_VERSION,
            true // Load in footer.
        );
    }
}
