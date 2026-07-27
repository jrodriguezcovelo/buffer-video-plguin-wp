=== Video Stream Buffer ===
Contributors: videostreambuffer
Tags: video, streaming, elementor, html5 video, range requests, media library, buffer, custom controls
Requires at least: 7.0.2
Tested up to: 7.0.2
Requires PHP: 8.3.32
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Delivers real video streaming via HTTP Range Requests from the WordPress Media Library, with a native Elementor widget, shortcode support, and custom video controls.

== Description ==

Video Stream Buffer enables true video streaming from your WordPress Media Library without needing a CDN or third-party video host. It serves video files through a custom REST API endpoint that supports HTTP Range Requests (byte-range), allowing browsers to seek, buffer, and play videos efficiently — exactly like a dedicated streaming server.

**Key features:**

- **True HTTP Range Request streaming** (HTTP 206 Partial Content) — no full-file loading
- **Custom video controls** — branded, consistent playback UI with play/pause, seek bar, volume, speed, and fullscreen (new in 1.1.0)
- **Native Elementor widget** with full visual controls — drop a video into any page with the visual editor
- **Shortcode support**: `[video_stream id="123"]` for non-Elementor pages
- **Streaming from the Media Library** — uses `wp-content/uploads` directly, no external services
- **Buffer progress visualization** — shows users how much of the video has loaded
- **Configurable chunk size** — balance memory usage vs. I/O performance
- **Optional login-only access** — restrict streaming to authenticated users
- **Path traversal protection** — strict file path validation prevents directory traversal attacks
- **Keyboard shortcuts** — Space, arrows, F, M for complete keyboard control
- **Responsive design** — mobile-optimized controls with larger touch targets

**Requirements:**

- WordPress 7.0.2+
- PHP 8.3.32+
- Elementor 3.x+ (for widget support — the shortcode works without Elementor)

== Installation ==

1. Upload the `video-stream-buffer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Settings > Video Stream Buffer** to configure chunk size and access restrictions.
4. Upload a video (MP4, WebM, or Ogg) to your Media Library.
5. Use the `[video_stream id="123"]` shortcode (replace 123 with your attachment ID), or add the "Video Stream Buffer" Elementor widget to any page.

== How to Test Streaming ==

Once you have uploaded a video and noted its attachment ID, you can verify the streaming endpoint works correctly using curl.

**Full file request (HTTP 200):**

`curl -I "https://yoursite.com/wp-json/video-stream/v1/play/123"`

Expected headers: `HTTP/1.1 200 OK`, `Accept-Ranges: bytes`, `Content-Type: video/mp4`

**Range request (HTTP 206 Partial Content):**

`curl -I -H "Range: bytes=0-1023" "https://yoursite.com/wp-json/video-stream/v1/play/123"`

Expected headers: `HTTP/1.1 206 Partial Content`, `Content-Range: bytes 0-1023/XXXX`, `Content-Length: 1024`

**Play in browser:**

Visit any page containing the shortcode or Elementor widget. Open the browser's Network tab and look for the `/wp-json/video-stream/v1/play/` request — it should show multiple requests with 206 status as you seek through the video.

== Shortcode Usage ==

`[video_stream id="123"]`

**Available attributes:**

- `id` (required) — WordPress attachment ID of the video
- `autoplay` — `true` or `false` (default: false; forces muted for browser policy)
- `loop` — `true` or `false` (default: false)
- `muted` — `true` or `false` (default: false)
- `show_buffer` — `true` or `false` (default: true)
- `aspect_ratio` — `16:9`, `4:3`, `1:1`, or `auto` (default: 16:9)
- `poster` — attachment ID for poster image
- `controls_style` — `native` or `custom` (default: `native`; **new in 1.1.0**)

**Examples:**

Full-featured video with custom controls: `[video_stream id="123" controls_style="custom" aspect_ratio="4:3" poster="456"]`

Native browser controls: `[video_stream id="123" controls_style="native"]`

Minimal: `[video_stream id="123"]`

== Elementor Widget ==

The "Video Stream Buffer" widget appears in the Elementor panel under the "Video Stream Buffer" category. It provides:

**Content controls:**
- Video picker (Media Library, video-only)
- Autoplay, Loop, Muted toggles
- Buffer bar visibility toggle
- Aspect ratio selector (16:9, 4:3, 1:1, auto)
- Poster image picker
- **Controls Style** selector — "Custom Controls" (default) or "Native Browser" (new in 1.1.0)

**Style controls — Buffer Bar:**
- Buffer bar color
- Progress bar background color
- Player background color
- Border radius

**Style controls — Custom Controls** (new in 1.1.0):
- Controls background color
- Progress bar color (played portion)
- Buffered bar color (buffered portion on seek bar)
- Text / icon color
- Controls border radius

== Custom Controls Features (1.1.0+) ==

When Controls Style is set to "Custom Controls", the plugin replaces native browser controls with a branded control bar:

- **Play/Pause** — button in control bar + center overlay when paused
- **Progress Bar** — clickable and draggable seek bar with played (accent color) and buffered (lighter color) portions
- **Time Display** — current time / total duration
- **Volume** — mute toggle button + horizontal slider
- **Fullscreen** — toggle fullscreen mode
- **Playback Speed** — selector with 0.5x, 0.75x, 1x, 1.25x, 1.5x, 2x options

**Keyboard Shortcuts:**
- Space — play/pause
- Left/Right arrows — skip 5 seconds
- Up/Down arrows — volume up/down
- F — toggle fullscreen
- M — mute/unmute

**Behavior:**
- Controls auto-hide after 3 seconds of cursor inactivity (desktop only)
- Controls stay visible when the video is paused
- On mobile, controls are always visible with larger touch targets
- Center play button appears briefly when paused

== Custom Controls Theming ==

Custom controls use CSS custom properties that can be styled via the Elementor widget settings or overridden in your theme:

- `--vsb-controls-bg` — controls bar background (default: `rgba(0, 0, 0, 0.8)`)
- `--vsb-controls-progress` — played portion of progress bar (default: `#00aaff`)
- `--vsb-controls-buffered` — buffered portion on seek bar (default: `rgba(255, 255, 255, 0.25)`)
- `--vsb-controls-text` — text and icon color (default: `#ffffff`)
- `--vsb-controls-radius` — controls border radius (default: `6px`)

