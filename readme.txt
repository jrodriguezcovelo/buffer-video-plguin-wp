=== Video Stream Buffer ===
Contributors: videostreambuffer
Tags: video, streaming, elementor, html5 video, range requests, media library, buffer, custom controls, hls, ffmpeg, adaptive bitrate, compression
Requires at least: 7.0.2
Tested up to: 7.0.2
Requires PHP: 8.3.32
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Delivers real video streaming via HTTP Range Requests from the WordPress Media Library, with HLS adaptive bitrate streaming, FFmpeg compression, quality selector, native Elementor widget, and shortcode support.

== Description ==

Video Stream Buffer enables true video streaming from your WordPress Media Library without needing a CDN or third-party video host. It serves video files through a custom REST API endpoint that supports HTTP Range Requests (byte-range), allowing browsers to seek, buffer, and play videos efficiently — exactly like a dedicated streaming server.

**New in v2.0.0: HLS Adaptive Bitrate Streaming with FFmpeg compression!**

**Key features:**

- **HLS adaptive bitrate streaming** — compress videos into multiple quality levels (1080p, 720p, 480p, 360p) with on-the-fly quality switching (new in 2.0.0)
- **FFmpeg-based compression engine** — configurable FFmpeg binary path, async background processing, media library integration (new in 2.0.0)
- **Quality selector** — viewers can choose Auto, 1080p, 720p, 480p, or 360p from the player controls (new in 2.0.0)
- **True HTTP Range Request streaming** (HTTP 206 Partial Content) — no full-file loading
- **Custom video controls** — branded, consistent playback UI with play/pause, seek bar, volume, speed, quality selector, and fullscreen
- **Native Elementor widget** with full visual controls — drop a video into any page with the visual editor
- **Shortcode support**: `[video_stream id="123"]` for non-Elementor pages
- **Streaming from the Media Library** — uses `wp-content/uploads` directly, no external services
- **Buffer progress visualization** — shows users how much of the video has loaded
- **Configurable chunk size** — balance memory usage vs. I/O performance
- **Optional login-only access** — restrict streaming to authenticated users
- **Path traversal protection** — strict file path validation prevents directory traversal attacks
- **Keyboard shortcuts** — Space, arrows, F, M, Q for complete keyboard control
- **Responsive design** — mobile-optimized controls with larger touch targets

**Requirements:**

- WordPress 7.0.2+
- PHP 8.3.32+
- Elementor 3.x+ (for widget support — the shortcode works without Elementor)
- FFmpeg (optional — required for HLS compression; the plugin works without it using single-file streaming)

== Installation ==

