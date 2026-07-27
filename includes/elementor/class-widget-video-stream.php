<?php
/**
 * Elementor Widget — Video Stream Buffer.
 *
 * Provides a native Elementor widget that integrates with the Video Stream
 * Buffer plugin. Site builders can drop streamed videos into any page through
 * the visual editor.
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
		return array( 'video', 'stream', 'buffer', 'mp4', 'media', 'play' );
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
				'description' => __( 'Select a video from the WordPress Media Library. Supported formats: MP4, WebM, Ogg.', 'video-stream-buffer' ),
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
				'description'  => __( 'Display a visual buffer progress indicator below the video.', 'video-stream-buffer' ),
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

		$this->end_controls_section();

		// =====================================================================
		// STYLE TAB — Buffer Bar Styles
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
		$autoplay    = 'yes' === $settings['autoplay'];
		$loop        = 'yes' === $settings['loop'];
		$muted       = 'yes' === $settings['muted'];
		$show_buffer = 'yes' === $settings['show_buffer'];
		$aspect      = isset( $settings['aspect_ratio'] ) ? $settings['aspect_ratio'] : '16:9';

		// -----------------------------------------------------------------
		// Browser autoplay policy: browsers block autoplay of audible
		// videos. To ensure autoplay works, force muted when autoplay is on.
		// -----------------------------------------------------------------
		if ( $autoplay ) {
			$muted = true;
		}

		// Build the REST streaming URL (same as shortcode).
		$stream_url = rest_url( 'video-stream/v1/play/' . $attachment_id );

		// Poster URL.
		$poster_url = '';
		if ( ! empty( $settings['poster']['id'] ) ) {
			$poster_url = wp_get_attachment_url( absint( $settings['poster']['id'] ) );
		}

		// Build video element attributes.
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

		$attrs_string = '';
		foreach ( $video_attrs as $key => $value ) {
			$attrs_string .= sprintf( ' %s="%s"', $key, $value );
		}

		// Enqueue assets.
		wp_enqueue_style( 'vsb-video-stream' );
		wp_enqueue_script( 'vsb-video-stream' );

		// Build wrapper class.
		$wrapper_class = 'vsb-video-wrapper vsb-aspect-' . sanitize_html_class( str_replace( ':', '-', $aspect ) );
		if ( $show_buffer ) {
			$wrapper_class .= ' vsb-show-buffer';
		}

		// Buffer progress bar overlay.
		$buffer_bar = '';
		if ( $show_buffer ) {
			$buffer_bar = '<div class="vsb-buffer-bar"><div class="vsb-buffer-bar-fill"></div></div>';
		}

		// In edit mode, show a placeholder overlay so the widget is visible in
		// the Elementor editor.
		if ( $this->is_edit_mode() ) {
			echo '<div class="' . esc_attr( $wrapper_class ) . '">';
			echo '<div class="vsb-editor-overlay">'
				. '<span class="vsb-editor-label">' . esc_html__( 'Video Stream Buffer', 'video-stream-buffer' ) . '</span>'
				. '<span class="vsb-editor-filename">' . esc_html( basename( $video['path'] ) ) . '</span>'
				. '</div>';
			echo $buffer_bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped.
			echo '<video' . $attrs_string . '></video>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return;
		}

		// Frontend output.
		printf(
			'<div class="%s">%s<video%s></video></div>',
			esc_attr( $wrapper_class ),
			$buffer_bar, // Already escaped.
			$attrs_string // Already escaped.
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
