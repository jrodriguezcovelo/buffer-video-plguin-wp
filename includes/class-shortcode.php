<?php
/**
 * Shortcode handler for [video_stream].
 *
 * Provides a simple shortcode for embedding streamed videos in any post,
 * page, or widget area. Uses the same REST endpoint and validation as the
 * Elementor widget.
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
	 *   - id (int, required):   Attachment ID of the video.
	 *   - autoplay (bool):       Autoplay the video. Forces muted for browser policy.
	 *   - loop (bool):           Loop playback.
	 *   - muted (bool):          Mute the video.
	 *   - show_buffer (bool):    Show the buffer progress bar overlay. Default: true.
	 *   - aspect_ratio (string): 16:9, 4:3, 1:1, or auto. Default: 16:9.
	 *   - poster (int):          Attachment ID for poster image.
	 *
	 * @since  1.0.0
	 * @param  array  $atts    User-defined shortcode attributes.
	 * @param  string $content Enclosed content (not used).
	 * @return string          HTML output of the video player.
	 */
	public static function render( $atts, $content = '' ) {
		// Parse attributes with defaults.
		$atts = shortcode_atts(
			array(
				'id'           => 0,
				'autoplay'     => false,
				'loop'         => false,
				'muted'        => false,
				'show_buffer'  => true,
				'aspect_ratio' => '16:9',
				'poster'       => 0,
			),
			$atts,
			'video_stream'
		);

		// Sanitize attributes.
		$attachment_id = absint( $atts['id'] );
		$autoplay      = filter_var( $atts['autoplay'], FILTER_VALIDATE_BOOLEAN );
		$loop          = filter_var( $atts['loop'], FILTER_VALIDATE_BOOLEAN );
		$muted         = filter_var( $atts['muted'], FILTER_VALIDATE_BOOLEAN );
		$show_buffer   = filter_var( $atts['show_buffer'], FILTER_VALIDATE_BOOLEAN );
		$poster_id     = absint( $atts['poster'] );

		// Allowed aspect ratios.
		$allowed_ratios = array( '16:9', '4:3', '1:1', 'auto' );
		$aspect_ratio   = in_array( $atts['aspect_ratio'], $allowed_ratios, true )
			? $atts['aspect_ratio']
			: '16:9';

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
		// Build the REST streaming URL.
		// rest_url() is the canonical way to generate REST endpoint URLs.
		// No hardcoded paths.
		// -----------------------------------------------------------------
		$stream_url = rest_url( 'video-stream/v1/play/' . $attachment_id );

		// -----------------------------------------------------------------
		// Browser autoplay policy: most modern browsers block autoplay of
		// videos with audio unless they are muted. To ensure autoplay
		// actually works, we force the muted attribute when autoplay is on.
		// See: https://developer.mozilla.org/en-US/docs/Web/Media/Autoplay_guide
		// -----------------------------------------------------------------
		if ( $autoplay ) {
			$muted = true;
		}

		// Resolve poster URL if a poster attachment ID was provided.
		$poster_url = '';
		if ( $poster_id ) {
			$poster_url = wp_get_attachment_url( $poster_id );
		}

		// Build attributes for the <video> element.
		$video_attrs = array(
			'src'         => esc_url( $stream_url ),
			'controls'    => 'controls',
			'playsinline' => 'playsinline',
			'preload'     => 'metadata',
		);

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

		// Assemble the <video> attributes string.
		$attrs_string = '';
		foreach ( $video_attrs as $key => $value ) {
			$attrs_string .= sprintf( ' %s="%s"', $key, $value );
		}

		// Enqueue assets.
		self::enqueue_assets();

		// Build the wrapper with aspect ratio class.
		$wrapper_class = 'vsb-video-wrapper vsb-aspect-' . sanitize_html_class( str_replace( ':', '-', $aspect_ratio ) );
		if ( $show_buffer ) {
			$wrapper_class .= ' vsb-show-buffer';
		}

		// Buffer progress bar (only shown when show_buffer is true).
		$buffer_bar = '';
		if ( $show_buffer ) {
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
	 */
	public static function enqueue_assets() {
		// CSS: frontend player styles.
		wp_enqueue_style(
			'vsb-video-stream',
			VSB_PLUGIN_URL . 'assets/css/video-stream.css',
			array(),
			VSB_VERSION
		);

		// JS: buffer progress bar visualization.
		wp_enqueue_script(
			'vsb-video-stream',
			VSB_PLUGIN_URL . 'assets/js/video-stream.js',
			array(),
			VSB_VERSION,
			true // Load in footer.
		);
	}
}