== Known Limitations ==

- **No HLS/DASH support:** The plugin streams single video files via HTTP Range Requests. It does not segment videos or generate adaptive bitrate manifests. For high-traffic or multi-resolution streaming, consider pairing with a CDN.
- **Concurrent streaming load:** Each active stream holds a PHP process and file handle. At very high concurrency (hundreds of simultaneous viewers), a dedicated streaming server or CDN is more appropriate. The configurable chunk size helps tune memory usage.
- **Only media library files:** Videos must be uploaded through the WordPress Media Library. External URLs are not supported.
- **Single file per player:** Each widget/shortcode plays one video file. Playlists are not supported in this version.
- **No DRM:** The streaming endpoint does not implement digital rights management. Anyone with the REST URL can download the video file.

== Frequently Asked Questions ==

= Why does autoplay force muted? =

Modern browsers (Chrome, Firefox, Safari, Edge) block autoplay of videos with audio to prevent unwanted sound. By forcing `muted` when `autoplay` is on, the plugin ensures the video actually starts playing automatically.

= Can I use this with a CDN? =

Yes. The plugin streams from your WordPress server. For production sites with significant traffic, place a CDN in front of your site — the standard `Accept-Ranges`, `ETag`, and `Cache-Control` headers enable CDN caching of streamed chunks.

= What video formats are supported? =

MP4 (H.264), WebM, and Ogg. MP4 has the broadest browser support and is recommended.

= Is this secure? =

The plugin implements multiple security measures: path traversal prevention via realpath validation, MIME type allowlisting, nonce protection on admin forms, and proper data sanitization/escaping throughout. The optional login-only restriction adds an additional access layer.

= Are custom controls accessible? =

Yes. All buttons have `aria-label` attributes, the video element is keyboard-focusable, and keyboard shortcuts provide full control without a mouse. The progress bar uses a native range input for accessibility.

== Changelog ==

= 1.1.0 =
* Added: Custom video controls UI replacing native browser controls
* Added: Play/Pause button with center overlay on pause
* Added: Clickable/draggable progress bar with played and buffered portions
* Added: Time display (current time / duration)
* Added: Volume button with mute toggle and horizontal slider
* Added: Fullscreen toggle button
* Added: Playback speed selector (0.5x to 2x)
* Added: Keyboard shortcuts (Space, arrows, F, M, Up/Down)
* Added: Auto-hide behavior (3s inactivity, stays visible when paused)
* Added: Mobile-responsive layout with larger touch targets
* Added: `controls_style` shortcode attribute (`native` or `custom`)
* Added: Controls Style selector in Elementor widget (defaults to Custom Controls)
* Added: Custom Controls style tab in Elementor with 5 color/radius controls
* Added: CSS custom properties for easy theming (--vsb-controls-bg, --vsb-controls-progress, etc.)
* Improved: Aspect ratio "auto" now works naturally — video renders at native size, controls below
* Improved: Fullscreen mode properly sizes video and maintains controls
* All icons are inline SVG — no icon font dependencies
* Backward compatible: omitting `controls_style` defaults to native controls

= 1.0.3 =
* Fixed: Elementor widget not appearing in the editor
* Elementor hooks now registered directly in init() instead of deferred via plugins_loaded
* Callbacks self-guard with class_exists checks — safe even without Elementor
* Widget file included on demand when Elementor fires its registration hooks

= 1.0.2 =
* Fixed fatal error: "Class Elementor\Widget_Base not found" during wp-cron and early WordPress lifecycle
* init_elementor() now checks for the actual parent class (\Elementor\Widget_Base) instead of \Elementor\Plugin
* Prevents widget file inclusion when Elementor's autoloader hasn't loaded base widget classes

= 1.0.1 =
* Fixed fatal error: "Class VSB_Widget_Video_Stream not found" when Elementor loads after this plugin
* Elementor widget file loading deferred to 'plugins_loaded' hook to guarantee Elementor is available
* Elementor hook registrations now guarded inside init_elementor()

= 1.0.0 =
* Initial release
* REST API streaming endpoint with HTTP Range Request support
* `[video_stream]` shortcode
* Native Elementor widget with content and style controls
* Admin settings page (chunk size, access restriction)
* Buffer progress bar visualization
* Path traversal protection and MIME type validation

== Upgrade Notice ==

= 1.1.0 =
New: Custom video controls with progress bar, volume slider, speed selector, and fullscreen. The Elementor widget now defaults to Custom Controls — existing widgets will update automatically. Shortcodes remain on native controls by default for backward compatibility; add `controls_style="custom"` to opt in.

= 1.0.0 =
Initial release. No upgrade steps required.
