=== Video Stream Buffer ===
Contributors: videostreambuffer
Tags: video, streaming, elementor, html5 video, range requests, media library, buffer
Requires at least: 7.0.2
Tested up to: 7.0.2
Requires PHP: 8.3.32
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Delivers real video streaming via HTTP Range Requests from the WordPress Media Library, with a native Elementor widget and shortcode support.

== Description ==

Video Stream Buffer enables true video streaming from your WordPress Media Library without needing a CDN or third-party video host. It serves video files through a custom REST API endpoint that supports HTTP Range Requests (byte-range), allowing browsers to seek, buffer, and play videos efficiently — exactly like a dedicated streaming server.

**Key features:**

- **True HTTP Range Request streaming** (HTTP 206 Partial Content) — no full-file loading
- **Native Elementor widget** with full visual controls — drop a video into any page with the visual editor
- **Shortcode support**: `[video_stream id="123"]` for non-Elementor pages
- **Streaming from the Media Library** — uses `wp-content/uploads` directly, no external services
- **Buffer progress visualization** — shows users how much of the video has loaded
- **Configurable chunk size** — balance memory usage vs. I/O performance
- **Optional login-only access** — restrict streaming to authenticated users
- **Path traversal protection** — strict file path validation prevents directory traversal attacks

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

**Examples:**

Full-featured video: `[video_stream id="123" autoplay="true" loop="true" aspect_ratio="4:3" poster="456"]`

Minimal: `[video_stream id="123"]`

== Elementor Widget ==

The "Video Stream Buffer" widget appears in the Elementor panel under the "Video Stream Buffer" category. It provides:

**Content controls:**
- Video picker (Media Library, video-only)
- Autoplay, Loop, Muted toggles
- Buffer bar visibility toggle
- Aspect ratio selector (16:9, 4:3, 1:1, auto)
- Poster image picker

**Style controls:**
- Buffer bar color
- Progress bar background color
- Player background color
- Border radius

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

== Changelog ==

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

= 1.0.0 =
Initial release. No upgrade steps required.