1. Upload the `video-stream-buffer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Settings > Video Stream Buffer** to configure FFmpeg path, resolutions, chunk size, and access restrictions.
4. Upload a video (MP4, WebM, or Ogg) to your Media Library.
5. (Optional) Click "Compress for Streaming" on the video in the Media Library to generate HLS adaptive bitrate versions.
6. Use the `[video_stream id="123"]` shortcode (replace 123 with your attachment ID), or add the "Video Stream Buffer" Elementor widget to any page.

**FFmpeg Setup:**

To use HLS compression, FFmpeg must be installed on your server. On Ubuntu/Debian: `sudo apt install ffmpeg`. On macOS: `brew install ffmpeg`. The plugin will auto-detect `ffmpeg` from the system PATH, or you can configure a custom path in Settings.

== How to Test Streaming ==

Once you have uploaded a video and noted its attachment ID, you can verify the streaming endpoint works correctly using curl.

**Full file request (HTTP 200):**

`curl -I "https://yoursite.com/wp-json/video-stream/v1/play/123"`

Expected headers: `HTTP/1.1 200 OK`, `Accept-Ranges: bytes`, `Content-Type: video/mp4`

**Range request (HTTP 206 Partial Content):**

`curl -I -H "Range: bytes=0-1023" "https://yoursite.com/wp-json/video-stream/v1/play/123"`

Expected headers: `HTTP/1.1 206 Partial Content`, `Content-Range: bytes 0-1023/XXXX`, `Content-Length: 1024`

**HLS master playlist (v2.0.0+):**

`curl "https://yoursite.com/wp-json/video-stream/v1/hls/123/master.m3u8"`

After compression, this returns the master playlist with available quality levels.

**Play in browser:**

Visit any page containing the shortcode or Elementor widget. Open the browser's Network tab and look for the `/wp-json/video-stream/v1/play/` request — it should show multiple requests with 206 status as you seek through the video. For HLS videos, you'll see `.m3u8` and `.ts` segment requests instead.

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
- `controls_style` — `native` or `custom` (default: `native`)
- `preferred_quality` — `auto` (default), `1080p`, `720p`, `480p`, `360p` (new in 2.0.0; only applies to HLS-compressed videos)

**Examples:**

Full-featured HLS video with custom controls: `[video_stream id="123" controls_style="custom" preferred_quality="720p"]`

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
- **Controls Style** selector — "Custom Controls" (default) or "Native Browser"
- **Preferred Quality** selector — Auto (adaptive), 1080p, 720p, 480p, 360p (new in 2.0.0)

**Style controls — Buffer Bar:**
- Buffer bar color
- Progress bar background color
- Player background color
- Border radius

**Style controls — Custom Controls:**
- Controls background color
- Progress bar color (played portion)
- Buffered bar color (buffered portion on seek bar)
- Text / icon color
- Controls border radius

== Compression & HLS (2.0.0+) ==

**How it works:**

1. Configure FFmpeg path and enabled resolutions in Settings > Video Stream Buffer.
2. Go to Media Library, click "Compress for Streaming" on any video.
3. The plugin schedules a background task that runs FFmpeg to generate HLS segments at each enabled resolution (1080p, 720p, 480p, 360p).
4. A master playlist (`master.m3u8`) is generated referencing all resolution playlists.
5. Once complete, the video player automatically loads hls.js and provides a quality selector.

**Compression settings:**
- **FFmpeg Binary Path** — default: `ffmpeg` (auto-detected from PATH)
- **Enabled Resolutions** — checkboxes for 1080p, 720p, 480p, 360p
- **HLS Segment Duration** — 2–10 seconds, default: 6
- **Audio Bitrate** — default: 128k

**Performance notes:**
- Compression is CPU-intensive and runs asynchronously via WP Cron.
- The settings page includes a compression status table showing all videos and their status (Pending, Compressing, Complete, Error).
- A "Bulk Compress All Uncompressed" button compresses all videos at once.

== Custom Controls Features ==

When Controls Style is set to "Custom Controls", the plugin replaces native browser controls with a branded control bar:

- **Play/Pause** — button in control bar + center overlay when paused
- **Progress Bar** — clickable and draggable seek bar with played (accent color) and buffered (lighter color) portions
- **Time Display** — current time / total duration
- **Volume** — mute toggle button + horizontal slider
- **Quality Selector** — dropdown listing Auto, 1080p, 720p, 480p, 360p (new in 2.0.0; only shown for HLS-compressed videos)
- **Fullscreen** — toggle fullscreen mode
- **Playback Speed** — selector with 0.5x, 0.75x, 1x, 1.25x, 1.5x, 2x options

**Keyboard Shortcuts:**
- Space — play/pause
- Left/Right arrows — skip 5 seconds
- Up/Down arrows — volume up/down
- F — toggle fullscreen
- M — mute/unmute
- Q — cycle quality levels (new in 2.0.0)

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

- **FFmpeg required for HLS:** Adaptive bitrate streaming requires FFmpeg to be installed on the server. The plugin continues to work as a single-file streaming solution without it.
- **Compression is CPU-intensive:** Encoding multiple resolutions can take significant server resources. Compression runs asynchronously via WP Cron to avoid blocking admin requests.
- **hls.js adds ~100KB:** The hls.js library is only loaded when a compressed video is on the page.
- **Concurrent streaming load:** Each active stream holds a PHP process and file handle. At very high concurrency (hundreds of simultaneous viewers), a dedicated streaming server or CDN is more appropriate.
- **Only media library files:** Videos must be uploaded through the WordPress Media Library. External URLs are not supported.
- **Single file per player:** Each widget/shortcode plays one video file. Playlists are not supported in this version.
- **No DRM:** The streaming endpoint does not implement digital rights management. Anyone with the REST URL can download the video file.

== Frequently Asked Questions ==

= Why does autoplay force muted? =

Modern browsers (Chrome, Firefox, Safari, Edge) block autoplay of videos with audio to prevent unwanted sound. By forcing `muted` when `autoplay` is on, the plugin ensures the video actually starts playing automatically.

= What is HLS adaptive bitrate streaming? =

HLS (HTTP Live Streaming) splits a video into short segments at multiple quality levels. The player automatically switches between qualities based on the viewer's network speed, providing smooth playback without buffering. The quality selector lets viewers manually choose a resolution.

= Do I need FFmpeg? =

FFmpeg is only required for the HLS compression feature. Without FFmpeg, the plugin works perfectly as a single-file streaming solution with HTTP Range Request support — exactly as it did in v1.x.

= Can I use this with a CDN? =

Yes. The plugin streams from your WordPress server. For production sites with significant traffic, place a CDN in front of your site — the standard `Accept-Ranges`, `ETag`, and `Cache-Control` headers enable CDN caching of streamed chunks.

= What video formats are supported? =

MP4 (H.264), WebM, and Ogg. MP4 has the broadest browser support and is recommended. For HLS compression, the source video must be in a format FFmpeg can read (MP4 is recommended).

= Is this secure? =

The plugin implements multiple security measures: path traversal prevention via realpath validation, MIME type allowlisting, nonce protection on admin forms and AJAX actions, capability checks, and proper data sanitization/escaping throughout. The optional login-only restriction adds an additional access layer.

= Are custom controls accessible? =

Yes. All buttons have `aria-label` attributes, the video element is keyboard-focusable, and keyboard shortcuts provide full control without a mouse. The progress bar uses a native range input for accessibility.

== Changelog ==

= 2.0.0 =
* Added: HLS adaptive bitrate streaming via FFmpeg compression
* Added: On-the-fly video compression engine with async background processing
* Added: Compress action in Media Library row actions and attachment details
* Added: Configurable FFmpeg binary path in admin settings
* Added: Resolution selection (1080p, 720p, 480p, 360p) with checkboxes
* Added: HLS segment duration and audio bitrate settings
* Added: Compression status table with Bulk Compress button in settings
* Added: Master playlist and variant playlist generation
* Added: HLS REST endpoints for .m3u8 manifests and .ts segments
* Added: hls.js integration with dynamic loading (only when HLS is available)
* Added: Quality selector button in custom controls (Auto, 1080p, 720p, 480p, 360p)
* Added: Keyboard shortcut Q to cycle quality levels
* Added: `preferred_quality` shortcode attribute and Elementor control
* Added: Auto-detection of HLS in the /play/ endpoint with `?format=hls` support
* Added: data-vsb-hls and data-vsb-resolutions attributes on video elements
* Improved: Stream handler now auto-redirects to HLS when compressed version exists
* Improved: Elementor edit mode shows HLS compression status
* Backward compatible: uncompressed videos stream exactly as before (single file via Range Requests)
* Requires FFmpeg for HLS compression features; single-file streaming works without it

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

= 1.0.3 =
* Fixed: Elementor widget not appearing in the editor

= 1.0.2 =
* Fixed fatal error: "Class Elementor\Widget_Base not found" during wp-cron

= 1.0.1 =
* Fixed fatal error: "Class VSB_Widget_Video_Stream not found" when Elementor loads after plugin

= 1.0.0 =
* Initial release
* REST API streaming endpoint with HTTP Range Request support
* `[video_stream]` shortcode
* Native Elementor widget with content and style controls
* Admin settings page (chunk size, access restriction)
* Buffer progress bar visualization
* Path traversal protection and MIME type validation

== Upgrade Notice ==

= 2.0.0 =
Major update: HLS adaptive bitrate streaming with FFmpeg compression, quality selector, and hls.js integration. Existing shortcodes and widgets continue to work unchanged — HLS features are opt-in per video via the Compress action. See Settings > Video Stream Buffer for new compression configuration options.

= 1.1.0 =
New: Custom video controls with progress bar, volume slider, speed selector, and fullscreen. The Elementor widget now defaults to Custom Controls.

= 1.0.0 =
Initial release. No upgrade steps required.
